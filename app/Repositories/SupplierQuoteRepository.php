<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class SupplierQuoteRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                supplier_quotes.*,
                suppliers.name AS supplier_name,
                suppliers.ruc AS supplier_ruc,
                suppliers.delivery_terms AS supplier_delivery_terms,
                purchase_requisitions.requisition_number,
                purchase_requisitions.stock_check_id,
                purchase_requisitions.production_order_id
             FROM supplier_quotes
             INNER JOIN suppliers ON suppliers.id = supplier_quotes.supplier_id
             INNER JOIN purchase_requisitions ON purchase_requisitions.id = supplier_quotes.purchase_requisition_id
             WHERE supplier_quotes.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithItems(int $id): ?array
    {
        $quote = $this->find($id);
        if (!$quote) {
            return null;
        }

        $quote['items'] = $this->items($id);
        return $quote;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byRequisition(int $purchaseRequisitionId): array
    {
        return $this->fetchAll(
            'SELECT
                supplier_quotes.*,
                suppliers.name AS supplier_name,
                suppliers.ruc AS supplier_ruc,
                COUNT(supplier_quote_items.id) AS item_count
             FROM supplier_quotes
             INNER JOIN suppliers ON suppliers.id = supplier_quotes.supplier_id
             LEFT JOIN supplier_quote_items ON supplier_quote_items.supplier_quote_id = supplier_quotes.id
             WHERE supplier_quotes.purchase_requisition_id = :id
             GROUP BY supplier_quotes.id
             ORDER BY supplier_quotes.status = "approved" DESC, supplier_quotes.total_amount ASC, supplier_quotes.id',
            ['id' => $purchaseRequisitionId]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function registerReceivedQuote(int $purchaseRequisitionId, int $supplierId, array $data, array $items): int
    {
        $requisition = (new PurchaseRequisitionRepository())->find($purchaseRequisitionId);
        if (!$requisition) {
            throw new InvalidArgumentException('Pedido de presupuesto no encontrado.');
        }
        if (in_array((string) $requisition['status'], ['cancelled', 'closed'], true)) {
            throw new InvalidArgumentException('No se puede registrar presupuesto en un pedido cerrado o anulado.');
        }
        if (!(new SupplierRepository())->find($supplierId)) {
            throw new InvalidArgumentException('Proveedor no encontrado.');
        }
        if (!$this->quoteRequestExists($purchaseRequisitionId, $supplierId)) {
            throw new InvalidArgumentException('El proveedor debe estar asociado al pedido antes de registrar su presupuesto.');
        }

        $items = $this->normalizeItems($purchaseRequisitionId, $items);
        if ($items === []) {
            throw new InvalidArgumentException('El presupuesto debe tener al menos un ítem.');
        }

        $subtotal = array_reduce($items, static fn (float $sum, array $item): float => $sum + (float) $item['total_price'], 0.0);
        $tax = trim((string) ($data['tax_amount'] ?? '')) === '' ? 0.0 : (float) $data['tax_amount'];
        $total = trim((string) ($data['total_amount'] ?? '')) === '' ? $subtotal + $tax : (float) $data['total_amount'];

        if ($tax < 0 || $total < 0) {
            throw new InvalidArgumentException('Los totales del presupuesto no pueden ser negativos.');
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO supplier_quotes (
                    purchase_requisition_id, supplier_id, quote_number, quote_date, status,
                    currency, subtotal, tax_amount, total_amount, delivery_days, payment_terms,
                    attachment_path, notes, received_at, updated_at
                ) VALUES (
                    :purchase_requisition_id, :supplier_id, :quote_number, :quote_date, "received",
                    :currency, :subtotal, :tax_amount, :total_amount, :delivery_days, :payment_terms,
                    :attachment_path, :notes, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )',
                [
                    'purchase_requisition_id' => $purchaseRequisitionId,
                    'supplier_id' => $supplierId,
                    'quote_number' => trim((string) ($data['quote_number'] ?? '')),
                    'quote_date' => trim((string) ($data['quote_date'] ?? date('Y-m-d'))),
                    'currency' => trim((string) ($data['currency'] ?? 'PYG')) ?: 'PYG',
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'total_amount' => $total,
                    'delivery_days' => trim((string) ($data['delivery_days'] ?? '')) === '' ? null : (int) $data['delivery_days'],
                    'payment_terms' => $this->nullable($data['payment_terms'] ?? null),
                    'attachment_path' => $this->nullable($data['attachment_path'] ?? null),
                    'notes' => $this->nullable($data['notes'] ?? null),
                ]
            );

            $quoteId = (int) $pdo->lastInsertId();
            foreach ($items as $item) {
                $this->execute(
                    'INSERT INTO supplier_quote_items (
                        supplier_quote_id, purchase_requisition_item_id, description, unit,
                        quantity, unit_price, total_price, notes, updated_at
                    ) VALUES (
                        :supplier_quote_id, :purchase_requisition_item_id, :description, :unit,
                        :quantity, :unit_price, :total_price, :notes, CURRENT_TIMESTAMP
                    )',
                    $item + ['supplier_quote_id' => $quoteId]
                );
            }

            $this->execute(
                'UPDATE supplier_quote_requests
                 SET status = "received", updated_at = CURRENT_TIMESTAMP
                 WHERE purchase_requisition_id = :purchase_requisition_id AND supplier_id = :supplier_id',
                ['purchase_requisition_id' => $purchaseRequisitionId, 'supplier_id' => $supplierId]
            );
            (new PurchaseRequisitionRepository())->markQuoted($purchaseRequisitionId);

            $pdo->commit();
            $this->audit('register_supplier_quote', null, 'received', $this->findWithItems($quoteId), $quoteId, null);

            return $quoteId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function approve(int $supplierQuoteId, ?string $overrideReason = null): void
    {
        $quote = $this->findWithItems($supplierQuoteId);
        if (!$quote) {
            throw new InvalidArgumentException('Presupuesto no encontrado.');
        }
        if (in_array((string) $quote['status'], ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('No se puede aprobar un presupuesto anulado o rechazado.');
        }
        if (($quote['items'] ?? []) === []) {
            throw new InvalidArgumentException('No se puede aprobar un presupuesto sin ítems.');
        }

        $requestCount = (new PurchaseRequisitionRepository())->quoteRequestCount((int) $quote['purchase_requisition_id']);
        $overrideReason = trim((string) $overrideReason);
        if ($requestCount < 3) {
            if ($overrideReason === '') {
                throw new InvalidArgumentException('Se requieren al menos 3 proveedores solicitados para aprobar un presupuesto.');
            }
            if (Auth::role() !== 'admin') {
                throw new InvalidArgumentException('Solo admin puede aprobar con override de mínimo 3 proveedores.');
            }
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'UPDATE supplier_quotes
                 SET status = "rejected", rejected_by = :user_id, rejected_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE purchase_requisition_id = :purchase_requisition_id
                   AND id != :id
                   AND status IN ("draft", "received", "approved")',
                [
                    'purchase_requisition_id' => $quote['purchase_requisition_id'],
                    'id' => $supplierQuoteId,
                    'user_id' => Auth::id(),
                ]
            );
            $this->execute(
                'UPDATE supplier_quotes
                 SET status = "approved", approved_by = :user_id, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $supplierQuoteId, 'user_id' => Auth::id()]
            );
            (new PurchaseRequisitionRepository())->markApproved((int) $quote['purchase_requisition_id']);

            $pdo->commit();
            $this->audit('approve_supplier_quote', (string) $quote['status'], 'approved', $this->findWithItems($supplierQuoteId), $supplierQuoteId, $overrideReason ?: null);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $supplierQuoteId): array
    {
        return $this->fetchAll(
            'SELECT
                supplier_quote_items.*,
                purchase_requisition_items.required_material_type,
                purchase_requisition_items.missing_quantity,
                purchase_requisition_items.requested_quantity
             FROM supplier_quote_items
             INNER JOIN purchase_requisition_items ON purchase_requisition_items.id = supplier_quote_items.purchase_requisition_item_id
             WHERE supplier_quote_items.supplier_quote_id = :id
             ORDER BY supplier_quote_items.id',
            ['id' => $supplierQuoteId]
        );
    }

    private function quoteRequestExists(int $purchaseRequisitionId, int $supplierId): bool
    {
        return $this->fetchOne(
            'SELECT id FROM supplier_quote_requests WHERE purchase_requisition_id = :purchase_requisition_id AND supplier_id = :supplier_id AND status != "cancelled"',
            ['purchase_requisition_id' => $purchaseRequisitionId, 'supplier_id' => $supplierId]
        ) !== null;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(int $purchaseRequisitionId, array $items): array
    {
        $validItemIds = [];
        foreach ((new PurchaseRequisitionRepository())->items($purchaseRequisitionId) as $item) {
            $validItemIds[(int) $item['id']] = $item;
        }

        $normalized = [];
        foreach ($items as $item) {
            $requisitionItemId = (int) ($item['purchase_requisition_item_id'] ?? 0);
            if (!isset($validItemIds[$requisitionItemId])) {
                throw new InvalidArgumentException('Ítem de pedido inválido para este presupuesto.');
            }

            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $totalPrice = trim((string) ($item['total_price'] ?? '')) === ''
                ? $quantity * $unitPrice
                : (float) $item['total_price'];

            if ($quantity <= 0) {
                throw new InvalidArgumentException('La cantidad presupuestada debe ser mayor a cero.');
            }
            if ($unitPrice < 0 || $totalPrice < 0) {
                throw new InvalidArgumentException('Los precios del presupuesto no pueden ser negativos.');
            }

            $source = $validItemIds[$requisitionItemId];
            $description = trim((string) ($item['description'] ?? '')) ?: (string) $source['required_description'];
            $unit = trim((string) ($item['unit'] ?? '')) ?: (string) $source['required_unit'];

            $normalized[] = [
                'purchase_requisition_item_id' => $requisitionItemId,
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'notes' => $this->nullable($item['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $quote
     */
    private function audit(string $action, ?string $previous, ?string $next, ?array $quote, int $supplierQuoteId, ?string $overrideReason): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'supplier_quotes',
            $supplierQuoteId,
            (string) ($quote['quote_number'] ?? $supplierQuoteId),
            $action,
            $previous,
            $next,
            [
                'supplier_id' => $quote['supplier_id'] ?? null,
                'purchase_requisition_id' => $quote['purchase_requisition_id'] ?? null,
                'stock_check_id' => $quote['stock_check_id'] ?? null,
                'production_order_id' => $quote['production_order_id'] ?? null,
                'supplier_quote_id' => $supplierQuoteId,
                'total_amount' => $quote['total_amount'] ?? null,
                'currency' => $quote['currency'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
                'override_reason' => $overrideReason,
            ]
        );
    }
}
