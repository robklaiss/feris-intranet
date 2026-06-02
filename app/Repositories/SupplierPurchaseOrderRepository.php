<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class SupplierPurchaseOrderRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    supplier_purchase_orders.*,
                    suppliers.name AS supplier_name,
                    purchase_requisitions.requisition_number,
                    production_orders.production_number
                FROM supplier_purchase_orders
                INNER JOIN suppliers ON suppliers.id = supplier_purchase_orders.supplier_id
                INNER JOIN purchase_requisitions ON purchase_requisitions.id = supplier_purchase_orders.purchase_requisition_id
                INNER JOIN production_orders ON production_orders.id = purchase_requisitions.production_order_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                supplier_purchase_orders.supplier_po_number LIKE :q
                OR supplier_purchase_orders.iso_form_number LIKE :q
                OR suppliers.name LIKE :q
                OR purchase_requisitions.requisition_number LIKE :q
                OR production_orders.production_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND supplier_purchase_orders.status = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        $sql .= ' ORDER BY supplier_purchase_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                supplier_purchase_orders.*,
                suppliers.name AS supplier_name,
                suppliers.ruc AS supplier_ruc,
                purchase_requisitions.requisition_number,
                purchase_requisitions.stock_check_id,
                purchase_requisitions.production_order_id,
                supplier_quotes.quote_number,
                production_orders.production_number
             FROM supplier_purchase_orders
             INNER JOIN suppliers ON suppliers.id = supplier_purchase_orders.supplier_id
             INNER JOIN purchase_requisitions ON purchase_requisitions.id = supplier_purchase_orders.purchase_requisition_id
             INNER JOIN supplier_quotes ON supplier_quotes.id = supplier_purchase_orders.supplier_quote_id
             INNER JOIN production_orders ON production_orders.id = purchase_requisitions.production_order_id
             WHERE supplier_purchase_orders.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByRequisition(int $purchaseRequisitionId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM supplier_purchase_orders WHERE purchase_requisition_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => $purchaseRequisitionId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithItems(int $id): ?array
    {
        $order = $this->find($id);
        if (!$order) {
            return null;
        }

        $order['items'] = $this->items($id);
        return $order;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromApprovedQuote(int $supplierQuoteId, array $data = []): int
    {
        $quote = (new SupplierQuoteRepository())->findWithItems($supplierQuoteId);
        if (!$quote) {
            throw new InvalidArgumentException('Presupuesto no encontrado.');
        }
        if ((string) $quote['status'] !== 'approved') {
            throw new InvalidArgumentException('Solo se puede generar orden de compra desde un presupuesto aprobado.');
        }
        if (($quote['items'] ?? []) === []) {
            throw new InvalidArgumentException('No se puede generar orden de compra sin ítems.');
        }
        if ($this->findByQuote($supplierQuoteId)) {
            throw new InvalidArgumentException('Este presupuesto ya tiene una orden de compra proveedor.');
        }

        $supplier = (new SupplierRepository())->find((int) $quote['supplier_id']);
        $supplierPoNumber = trim((string) ($data['supplier_po_number'] ?? ''));
        if ($supplierPoNumber === '') {
            $supplierPoNumber = $this->nextOrderNumber();
        }

        $orderDate = trim((string) ($data['order_date'] ?? date('Y-m-d')));
        $expectedDeliveryDate = $this->nullable($data['expected_delivery_date'] ?? $this->expectedDeliveryDate($orderDate, $quote['delivery_days'] ?? null));

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO supplier_purchase_orders (
                    supplier_quote_id, purchase_requisition_id, supplier_id, supplier_po_number, status,
                    iso_form_number, requested_by, approved_by, approved_at, order_date, expected_delivery_date,
                    currency, subtotal, tax_amount, total_amount, payment_terms, delivery_terms,
                    purchase_reason, supplier_comparison_summary, product_specifications,
                    quality_requirements, notes, updated_at
                ) VALUES (
                    :supplier_quote_id, :purchase_requisition_id, :supplier_id, :supplier_po_number, "draft",
                    :iso_form_number, :requested_by, :approved_by, :approved_at, :order_date, :expected_delivery_date,
                    :currency, :subtotal, :tax_amount, :total_amount, :payment_terms, :delivery_terms,
                    :purchase_reason, :supplier_comparison_summary, :product_specifications,
                    :quality_requirements, :notes, CURRENT_TIMESTAMP
                )',
                [
                    'supplier_quote_id' => $supplierQuoteId,
                    'purchase_requisition_id' => $quote['purchase_requisition_id'],
                    'supplier_id' => $quote['supplier_id'],
                    'supplier_po_number' => $supplierPoNumber,
                    'iso_form_number' => $this->nullable($data['iso_form_number'] ?? null),
                    'requested_by' => Auth::id(),
                    'approved_by' => $quote['approved_by'] ?? Auth::id(),
                    'approved_at' => $quote['approved_at'] ?? date('Y-m-d H:i:s'),
                    'order_date' => $orderDate,
                    'expected_delivery_date' => $expectedDeliveryDate,
                    'currency' => $quote['currency'],
                    'subtotal' => $quote['subtotal'],
                    'tax_amount' => $quote['tax_amount'],
                    'total_amount' => $quote['total_amount'],
                    'payment_terms' => $this->nullable($data['payment_terms'] ?? $quote['payment_terms'] ?? $supplier['payment_terms'] ?? null),
                    'delivery_terms' => $this->nullable($data['delivery_terms'] ?? $supplier['delivery_terms'] ?? null),
                    'purchase_reason' => $this->nullable($data['purchase_reason'] ?? 'Faltantes detectados en verificación de stock.'),
                    'supplier_comparison_summary' => $this->nullable($data['supplier_comparison_summary'] ?? $this->comparisonSummary((int) $quote['purchase_requisition_id'], $supplierPoNumber)),
                    'product_specifications' => $this->nullable($data['product_specifications'] ?? $this->productSpecifications($quote['items'])),
                    'quality_requirements' => $this->nullable($data['quality_requirements'] ?? 'Cumplir especificaciones técnicas aprobadas y condiciones del presupuesto.'),
                    'notes' => $this->nullable($data['notes'] ?? null),
                ]
            );

            $orderId = (int) $pdo->lastInsertId();
            foreach ($quote['items'] as $item) {
                $this->execute(
                    'INSERT INTO supplier_purchase_order_items (
                        supplier_purchase_order_id, purchase_requisition_item_id, description, unit,
                        quantity, unit_price, total_price, notes, updated_at
                    ) VALUES (
                        :supplier_purchase_order_id, :purchase_requisition_item_id, :description, :unit,
                        :quantity, :unit_price, :total_price, :notes, CURRENT_TIMESTAMP
                    )',
                    [
                        'supplier_purchase_order_id' => $orderId,
                        'purchase_requisition_item_id' => $item['purchase_requisition_item_id'],
                        'description' => $item['description'],
                        'unit' => $item['unit'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'notes' => $item['notes'] ?? null,
                    ]
                );
            }

            $pdo->commit();
            $this->audit('create_supplier_purchase_order', null, 'draft', $this->findWithItems($orderId), $orderId);

            return $orderId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function confirm(int $id): void
    {
        $this->transition($id, 'confirmed', 'confirm_supplier_purchase_order');
    }

    public function cancel(int $id): void
    {
        $this->transition($id, 'cancelled', 'cancel_supplier_purchase_order');
    }

    public function close(int $id): void
    {
        $this->transition($id, 'closed', 'close_supplier_purchase_order');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $supplierPurchaseOrderId): array
    {
        return $this->fetchAll(
            'SELECT
                supplier_purchase_order_items.*,
                purchase_requisition_items.required_material_type,
                purchase_requisition_items.missing_quantity
             FROM supplier_purchase_order_items
             INNER JOIN purchase_requisition_items ON purchase_requisition_items.id = supplier_purchase_order_items.purchase_requisition_item_id
             WHERE supplier_purchase_order_items.supplier_purchase_order_id = :id
             ORDER BY supplier_purchase_order_items.id',
            ['id' => $supplierPurchaseOrderId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByQuote(int $supplierQuoteId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM supplier_purchase_orders WHERE supplier_quote_id = :id LIMIT 1',
            ['id' => $supplierQuoteId]
        );
    }

    private function transition(int $id, string $status, string $action): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Orden de compra proveedor no encontrada.');
        }
        if (in_array((string) $existing['status'], ['cancelled', 'closed'], true)) {
            throw new InvalidArgumentException('No se puede modificar una orden cerrada o anulada.');
        }

        $this->execute(
            'UPDATE supplier_purchase_orders SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $id, 'status' => $status]
        );
        $this->audit($action, (string) $existing['status'], $status, $this->findWithItems($id), $id);
    }

    private function nextOrderNumber(): string
    {
        $count = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM supplier_purchase_orders')['total'] ?? 0);
        return sprintf('OC-PROV-%04d', $count + 1);
    }

    private function expectedDeliveryDate(string $orderDate, mixed $deliveryDays): ?string
    {
        if ($deliveryDays === null || trim((string) $deliveryDays) === '') {
            return null;
        }

        $timestamp = strtotime($orderDate . ' +' . max(0, (int) $deliveryDays) . ' days');
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function comparisonSummary(int $purchaseRequisitionId, string $supplierPoNumber): string
    {
        $quotes = (new SupplierQuoteRepository())->byRequisition($purchaseRequisitionId);
        $parts = [];
        foreach ($quotes as $quote) {
            $parts[] = sprintf(
                '%s: %s %s, plazo %s días, estado %s',
                (string) $quote['supplier_name'],
                (string) $quote['currency'],
                number_format((float) $quote['total_amount'], 2, '.', ''),
                (string) ($quote['delivery_days'] ?? '-'),
                (string) $quote['status']
            );
        }

        return 'Comparación para ' . $supplierPoNumber . ': ' . implode(' | ', $parts);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function productSpecifications(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            $parts[] = sprintf(
                '%s - %s %s',
                (string) $item['description'],
                (string) $item['quantity'],
                (string) $item['unit']
            );
        }

        return implode('; ', $parts);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $order
     */
    private function audit(string $action, ?string $previous, ?string $next, ?array $order, int $supplierPurchaseOrderId): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'supplier_purchase_orders',
            $supplierPurchaseOrderId,
            (string) ($order['supplier_po_number'] ?? $supplierPurchaseOrderId),
            $action,
            $previous,
            $next,
            [
                'supplier_id' => $order['supplier_id'] ?? null,
                'purchase_requisition_id' => $order['purchase_requisition_id'] ?? null,
                'stock_check_id' => $order['stock_check_id'] ?? null,
                'production_order_id' => $order['production_order_id'] ?? null,
                'supplier_quote_id' => $order['supplier_quote_id'] ?? null,
                'supplier_purchase_order_id' => $supplierPurchaseOrderId,
                'total_amount' => $order['total_amount'] ?? null,
                'currency' => $order['currency'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
            ]
        );
    }
}
