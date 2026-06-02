<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class GoodsReceiptRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    goods_receipts.*,
                    suppliers.name AS supplier_name,
                    supplier_purchase_orders.supplier_po_number,
                    COALESCE(SUM(goods_receipt_items.accepted_quantity), 0) AS total_accepted,
                    COALESCE(SUM(goods_receipt_items.rejected_quantity), 0) AS total_rejected
                FROM goods_receipts
                INNER JOIN suppliers ON suppliers.id = goods_receipts.supplier_id
                INNER JOIN supplier_purchase_orders ON supplier_purchase_orders.id = goods_receipts.supplier_purchase_order_id
                LEFT JOIN goods_receipt_items ON goods_receipt_items.goods_receipt_id = goods_receipts.id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                goods_receipts.receipt_number LIKE :q
                OR goods_receipts.delivery_note_number LIKE :q
                OR goods_receipts.invoice_number LIKE :q
                OR suppliers.name LIKE :q
                OR suppliers.ruc LIKE :q
                OR supplier_purchase_orders.supplier_po_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND goods_receipts.status = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['supplier_purchase_order_id'])) {
            $sql .= ' AND goods_receipts.supplier_purchase_order_id = :supplier_purchase_order_id';
            $params['supplier_purchase_order_id'] = (int) $filters['supplier_purchase_order_id'];
        }

        $sql .= ' GROUP BY goods_receipts.id ORDER BY goods_receipts.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                goods_receipts.*,
                suppliers.name AS supplier_name,
                suppliers.ruc AS supplier_ruc,
                supplier_purchase_orders.supplier_po_number,
                supplier_purchase_orders.status AS supplier_purchase_order_status
             FROM goods_receipts
             INNER JOIN suppliers ON suppliers.id = goods_receipts.supplier_id
             INNER JOIN supplier_purchase_orders ON supplier_purchase_orders.id = goods_receipts.supplier_purchase_order_id
             WHERE goods_receipts.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithItems(int $id): ?array
    {
        $receipt = $this->find($id);
        if (!$receipt) {
            return null;
        }

        $receipt['items'] = $this->items($id);
        return $receipt;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bySupplierPurchaseOrder(int $supplierPurchaseOrderId): array
    {
        return $this->search(['supplier_purchase_order_id' => $supplierPurchaseOrderId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $goodsReceiptId): array
    {
        return $this->fetchAll(
            'SELECT
                goods_receipt_items.*,
                raw_material_inventory.internal_code AS inventory_internal_code
             FROM goods_receipt_items
             LEFT JOIN raw_material_inventory ON raw_material_inventory.id = goods_receipt_items.raw_material_inventory_id
             WHERE goods_receipt_items.goods_receipt_id = :id
             ORDER BY goods_receipt_items.id',
            ['id' => $goodsReceiptId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingItemsForSupplierPurchaseOrder(int $supplierPurchaseOrderId): array
    {
        $items = (new SupplierPurchaseOrderRepository())->items($supplierPurchaseOrderId);
        $pending = [];

        foreach ($items as $item) {
            $previouslyReceived = $this->acceptedQuantityForSupplierPurchaseOrderItem((int) $item['id']);
            $ordered = (float) $item['quantity'];
            $pendingQuantity = max(0.0, $ordered - $previouslyReceived);
            $item['previously_received_quantity'] = $previouslyReceived;
            $item['pending_quantity'] = $pendingQuantity;

            if ($pendingQuantity > 0.0001) {
                $pending[] = $item;
            }
        }

        return $pending;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createDraftFromSupplierPurchaseOrder(int $supplierPurchaseOrderId, array $data, array $items): int
    {
        $order = (new SupplierPurchaseOrderRepository())->find($supplierPurchaseOrderId);
        if (!$order) {
            throw new InvalidArgumentException('Orden de compra proveedor no encontrada.');
        }
        if (!in_array((string) $order['status'], ['confirmed', 'sent', 'partially_received'], true)) {
            throw new InvalidArgumentException('Solo se puede registrar recepción desde una OC proveedor confirmada, enviada o parcialmente recibida.');
        }

        $normalizedItems = $this->normalizeItems($supplierPurchaseOrderId, $items);
        if ($normalizedItems === []) {
            throw new InvalidArgumentException('No se puede guardar una recepción sin ítems con cantidad recibida.');
        }

        $receiptNumber = trim((string) ($data['receipt_number'] ?? ''));
        if ($receiptNumber === '') {
            $receiptNumber = $this->nextReceiptNumber();
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO goods_receipts (
                    supplier_purchase_order_id, supplier_id, receipt_number, status, received_by,
                    received_at, delivery_note_number, invoice_number, notes, updated_at
                ) VALUES (
                    :supplier_purchase_order_id, :supplier_id, :receipt_number, "draft", :received_by,
                    :received_at, :delivery_note_number, :invoice_number, :notes, CURRENT_TIMESTAMP
                )',
                [
                    'supplier_purchase_order_id' => $supplierPurchaseOrderId,
                    'supplier_id' => $order['supplier_id'],
                    'receipt_number' => $receiptNumber,
                    'received_by' => Auth::id(),
                    'received_at' => $this->nullable($data['received_at'] ?? date('Y-m-d H:i:s')),
                    'delivery_note_number' => $this->nullable($data['delivery_note_number'] ?? null),
                    'invoice_number' => $this->nullable($data['invoice_number'] ?? null),
                    'notes' => $this->nullable($data['notes'] ?? null),
                ]
            );
            $receiptId = (int) $pdo->lastInsertId();

            foreach ($normalizedItems as $item) {
                $this->execute(
                    'INSERT INTO goods_receipt_items (
                        goods_receipt_id, supplier_purchase_order_item_id, description, unit,
                        ordered_quantity, previously_received_quantity, received_quantity,
                        rejected_quantity, accepted_quantity, internal_code, material_type,
                        lot_number, location, cost, quality_status, notes, updated_at
                    ) VALUES (
                        :goods_receipt_id, :supplier_purchase_order_item_id, :description, :unit,
                        :ordered_quantity, :previously_received_quantity, :received_quantity,
                        :rejected_quantity, :accepted_quantity, :internal_code, :material_type,
                        :lot_number, :location, :cost, :quality_status, :notes, CURRENT_TIMESTAMP
                    )',
                    $item + ['goods_receipt_id' => $receiptId]
                );
            }

            $pdo->commit();
            $this->audit('create_goods_receipt', null, 'draft', $this->findWithItems($receiptId), $receiptId, null);

            return $receiptId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function confirm(int $id): void
    {
        $receipt = $this->findWithItems($id);
        if (!$receipt) {
            throw new InvalidArgumentException('Recepción no encontrada.');
        }
        if ((string) $receipt['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede confirmar una recepción en borrador.');
        }
        if (in_array((string) $receipt['supplier_purchase_order_status'], ['cancelled', 'closed'], true)) {
            throw new InvalidArgumentException('No se puede confirmar recepción de una OC proveedor anulada o cerrada.');
        }

        foreach ($receipt['items'] as $item) {
            $this->validateConfirmItem($item);
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            foreach ($receipt['items'] as $item) {
                if ((float) $item['accepted_quantity'] <= 0.0001) {
                    continue;
                }

                $inventoryId = (new RawMaterialInventoryRepository())->create([
                    'internal_code' => $item['internal_code'],
                    'material_type' => $item['material_type'],
                    'description' => $item['description'],
                    'unit' => $item['unit'],
                    'quantity_available' => $item['accepted_quantity'],
                    'quantity_reserved' => 0,
                    'minimum_stock' => 0,
                    'supplier_name' => $receipt['supplier_name'],
                    'supplier_ruc' => $receipt['supplier_ruc'],
                    'lot_number' => $item['lot_number'],
                    'location' => $item['location'],
                    'cost' => $item['cost'],
                    'status' => 'active',
                    'notes' => 'Ingreso por recepción ' . (string) $receipt['receipt_number'],
                    'source_goods_receipt_item_id' => $item['id'],
                ]);

                $this->execute(
                    'UPDATE goods_receipt_items
                     SET raw_material_inventory_id = :raw_material_inventory_id,
                         quality_status = "accepted",
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id',
                    ['id' => $item['id'], 'raw_material_inventory_id' => $inventoryId]
                );

                $this->audit('create_raw_material_from_receipt', null, 'active', $this->findWithItems($id), $id, [
                    'internal_code' => $item['internal_code'],
                    'accepted_quantity' => $item['accepted_quantity'],
                    'rejected_quantity' => $item['rejected_quantity'],
                    'raw_material_inventory_id' => $inventoryId,
                ]);
            }

            $this->execute(
                'UPDATE goods_receipts SET status = "confirmed", updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['id' => $id]
            );

            $previousStatus = (string) $receipt['supplier_purchase_order_status'];
            $newStatus = $this->updateSupplierPurchaseOrderReceiptStatus((int) $receipt['supplier_purchase_order_id']);

            $pdo->commit();

            $confirmedReceipt = $this->findWithItems($id);
            $this->audit('confirm_goods_receipt', 'draft', 'confirmed', $confirmedReceipt, $id, [
                'status_previous' => 'draft',
                'status_new' => 'confirmed',
                'supplier_purchase_order_status_previous' => $previousStatus,
                'supplier_purchase_order_status_new' => $newStatus,
            ]);
            $this->audit('update_supplier_purchase_order_receipt_status', $previousStatus, $newStatus, $confirmedReceipt, $id, [
                'estado_anterior_oc_proveedor' => $previousStatus,
                'estado_nuevo_oc_proveedor' => $newStatus,
            ]);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function cancelDraft(int $id): void
    {
        $receipt = $this->findWithItems($id);
        if (!$receipt) {
            throw new InvalidArgumentException('Recepción no encontrada.');
        }
        if ((string) $receipt['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede anular una recepción en borrador.');
        }

        $this->execute(
            'UPDATE goods_receipts SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $id]
        );
        $this->audit('cancel_goods_receipt', 'draft', 'cancelled', $this->findWithItems($id), $id, null);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(int $supplierPurchaseOrderId, array $items): array
    {
        $orderItems = [];
        foreach ((new SupplierPurchaseOrderRepository())->items($supplierPurchaseOrderId) as $item) {
            $orderItems[(int) $item['id']] = $item;
        }

        $normalized = [];
        foreach ($items as $item) {
            $supplierPurchaseOrderItemId = (int) ($item['supplier_purchase_order_item_id'] ?? 0);
            if (!isset($orderItems[$supplierPurchaseOrderItemId])) {
                continue;
            }

            $receivedQuantity = (float) ($item['received_quantity'] ?? 0);
            $acceptedQuantity = (float) ($item['accepted_quantity'] ?? 0);
            $rejectedQuantity = (float) ($item['rejected_quantity'] ?? 0);
            if ($receivedQuantity <= 0.0001 && $acceptedQuantity <= 0.0001 && $rejectedQuantity <= 0.0001) {
                continue;
            }

            $orderItem = $orderItems[$supplierPurchaseOrderItemId];
            $previouslyReceived = $this->acceptedQuantityForSupplierPurchaseOrderItem($supplierPurchaseOrderItemId);
            $pending = max(0.0, (float) $orderItem['quantity'] - $previouslyReceived);

            $row = [
                'supplier_purchase_order_item_id' => $supplierPurchaseOrderItemId,
                'description' => trim((string) ($item['description'] ?? $orderItem['description'])),
                'unit' => trim((string) ($item['unit'] ?? $orderItem['unit'])),
                'ordered_quantity' => (float) $orderItem['quantity'],
                'previously_received_quantity' => $previouslyReceived,
                'received_quantity' => $receivedQuantity,
                'accepted_quantity' => $acceptedQuantity,
                'rejected_quantity' => $rejectedQuantity,
                'internal_code' => $this->nullable($item['internal_code'] ?? null),
                'material_type' => trim((string) ($item['material_type'] ?? $orderItem['required_material_type'] ?? $orderItem['description'])),
                'lot_number' => $this->nullable($item['lot_number'] ?? null),
                'location' => $this->nullable($item['location'] ?? null),
                'cost' => trim((string) ($item['cost'] ?? '')) === '' ? null : (float) $item['cost'],
                'quality_status' => trim((string) ($item['quality_status'] ?? 'pending')) ?: 'pending',
                'notes' => $this->nullable($item['notes'] ?? null),
            ];

            if ($row['description'] === '') {
                throw new InvalidArgumentException('La descripción recibida es obligatoria.');
            }
            if ($row['unit'] === '') {
                throw new InvalidArgumentException('La unidad recibida es obligatoria.');
            }
            if ($row['material_type'] === '') {
                throw new InvalidArgumentException('El tipo de material es obligatorio.');
            }
            if (!in_array($row['quality_status'], ['pending', 'accepted', 'rejected'], true)) {
                throw new InvalidArgumentException('Estado de calidad inválido.');
            }
            foreach (['received_quantity', 'accepted_quantity', 'rejected_quantity'] as $field) {
                if ((float) $row[$field] < 0) {
                    throw new InvalidArgumentException('No se permiten cantidades negativas en recepción.');
                }
            }
            if ((float) $row['accepted_quantity'] + (float) $row['rejected_quantity'] > (float) $row['received_quantity'] + 0.0001) {
                throw new InvalidArgumentException('Aceptado más rechazado no puede superar la cantidad recibida.');
            }
            if ((float) $row['received_quantity'] > $pending + 0.0001) {
                throw new InvalidArgumentException('No se puede recibir más que el saldo pendiente de la OC proveedor.');
            }
            if ((float) $row['accepted_quantity'] > 0.0001 && (string) $row['internal_code'] === '') {
                throw new InvalidArgumentException('El código interno es obligatorio para insumos aceptados.');
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function validateConfirmItem(array $item): void
    {
        $pendingAtCreation = (float) $item['ordered_quantity'] - (float) $item['previously_received_quantity'];
        if ((float) $item['received_quantity'] > $pendingAtCreation + 0.0001) {
            throw new InvalidArgumentException('No se puede confirmar una recepción por encima del saldo pendiente.');
        }
        if ((float) $item['accepted_quantity'] > 0.0001 && trim((string) $item['internal_code']) === '') {
            throw new InvalidArgumentException('El código interno es obligatorio para insumos aceptados.');
        }
        if ((float) $item['accepted_quantity'] > 0.0001) {
            $existing = $this->fetchOne(
                'SELECT id FROM raw_material_inventory WHERE internal_code = :internal_code LIMIT 1',
                ['internal_code' => trim((string) $item['internal_code'])]
            );
            if ($existing) {
                throw new InvalidArgumentException('El código interno ya existe en inventario.');
            }
        }
    }

    private function acceptedQuantityForSupplierPurchaseOrderItem(int $supplierPurchaseOrderItemId): float
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(SUM(goods_receipt_items.accepted_quantity), 0) AS total
             FROM goods_receipt_items
             INNER JOIN goods_receipts ON goods_receipts.id = goods_receipt_items.goods_receipt_id
             WHERE goods_receipt_items.supplier_purchase_order_item_id = :id
               AND goods_receipts.status = "confirmed"',
            ['id' => $supplierPurchaseOrderItemId]
        );

        return (float) ($row['total'] ?? 0);
    }

    private function updateSupplierPurchaseOrderReceiptStatus(int $supplierPurchaseOrderId): string
    {
        $allComplete = true;
        foreach ((new SupplierPurchaseOrderRepository())->items($supplierPurchaseOrderId) as $item) {
            $received = $this->acceptedQuantityForSupplierPurchaseOrderItem((int) $item['id']);
            if ($received < (float) $item['quantity'] - 0.0001) {
                $allComplete = false;
                break;
            }
        }

        $status = $allComplete ? 'received' : 'partially_received';
        $this->execute(
            'UPDATE supplier_purchase_orders SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $supplierPurchaseOrderId, 'status' => $status]
        );

        return $status;
    }

    private function nextReceiptNumber(): string
    {
        $count = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM goods_receipts')['total'] ?? 0);
        return sprintf('REC-INS-%04d', $count + 1);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $receipt
     * @param array<string, mixed>|null $extra
     */
    private function audit(string $action, ?string $previous, ?string $next, ?array $receipt, int $goodsReceiptId, ?array $extra): void
    {
        $firstItem = $receipt['items'][0] ?? [];
        (new OperationalAuditService())->logDocumentAction(
            'goods_receipts',
            $goodsReceiptId,
            (string) ($receipt['receipt_number'] ?? $goodsReceiptId),
            $action,
            $previous,
            $next,
            [
                'goods_receipt_id' => $goodsReceiptId,
                'supplier_purchase_order_id' => $receipt['supplier_purchase_order_id'] ?? null,
                'supplier_id' => $receipt['supplier_id'] ?? null,
                'receipt_number' => $receipt['receipt_number'] ?? null,
                'internal_code' => $extra['internal_code'] ?? ($firstItem['internal_code'] ?? null),
                'accepted_quantity' => $extra['accepted_quantity'] ?? ($firstItem['accepted_quantity'] ?? null),
                'rejected_quantity' => $extra['rejected_quantity'] ?? ($firstItem['rejected_quantity'] ?? null),
                'raw_material_inventory_id' => $extra['raw_material_inventory_id'] ?? ($firstItem['raw_material_inventory_id'] ?? null),
                'estado_anterior_oc_proveedor' => $extra['estado_anterior_oc_proveedor'] ?? ($extra['supplier_purchase_order_status_previous'] ?? null),
                'estado_nuevo_oc_proveedor' => $extra['estado_nuevo_oc_proveedor'] ?? ($extra['supplier_purchase_order_status_new'] ?? null),
                'status_previous' => $previous,
                'status_new' => $next,
                'items' => $receipt['items'] ?? [],
            ]
        );
    }
}
