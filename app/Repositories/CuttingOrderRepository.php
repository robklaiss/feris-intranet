<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class CuttingOrderRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    cutting_orders.*,
                    production_orders.production_number,
                    customer_purchase_orders.po_number,
                    clients.name AS client_name,
                    contracts.contract_number
                FROM cutting_orders
                INNER JOIN production_orders ON production_orders.id = cutting_orders.production_order_id
                LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
                LEFT JOIN clients ON clients.id = production_orders.client_id
                LEFT JOIN contracts ON contracts.id = production_orders.contract_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                cutting_orders.cutting_number LIKE :q
                OR production_orders.production_number LIKE :q
                OR customer_purchase_orders.po_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND cutting_orders.status = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['production_order_id'])) {
            $sql .= ' AND cutting_orders.production_order_id = :production_order_id';
            $params['production_order_id'] = (int) $filters['production_order_id'];
        }

        $sql .= ' ORDER BY cutting_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                cutting_orders.*,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                production_orders.contract_id,
                production_orders.client_id,
                production_orders.production_stage,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number
             FROM cutting_orders
             INNER JOIN production_orders ON production_orders.id = cutting_orders.production_order_id
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             WHERE cutting_orders.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithDetails(int $id): ?array
    {
        $order = $this->find($id);
        if (!$order) {
            return null;
        }

        $order['items'] = $this->items($id);
        $order['materials'] = $this->materials($id);
        return $order;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byProductionOrder(int $productionOrderId): array
    {
        return $this->search(['production_order_id' => $productionOrderId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $cuttingOrderId): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM cutting_order_items
             WHERE cutting_order_id = :id
             ORDER BY id',
            ['id' => $cuttingOrderId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function materials(int $cuttingOrderId): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM cutting_order_materials
             WHERE cutting_order_id = :id
             ORDER BY id',
            ['id' => $cuttingOrderId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reservedMaterialsForProductionOrder(int $productionOrderId): array
    {
        return $this->fetchAll(
            'SELECT
                raw_material_reservations.*,
                raw_material_inventory.internal_code,
                raw_material_inventory.material_type,
                raw_material_inventory.description,
                raw_material_inventory.unit,
                production_order_items.item_code
             FROM raw_material_reservations
             INNER JOIN raw_material_inventory ON raw_material_inventory.id = raw_material_reservations.raw_material_inventory_id
             INNER JOIN production_order_items ON production_order_items.id = raw_material_reservations.production_order_item_id
             WHERE raw_material_reservations.production_order_id = :id
               AND raw_material_reservations.status = "reserved"
             ORDER BY production_order_items.id, raw_material_reservations.id',
            ['id' => $productionOrderId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDraftContext(int $productionOrderId): array
    {
        $order = (new ProductionOrderRepository())->findWithItems($productionOrderId);
        if (!$order) {
            throw new InvalidArgumentException('Orden de producción no encontrada.');
        }

        $order['items'] = $this->itemsWithPendingCut($productionOrderId);
        $order['reservations'] = $this->reservedMaterialsForProductionOrder($productionOrderId);
        $order['stock_check_id'] = $this->reservedStockCheckId($productionOrderId);

        return $order;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createFromProductionOrder(int $productionOrderId, array $data, array $items): int
    {
        $this->assertProductionReadyForCutting($productionOrderId);
        $normalizedItems = $this->normalizeItems($productionOrderId, $items);
        $reservations = $this->reservedMaterialsForProductionOrder($productionOrderId);
        if ($reservations === []) {
            throw new InvalidArgumentException('La orden de producción no tiene reservas activas para corte.');
        }

        $selectedItemIds = array_column($normalizedItems, 'production_order_item_id');
        $materials = array_values(array_filter(
            $reservations,
            static fn (array $reservation): bool => in_array((int) $reservation['production_order_item_id'], $selectedItemIds, true)
        ));
        if ($materials === []) {
            throw new InvalidArgumentException('No hay materiales reservados para los ítems seleccionados.');
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $cuttingNumber = trim((string) ($data['cutting_number'] ?? ''));
            if ($cuttingNumber === '') {
                $cuttingNumber = $this->nextCuttingNumber($productionOrderId);
            }

            $this->execute(
                'INSERT INTO cutting_orders (
                    production_order_id, stock_check_id, cutting_number, status, cut_by, planned_date,
                    notes, created_by, updated_at
                ) VALUES (
                    :production_order_id, :stock_check_id, :cutting_number, "draft", :cut_by, :planned_date,
                    :notes, :created_by, CURRENT_TIMESTAMP
                )',
                [
                    'production_order_id' => $productionOrderId,
                    'stock_check_id' => $this->reservedStockCheckId($productionOrderId),
                    'cutting_number' => $cuttingNumber,
                    'cut_by' => $this->nullable($data['cut_by'] ?? null),
                    'planned_date' => $this->nullable($data['planned_date'] ?? null),
                    'notes' => $this->nullable($data['notes'] ?? null),
                    'created_by' => Auth::id(),
                ]
            );

            $cuttingOrderId = (int) $pdo->lastInsertId();
            $itemIds = $this->insertItems($cuttingOrderId, $normalizedItems);
            $this->insertMaterials($cuttingOrderId, $materials, $itemIds);
            $pdo->commit();

            return $cuttingOrderId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function confirm(int $id): void
    {
        $order = $this->findWithDetails($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de corte no encontrada.');
        }
        if ((string) $order['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede confirmar una orden de corte en borrador.');
        }
        if ($order['materials'] === []) {
            throw new InvalidArgumentException('La orden de corte no tiene materiales reservados.');
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->consumeReservations($id);
            $this->execute(
                'UPDATE cutting_orders
                 SET status = "confirmed",
                     started_at = COALESCE(started_at, CURRENT_TIMESTAMP),
                     confirmed_by = :user_id,
                     confirmed_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $id, 'user_id' => Auth::id()]
            );
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = "in_cutting", updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = "confirmed"',
                ['id' => $order['production_order_id']]
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<int, float> $cutQuantities
     */
    public function complete(int $id, array $cutQuantities = []): void
    {
        $order = $this->findWithDetails($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de corte no encontrada.');
        }
        if (!in_array((string) $order['status'], ['confirmed', 'in_progress'], true)) {
            throw new InvalidArgumentException('Solo se puede completar una orden de corte confirmada o en proceso.');
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            foreach ($order['items'] as $item) {
                $quantity = $cutQuantities[(int) $item['id']] ?? (float) $item['quantity_to_cut'];
                $quantity = (float) $quantity;
                if ($quantity <= 0 || $quantity > (float) $item['quantity_to_cut'] + 0.0001) {
                    throw new InvalidArgumentException('La cantidad cortada debe ser mayor a cero y no superar la cantidad a cortar.');
                }

                $status = abs($quantity - (float) $item['quantity_to_cut']) <= 0.0001 ? 'cut' : 'partial';
                $this->execute(
                    'UPDATE cutting_order_items
                     SET quantity_cut = :quantity_cut,
                         status = :status,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id',
                    ['id' => $item['id'], 'quantity_cut' => $quantity, 'status' => $status]
                );
            }

            $nextStage = $this->requiresExternalWork($id) ? 'waiting_external_work' : 'in_sewing';
            $this->execute(
                'UPDATE cutting_orders
                 SET status = "completed",
                     completed_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $id]
            );
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = :stage, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = "confirmed"',
                ['id' => $order['production_order_id'], 'stage' => $nextStage]
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function close(int $id): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de corte no encontrada.');
        }
        if ((string) $order['status'] !== 'completed') {
            throw new InvalidArgumentException('Solo se puede cerrar una orden de corte completada.');
        }

        $this->execute(
            'UPDATE cutting_orders
             SET status = "closed",
                 closed_by = :user_id,
                 closed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
    }

    public function cancelDraft(int $id): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de corte no encontrada.');
        }
        if ((string) $order['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede anular una orden de corte en borrador. Las órdenes con consumo requieren reversa auditada.');
        }

        $this->execute(
            'UPDATE cutting_orders
             SET status = "cancelled",
                 cancelled_by = :user_id,
                 cancelled_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->execute(
            'UPDATE cutting_order_items SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE cutting_order_id = :id',
            ['id' => $id]
        );
        $this->execute(
            'UPDATE cutting_order_materials SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE cutting_order_id = :id',
            ['id' => $id]
        );
    }

    public function pendingCutQuantity(int $productionOrderItemId, ?int $excludeCuttingOrderId = null): float
    {
        $item = $this->fetchOne(
            'SELECT quantity FROM production_order_items WHERE id = :id',
            ['id' => $productionOrderItemId]
        );
        if (!$item) {
            throw new InvalidArgumentException('Ítem de producción no encontrado.');
        }

        $sql = 'SELECT COALESCE(SUM(cutting_order_items.quantity_to_cut), 0) AS total
                FROM cutting_order_items
                INNER JOIN cutting_orders ON cutting_orders.id = cutting_order_items.cutting_order_id
                WHERE cutting_order_items.production_order_item_id = :item_id
                  AND cutting_orders.status != "cancelled"';
        $params = ['item_id' => $productionOrderItemId];

        if ($excludeCuttingOrderId !== null) {
            $sql .= ' AND cutting_orders.id != :exclude_id';
            $params['exclude_id'] = $excludeCuttingOrderId;
        }

        $cut = (float) ($this->fetchOne($sql, $params)['total'] ?? 0);
        return max(0.0, (float) $item['quantity'] - $cut);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsWithPendingCut(int $productionOrderId): array
    {
        $items = $this->fetchAll(
            'SELECT
                production_order_items.*,
                contract_item_specs.fabric,
                contract_item_specs.measurements,
                contract_item_specs.technical_notes
             FROM production_order_items
             LEFT JOIN contract_item_specs ON contract_item_specs.id = production_order_items.contract_item_spec_id
             WHERE production_order_items.production_order_id = :id
             ORDER BY production_order_items.id',
            ['id' => $productionOrderId]
        );

        foreach ($items as &$item) {
            $item['pending_cut_quantity'] = $this->pendingCutQuantity((int) $item['id']);
        }
        unset($item);

        return $items;
    }

    private function assertProductionReadyForCutting(int $productionOrderId): void
    {
        $order = (new ProductionOrderRepository())->find($productionOrderId);
        if (!$order) {
            throw new InvalidArgumentException('Orden de producción no encontrada.');
        }
        if ((string) $order['status'] !== 'confirmed') {
            throw new InvalidArgumentException('Solo se puede crear corte desde una orden de producción confirmada.');
        }
        if (in_array((string) $order['status'], ['draft', 'cancelled', 'closed'], true)) {
            throw new InvalidArgumentException('No se puede crear corte desde una orden de producción borrador, anulada o cerrada.');
        }
        if (!in_array((string) $order['production_stage'], ['ready_for_cutting', 'in_cutting'], true)) {
            throw new InvalidArgumentException('La orden de producción debe tener stock reservado suficiente para corte.');
        }
        if ($this->hasActiveShortage($productionOrderId)) {
            throw new InvalidArgumentException('No se puede crear corte mientras existan faltantes activos.');
        }
    }

    private function hasActiveShortage(int $productionOrderId): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM stock_checks
             WHERE production_order_id = :id
               AND status = "insufficient"',
            ['id' => $productionOrderId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(int $productionOrderId, array $items): array
    {
        $sourceItems = $this->itemsWithPendingCut($productionOrderId);
        $sourceById = [];
        foreach ($sourceItems as $sourceItem) {
            $sourceById[(int) $sourceItem['id']] = $sourceItem;
        }

        if ($items === []) {
            foreach ($sourceItems as $sourceItem) {
                if ((float) $sourceItem['pending_cut_quantity'] > 0.0001) {
                    $items[] = [
                        'production_order_item_id' => (int) $sourceItem['id'],
                        'quantity_to_cut' => (float) $sourceItem['pending_cut_quantity'],
                        'notes' => '',
                    ];
                }
            }
        }

        if ($items === []) {
            throw new InvalidArgumentException('No hay saldo pendiente de corte.');
        }

        $reservedByItem = [];
        foreach ($this->reservedMaterialsForProductionOrder($productionOrderId) as $reservation) {
            $itemId = (int) $reservation['production_order_item_id'];
            $reservedByItem[$itemId] = ($reservedByItem[$itemId] ?? 0.0) + (float) $reservation['reserved_quantity'];
        }

        $normalized = [];
        foreach ($items as $item) {
            $productionItemId = (int) ($item['production_order_item_id'] ?? 0);
            if (!isset($sourceById[$productionItemId])) {
                throw new InvalidArgumentException('El ítem no pertenece a la orden de producción.');
            }

            $quantity = (float) ($item['quantity_to_cut'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('La cantidad a cortar debe ser mayor a cero.');
            }
            if ($quantity > (float) $sourceById[$productionItemId]['pending_cut_quantity'] + 0.0001) {
                throw new InvalidArgumentException('La cantidad a cortar supera el saldo pendiente de producción para ' . (string) $sourceById[$productionItemId]['item_code'] . '.');
            }
            if ($quantity > (float) ($reservedByItem[$productionItemId] ?? 0.0) + 0.0001) {
                throw new InvalidArgumentException('La cantidad a cortar supera la reserva activa para ' . (string) $sourceById[$productionItemId]['item_code'] . '.');
            }

            $source = $sourceById[$productionItemId];
            $normalized[] = [
                'production_order_item_id' => $productionItemId,
                'contract_item_spec_id' => $source['contract_item_spec_id'] ?: null,
                'item_code' => $source['item_code'],
                'product_type' => $source['product_type'],
                'description' => $source['description'],
                'size' => $source['size'],
                'color' => $source['color'],
                'fabric' => $source['fabric'] ?? null,
                'measurements' => $source['measurements'] ?? null,
                'quantity_to_cut' => $quantity,
                'unit' => $source['unit'] ?: 'unidad',
                'notes' => $this->nullable($item['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, int>
     */
    private function insertItems(int $cuttingOrderId, array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $this->execute(
                'INSERT INTO cutting_order_items (
                    cutting_order_id, production_order_item_id, contract_item_spec_id, item_code,
                    product_type, description, size, color, fabric, measurements, quantity_to_cut,
                    quantity_cut, unit, status, notes, updated_at
                ) VALUES (
                    :cutting_order_id, :production_order_item_id, :contract_item_spec_id, :item_code,
                    :product_type, :description, :size, :color, :fabric, :measurements, :quantity_to_cut,
                    0, :unit, "pending", :notes, CURRENT_TIMESTAMP
                )',
                $item + ['cutting_order_id' => $cuttingOrderId]
            );
            $ids[(int) $item['production_order_item_id']] = (int) $this->db()->lastInsertId();
        }

        return $ids;
    }

    /**
     * @param array<int, array<string, mixed>> $materials
     * @param array<int, int> $itemIds
     */
    private function insertMaterials(int $cuttingOrderId, array $materials, array $itemIds): void
    {
        foreach ($materials as $material) {
            $this->execute(
                'INSERT INTO cutting_order_materials (
                    cutting_order_id, cutting_order_item_id, raw_material_reservation_id,
                    raw_material_inventory_id, internal_code, material_type, description, unit,
                    reserved_quantity, consumed_quantity, status, notes, updated_at
                ) VALUES (
                    :cutting_order_id, :cutting_order_item_id, :raw_material_reservation_id,
                    :raw_material_inventory_id, :internal_code, :material_type, :description, :unit,
                    :reserved_quantity, 0, "reserved", :notes, CURRENT_TIMESTAMP
                )',
                [
                    'cutting_order_id' => $cuttingOrderId,
                    'cutting_order_item_id' => $itemIds[(int) $material['production_order_item_id']] ?? null,
                    'raw_material_reservation_id' => $material['id'],
                    'raw_material_inventory_id' => $material['raw_material_inventory_id'],
                    'internal_code' => $material['internal_code'],
                    'material_type' => $material['material_type'],
                    'description' => $material['description'],
                    'unit' => $material['unit'],
                    'reserved_quantity' => $material['reserved_quantity'],
                    'notes' => $material['notes'] ?? null,
                ]
            );
        }
    }

    private function consumeReservations(int $cuttingOrderId): void
    {
        foreach ($this->materials($cuttingOrderId) as $material) {
            if ((string) $material['status'] !== 'reserved') {
                continue;
            }

            $reservation = $this->fetchOne(
                'SELECT * FROM raw_material_reservations WHERE id = :id',
                ['id' => $material['raw_material_reservation_id']]
            );
            if (!$reservation || (string) $reservation['status'] !== 'reserved') {
                throw new InvalidArgumentException('La reserva asociada ya no está disponible para consumo.');
            }
            if ((float) $material['reserved_quantity'] > (float) $reservation['reserved_quantity'] + 0.0001) {
                throw new InvalidArgumentException('No se puede consumir más que la cantidad reservada.');
            }

            $inventory = (new RawMaterialInventoryRepository())->find((int) $material['raw_material_inventory_id']);
            if (!$inventory) {
                throw new InvalidArgumentException('Insumo no encontrado para consumo.');
            }

            $quantity = (float) $material['reserved_quantity'];
            if ((float) $inventory['quantity_available'] < $quantity - 0.0001) {
                throw new InvalidArgumentException('El consumo dejaría cantidad disponible negativa para ' . (string) $inventory['internal_code'] . '.');
            }
            if ((float) $inventory['quantity_reserved'] < $quantity - 0.0001) {
                throw new InvalidArgumentException('El consumo supera la cantidad reservada de ' . (string) $inventory['internal_code'] . '.');
            }

            $this->execute(
                'UPDATE raw_material_inventory
                 SET quantity_available = quantity_available - :quantity,
                     quantity_reserved = quantity_reserved - :quantity,
                     status = CASE WHEN quantity_available - :quantity <= 0.0001 THEN "depleted" ELSE status END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $inventory['id'], 'quantity' => $quantity]
            );
            $this->execute(
                'UPDATE raw_material_reservations
                 SET status = "consumed", consumed_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $reservation['id']]
            );
            $this->execute(
                'UPDATE cutting_order_materials
                 SET consumed_quantity = :quantity,
                     status = "consumed",
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $material['id'], 'quantity' => $quantity]
            );
        }
    }

    private function reservedStockCheckId(int $productionOrderId): ?int
    {
        $row = $this->fetchOne(
            'SELECT stock_check_items.stock_check_id
             FROM raw_material_reservations
             INNER JOIN stock_check_items ON stock_check_items.id = raw_material_reservations.stock_check_item_id
             WHERE raw_material_reservations.production_order_id = :id
               AND raw_material_reservations.status = "reserved"
             ORDER BY raw_material_reservations.id DESC
             LIMIT 1',
            ['id' => $productionOrderId]
        );

        return $row ? (int) $row['stock_check_id'] : null;
    }

    private function requiresExternalWork(int $cuttingOrderId): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM cutting_order_items
             INNER JOIN production_order_items ON production_order_items.id = cutting_order_items.production_order_item_id
             WHERE cutting_order_items.cutting_order_id = :id
               AND (production_order_items.requires_embroidery = 1 OR production_order_items.requires_screen_printing = 1)',
            ['id' => $cuttingOrderId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    private function nextCuttingNumber(int $productionOrderId): string
    {
        $row = $this->fetchOne(
            'SELECT production_number FROM production_orders WHERE id = :id',
            ['id' => $productionOrderId]
        );
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM cutting_orders WHERE production_order_id = :id',
            ['id' => $productionOrderId]
        )['total'] ?? 0);

        return sprintf('CUT-%s-%02d', (string) ($row['production_number'] ?? $productionOrderId), $count + 1);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
