<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class PurchaseRequisitionRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    purchase_requisitions.*,
                    stock_checks.check_number,
                    production_orders.production_number,
                    clients.name AS client_name,
                    COUNT(DISTINCT purchase_requisition_items.id) AS item_count,
                    COUNT(DISTINCT supplier_quote_requests.id) AS supplier_request_count,
                    COUNT(DISTINCT supplier_quotes.id) AS quote_count
                FROM purchase_requisitions
                INNER JOIN stock_checks ON stock_checks.id = purchase_requisitions.stock_check_id
                INNER JOIN production_orders ON production_orders.id = purchase_requisitions.production_order_id
                LEFT JOIN clients ON clients.id = production_orders.client_id
                LEFT JOIN purchase_requisition_items ON purchase_requisition_items.purchase_requisition_id = purchase_requisitions.id
                LEFT JOIN supplier_quote_requests ON supplier_quote_requests.purchase_requisition_id = purchase_requisitions.id
                LEFT JOIN supplier_quotes ON supplier_quotes.purchase_requisition_id = purchase_requisitions.id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                purchase_requisitions.requisition_number LIKE :q
                OR stock_checks.check_number LIKE :q
                OR production_orders.production_number LIKE :q
                OR clients.name LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND purchase_requisitions.status = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        $sql .= ' GROUP BY purchase_requisitions.id ORDER BY purchase_requisitions.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                purchase_requisitions.*,
                stock_checks.check_number,
                stock_checks.status AS stock_check_status,
                production_orders.production_number,
                production_orders.status AS production_order_status,
                production_orders.production_stage,
                clients.name AS client_name
             FROM purchase_requisitions
             INNER JOIN stock_checks ON stock_checks.id = purchase_requisitions.stock_check_id
             INNER JOIN production_orders ON production_orders.id = purchase_requisitions.production_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             WHERE purchase_requisitions.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByStockCheck(int $stockCheckId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM purchase_requisitions WHERE stock_check_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => $stockCheckId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithDetails(int $id): ?array
    {
        $requisition = $this->find($id);
        if (!$requisition) {
            return null;
        }

        $requisition['items'] = $this->items($id);
        $requisition['quote_requests'] = $this->quoteRequests($id);
        $requisition['quotes'] = (new SupplierQuoteRepository())->byRequisition($id);
        $requisition['purchase_order'] = (new SupplierPurchaseOrderRepository())->findByRequisition($id);

        return $requisition;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromStockCheck(int $stockCheckId, array $data = []): int
    {
        if ($this->findByStockCheck($stockCheckId)) {
            throw new InvalidArgumentException('Ya existe un pedido de presupuesto para esta verificación.');
        }

        $check = (new StockCheckRepository())->findWithItems($stockCheckId);
        if (!$check) {
            throw new InvalidArgumentException('Verificación de stock no encontrada.');
        }

        $missingItems = array_values(array_filter(
            $check['items'],
            static fn (array $item): bool => (float) ($item['missing_quantity'] ?? 0) > 0
        ));

        if ($missingItems === [] || !in_array((string) $check['status'], ['insufficient', 'draft'], true)) {
            throw new InvalidArgumentException('Solo se puede generar pedido de presupuesto desde una verificación con faltantes.');
        }

        $requestedQuantities = is_array($data['requested_quantity'] ?? null) ? $data['requested_quantity'] : [];
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $requisitionNumber = trim((string) ($data['requisition_number'] ?? ''));
            if ($requisitionNumber === '') {
                $requisitionNumber = $this->nextRequisitionNumber((int) $check['production_order_id']);
            }

            $this->execute(
                'INSERT INTO purchase_requisitions (
                    stock_check_id, production_order_id, requisition_number, status,
                    requested_by, requested_at, notes, updated_at
                ) VALUES (
                    :stock_check_id, :production_order_id, :requisition_number, "requested",
                    :requested_by, CURRENT_TIMESTAMP, :notes, CURRENT_TIMESTAMP
                )',
                [
                    'stock_check_id' => $stockCheckId,
                    'production_order_id' => $check['production_order_id'],
                    'requisition_number' => $requisitionNumber,
                    'requested_by' => Auth::id(),
                    'notes' => $this->nullable($data['notes'] ?? null),
                ]
            );

            $requisitionId = (int) $pdo->lastInsertId();
            foreach ($missingItems as $item) {
                $stockCheckItemId = (int) $item['id'];
                $requested = isset($requestedQuantities[$stockCheckItemId])
                    ? (float) $requestedQuantities[$stockCheckItemId]
                    : (float) $item['missing_quantity'];

                if ($requested <= 0) {
                    throw new InvalidArgumentException('La cantidad solicitada debe ser mayor a cero.');
                }

                $this->execute(
                    'INSERT INTO purchase_requisition_items (
                        purchase_requisition_id, stock_check_item_id, required_material_type,
                        required_description, required_unit, missing_quantity, requested_quantity, notes, updated_at
                    ) VALUES (
                        :purchase_requisition_id, :stock_check_item_id, :required_material_type,
                        :required_description, :required_unit, :missing_quantity, :requested_quantity, :notes, CURRENT_TIMESTAMP
                    )',
                    [
                        'purchase_requisition_id' => $requisitionId,
                        'stock_check_item_id' => $stockCheckItemId,
                        'required_material_type' => $item['required_material_type'],
                        'required_description' => $item['required_description'],
                        'required_unit' => $item['required_unit'],
                        'missing_quantity' => $item['missing_quantity'],
                        'requested_quantity' => $requested,
                        'notes' => $item['notes'] ?? null,
                    ]
                );
            }

            $pdo->commit();
            $this->audit('create_purchase_requisition', null, 'requested', $this->findWithDetails($requisitionId), $requisitionId);

            return $requisitionId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<int, int|string> $supplierIds
     * @param array<string, mixed> $data
     */
    public function addSupplierQuoteRequests(int $purchaseRequisitionId, array $supplierIds, array $data = []): void
    {
        $requisition = $this->find($purchaseRequisitionId);
        if (!$requisition) {
            throw new InvalidArgumentException('Pedido de presupuesto no encontrado.');
        }
        if (in_array((string) $requisition['status'], ['cancelled', 'closed'], true)) {
            throw new InvalidArgumentException('No se pueden agregar proveedores a un pedido cerrado o anulado.');
        }

        $supplierIds = array_values(array_unique(array_filter(array_map('intval', $supplierIds))));
        if ($supplierIds === []) {
            throw new InvalidArgumentException('Debe seleccionar al menos un proveedor.');
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            foreach ($supplierIds as $supplierId) {
                $supplier = (new SupplierRepository())->find($supplierId);
                if (!$supplier || (string) $supplier['status'] !== 'active') {
                    throw new InvalidArgumentException('Proveedor activo no encontrado.');
                }

                $this->execute(
                    'INSERT OR IGNORE INTO supplier_quote_requests (
                        purchase_requisition_id, supplier_id, status, sent_at, response_due_date, notes, updated_at
                    ) VALUES (
                        :purchase_requisition_id, :supplier_id, :status, :sent_at, :response_due_date, :notes, CURRENT_TIMESTAMP
                    )',
                    [
                        'purchase_requisition_id' => $purchaseRequisitionId,
                        'supplier_id' => $supplierId,
                        'status' => trim((string) ($data['status'] ?? 'sent')) ?: 'sent',
                        'sent_at' => $this->nullable($data['sent_at'] ?? date('Y-m-d')),
                        'response_due_date' => $this->nullable($data['response_due_date'] ?? null),
                        'notes' => $this->nullable($data['notes'] ?? null),
                    ]
                );
            }

            $this->execute(
                'UPDATE purchase_requisitions
                 SET status = CASE WHEN status = "draft" THEN "requested" ELSE status END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $purchaseRequisitionId]
            );

            $pdo->commit();
            $this->audit('add_supplier_quote_request', (string) $requisition['status'], (string) $this->find($purchaseRequisitionId)['status'], $this->findWithDetails($purchaseRequisitionId), $purchaseRequisitionId);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $purchaseRequisitionId): array
    {
        return $this->fetchAll(
            'SELECT * FROM purchase_requisition_items WHERE purchase_requisition_id = :id ORDER BY id',
            ['id' => $purchaseRequisitionId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function quoteRequests(int $purchaseRequisitionId): array
    {
        return $this->fetchAll(
            'SELECT
                supplier_quote_requests.*,
                suppliers.name AS supplier_name,
                suppliers.ruc AS supplier_ruc,
                suppliers.payment_terms AS supplier_payment_terms,
                suppliers.delivery_terms AS supplier_delivery_terms
             FROM supplier_quote_requests
             INNER JOIN suppliers ON suppliers.id = supplier_quote_requests.supplier_id
             WHERE supplier_quote_requests.purchase_requisition_id = :id
             ORDER BY supplier_quote_requests.id',
            ['id' => $purchaseRequisitionId]
        );
    }

    public function quoteRequestCount(int $purchaseRequisitionId): int
    {
        return (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM supplier_quote_requests WHERE purchase_requisition_id = :id AND status != "cancelled"',
            ['id' => $purchaseRequisitionId]
        )['total'] ?? 0);
    }

    public function markQuoted(int $purchaseRequisitionId): void
    {
        $this->execute(
            'UPDATE purchase_requisitions
             SET status = CASE WHEN status IN ("requested", "draft") THEN "quoted" ELSE status END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $purchaseRequisitionId]
        );
    }

    public function markApproved(int $purchaseRequisitionId): void
    {
        $this->execute(
            'UPDATE purchase_requisitions
             SET status = "approved", approved_by = :approved_by, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $purchaseRequisitionId, 'approved_by' => Auth::id()]
        );
    }

    private function nextRequisitionNumber(int $productionOrderId): string
    {
        $row = $this->fetchOne(
            'SELECT production_number FROM production_orders WHERE id = :id',
            ['id' => $productionOrderId]
        );
        $count = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM purchase_requisitions')['total'] ?? 0);

        return sprintf('REQ-%s-%04d', (string) ($row['production_number'] ?? $productionOrderId), $count + 1);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $requisition
     */
    private function audit(string $action, ?string $previous, ?string $next, ?array $requisition, int $purchaseRequisitionId): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'purchase_requisitions',
            $purchaseRequisitionId,
            (string) ($requisition['requisition_number'] ?? $purchaseRequisitionId),
            $action,
            $previous,
            $next,
            [
                'purchase_requisition_id' => $purchaseRequisitionId,
                'stock_check_id' => $requisition['stock_check_id'] ?? null,
                'production_order_id' => $requisition['production_order_id'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
            ]
        );
    }
}
