<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class StockCheckRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                stock_checks.*,
                production_orders.production_number,
                production_orders.status AS production_order_status,
                production_orders.production_stage,
                clients.name AS client_name
             FROM stock_checks
             INNER JOIN production_orders ON production_orders.id = stock_checks.production_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             WHERE stock_checks.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithItems(int $id): ?array
    {
        $check = $this->find($id);
        if (!$check) {
            return null;
        }

        $check['items'] = $this->items($id);
        return $check;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byProductionOrder(int $productionOrderId): array
    {
        return $this->fetchAll(
            'SELECT * FROM stock_checks WHERE production_order_id = :id ORDER BY id DESC',
            ['id' => $productionOrderId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $stockCheckId): array
    {
        return $this->fetchAll(
            'SELECT
                stock_check_items.*,
                raw_material_inventory.internal_code,
                raw_material_inventory.description AS inventory_description
             FROM stock_check_items
             LEFT JOIN raw_material_inventory ON raw_material_inventory.id = stock_check_items.raw_material_inventory_id
             WHERE stock_check_items.stock_check_id = :id
             ORDER BY stock_check_items.id',
            ['id' => $stockCheckId]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createForProductionOrder(int $productionOrderId, array $data, array $items = []): int
    {
        $order = (new ProductionOrderRepository())->findWithItems($productionOrderId);
        if (!$order) {
            throw new InvalidArgumentException('Orden de producción no encontrada.');
        }
        if ((string) $order['status'] !== 'confirmed') {
            throw new InvalidArgumentException('Solo se puede verificar stock desde una orden de producción confirmada.');
        }
        if (in_array((string) $order['production_stage'], ['pending'], true)) {
            throw new InvalidArgumentException('La orden de producción todavía no está lista para verificar stock.');
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $checkNumber = trim((string) ($data['check_number'] ?? ''));
            if ($checkNumber === '') {
                $checkNumber = $this->nextCheckNumber($productionOrderId);
            }

            $this->execute(
                'INSERT INTO stock_checks (
                    production_order_id, check_number, status, checked_by, checked_at, notes, updated_at
                ) VALUES (
                    :production_order_id, :check_number, "draft", :checked_by, CURRENT_TIMESTAMP, :notes, CURRENT_TIMESTAMP
                )',
                [
                    'production_order_id' => $productionOrderId,
                    'check_number' => $checkNumber,
                    'checked_by' => Auth::id(),
                    'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                ]
            );

            $stockCheckId = (int) $pdo->lastInsertId();
            $selectedMaterials = $this->selectedMaterials($items);
            foreach ($order['items'] as $orderItem) {
                $materialId = $selectedMaterials[(int) $orderItem['id']] ?? $this->defaultMaterialForItem($orderItem);
                $this->insertCheckItem($stockCheckId, $orderItem, $materialId);
            }

            $status = $this->recalculateStatus($stockCheckId);
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = :stage, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'id' => $productionOrderId,
                    'stage' => $status === 'insufficient' ? 'stock_pending' : 'ready_for_stock_check',
                ]
            );

            $pdo->commit();
            return $stockCheckId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function reserve(int $stockCheckId): void
    {
        $check = $this->findWithItems($stockCheckId);
        if (!$check) {
            throw new InvalidArgumentException('Verificación de stock no encontrada.');
        }
        if ((string) $check['production_order_status'] !== 'confirmed') {
            throw new InvalidArgumentException('Solo se puede reservar stock para una orden confirmada.');
        }
        if ((string) $check['status'] === 'reserved') {
            throw new InvalidArgumentException('Esta verificación ya tiene reserva aplicada.');
        }
        if (in_array((string) $check['status'], ['cancelled', 'closed'], true)) {
            throw new InvalidArgumentException('No se puede reservar una verificación cerrada o anulada.');
        }
        if ($this->activeReservations((int) $check['production_order_id']) !== []) {
            throw new InvalidArgumentException('La orden de producción ya tiene reservas activas.');
        }

        foreach ($check['items'] as $item) {
            if ((string) $item['status'] !== 'sufficient' || empty($item['raw_material_inventory_id'])) {
                throw new InvalidArgumentException('No se puede reservar mientras existan faltantes o ítems sin insumo.');
            }
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            foreach ($check['items'] as $item) {
                $inventory = (new RawMaterialInventoryRepository())->find((int) $item['raw_material_inventory_id']);
                if (!$inventory) {
                    throw new InvalidArgumentException('Insumo no encontrado para reservar.');
                }

                $required = (float) $item['required_quantity'];
                $available = (float) $inventory['quantity_available'] - (float) $inventory['quantity_reserved'];
                if ($required > $available + 0.0001) {
                    throw new InvalidArgumentException('Stock insuficiente para ' . (string) $inventory['internal_code'] . '.');
                }

                $this->execute(
                    'UPDATE raw_material_inventory
                     SET quantity_reserved = :quantity_reserved,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id',
                    [
                        'id' => $inventory['id'],
                        'quantity_reserved' => (float) $inventory['quantity_reserved'] + $required,
                    ]
                );

                $this->execute(
                    'INSERT INTO raw_material_reservations (
                        production_order_id, production_order_item_id, stock_check_item_id,
                        raw_material_inventory_id, reserved_quantity, status, created_by, notes
                    ) VALUES (
                        :production_order_id, :production_order_item_id, :stock_check_item_id,
                        :raw_material_inventory_id, :reserved_quantity, "reserved", :created_by, :notes
                    )',
                    [
                        'production_order_id' => $check['production_order_id'],
                        'production_order_item_id' => $item['production_order_item_id'],
                        'stock_check_item_id' => $item['id'],
                        'raw_material_inventory_id' => $inventory['id'],
                        'reserved_quantity' => $required,
                        'created_by' => Auth::id(),
                        'notes' => 'Reserva generada desde ' . (string) $check['check_number'],
                    ]
                );

                $this->execute(
                    'UPDATE stock_check_items
                     SET reserved_quantity = :quantity,
                         missing_quantity = 0,
                         status = "reserved",
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id',
                    ['id' => $item['id'], 'quantity' => $required]
                );
            }

            $this->execute(
                'UPDATE stock_checks SET status = "reserved", updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['id' => $stockCheckId]
            );
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = "ready_for_cutting", updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $check['production_order_id']]
            );

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function cancel(int $stockCheckId): void
    {
        $check = $this->find($stockCheckId);
        if (!$check) {
            throw new InvalidArgumentException('Verificación de stock no encontrada.');
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->releaseReservationsForStockCheck($stockCheckId, 'cancelled');
            $this->execute(
                'UPDATE stock_checks SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['id' => $stockCheckId]
            );
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = "stock_pending", updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = "confirmed"',
                ['id' => $check['production_order_id']]
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function releaseReservationsForProductionOrder(int $productionOrderId, string $newStatus = 'released'): void
    {
        $this->releaseReservations(
            'SELECT * FROM raw_material_reservations WHERE production_order_id = :id AND status = "reserved"',
            ['id' => $productionOrderId],
            $newStatus
        );
    }

    public function releaseReservationsForStockCheck(int $stockCheckId, string $newStatus = 'released'): void
    {
        $this->releaseReservations(
            'SELECT * FROM raw_material_reservations WHERE stock_check_item_id IN (
                SELECT id FROM stock_check_items WHERE stock_check_id = :id
            ) AND status = "reserved"',
            ['id' => $stockCheckId],
            $newStatus
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeReservations(int $productionOrderId): array
    {
        return $this->fetchAll(
            'SELECT
                raw_material_reservations.*,
                raw_material_inventory.internal_code,
                raw_material_inventory.material_type,
                raw_material_inventory.unit,
                production_order_items.item_code
             FROM raw_material_reservations
             INNER JOIN raw_material_inventory ON raw_material_inventory.id = raw_material_reservations.raw_material_inventory_id
             INNER JOIN production_order_items ON production_order_items.id = raw_material_reservations.production_order_item_id
             WHERE raw_material_reservations.production_order_id = :id
               AND raw_material_reservations.status = "reserved"
             ORDER BY raw_material_reservations.id DESC',
            ['id' => $productionOrderId]
        );
    }

    /**
     * @param array<string, mixed> $orderItem
     */
    private function insertCheckItem(int $stockCheckId, array $orderItem, ?int $materialId): void
    {
        $inventory = $materialId ? (new RawMaterialInventoryRepository())->find($materialId) : null;
        $required = (float) $orderItem['quantity'];
        $available = $inventory ? max(0.0, (float) $inventory['quantity_available'] - (float) $inventory['quantity_reserved']) : 0.0;
        $missing = max(0.0, $required - $available);
        $status = $missing > 0.0001 ? 'insufficient' : 'sufficient';

        $this->execute(
            'INSERT INTO stock_check_items (
                stock_check_id, production_order_item_id, raw_material_inventory_id,
                required_material_type, required_description, required_unit, required_quantity,
                available_quantity, reserved_quantity, missing_quantity, status, notes, updated_at
            ) VALUES (
                :stock_check_id, :production_order_item_id, :raw_material_inventory_id,
                :required_material_type, :required_description, :required_unit, :required_quantity,
                :available_quantity, 0, :missing_quantity, :status, :notes, CURRENT_TIMESTAMP
            )',
            [
                'stock_check_id' => $stockCheckId,
                'production_order_item_id' => $orderItem['id'],
                'raw_material_inventory_id' => $inventory['id'] ?? null,
                'required_material_type' => (string) ($inventory['material_type'] ?? $orderItem['product_type'] ?? 'insumo'),
                'required_description' => (string) ($inventory['description'] ?? $orderItem['description'] ?? $orderItem['item_code']),
                'required_unit' => (string) ($inventory['unit'] ?? $orderItem['unit'] ?? 'unidad'),
                'required_quantity' => $required,
                'available_quantity' => $available,
                'missing_quantity' => $missing,
                'status' => $status,
                'notes' => $inventory ? null : 'Sin insumo asociado para este ítem.',
            ]
        );
    }

    private function recalculateStatus(int $stockCheckId): string
    {
        $row = $this->fetchOne(
            'SELECT
                SUM(CASE WHEN status = "insufficient" THEN 1 ELSE 0 END) AS insufficient_count,
                COUNT(*) AS item_count
             FROM stock_check_items
             WHERE stock_check_id = :id',
            ['id' => $stockCheckId]
        );

        $status = (int) ($row['insufficient_count'] ?? 0) > 0 || (int) ($row['item_count'] ?? 0) === 0
            ? 'insufficient'
            : 'sufficient';

        $this->execute(
            'UPDATE stock_checks SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $stockCheckId, 'status' => $status]
        );

        return $status;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, int>
     */
    private function selectedMaterials(array $items): array
    {
        $selected = [];
        foreach ($items as $item) {
            $productionOrderItemId = (int) ($item['production_order_item_id'] ?? 0);
            $materialId = (int) ($item['raw_material_inventory_id'] ?? 0);
            if ($productionOrderItemId > 0 && $materialId > 0) {
                $selected[$productionOrderItemId] = $materialId;
            }
        }

        return $selected;
    }

    /**
     * @param array<string, mixed> $orderItem
     */
    private function defaultMaterialForItem(array $orderItem): ?int
    {
        $material = $this->fetchOne(
            'SELECT id
             FROM raw_material_inventory
             WHERE status = "active"
               AND (
                    related_item_code = :item_code
                    OR related_product_type = :product_type
                    OR material_type = :product_type
               )
             ORDER BY
                CASE
                    WHEN related_item_code = :item_code THEN 1
                    WHEN related_product_type = :product_type THEN 2
                    ELSE 3
                END,
                quantity_available - quantity_reserved DESC,
                id
             LIMIT 1',
            [
                'item_code' => (string) $orderItem['item_code'],
                'product_type' => (string) ($orderItem['product_type'] ?? ''),
            ]
        );

        return $material ? (int) $material['id'] : null;
    }

    private function nextCheckNumber(int $productionOrderId): string
    {
        $row = $this->fetchOne(
            'SELECT production_number FROM production_orders WHERE id = :id',
            ['id' => $productionOrderId]
        );
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM stock_checks WHERE production_order_id = :id',
            ['id' => $productionOrderId]
        )['total'] ?? 0);

        return sprintf('STK-%s-%02d', (string) ($row['production_number'] ?? $productionOrderId), $count + 1);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function releaseReservations(string $sql, array $params, string $newStatus): void
    {
        if (!in_array($newStatus, ['released', 'cancelled'], true)) {
            throw new InvalidArgumentException('Estado de liberación inválido.');
        }

        foreach ($this->fetchAll($sql, $params) as $reservation) {
            $this->execute(
                'UPDATE raw_material_inventory
                 SET quantity_reserved = MAX(quantity_reserved - :quantity, 0),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'id' => $reservation['raw_material_inventory_id'],
                    'quantity' => $reservation['reserved_quantity'],
                ]
            );
            $this->execute(
                'UPDATE raw_material_reservations
                 SET status = :status, released_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $reservation['id'], 'status' => $newStatus]
            );
            $this->execute(
                'UPDATE stock_check_items
                 SET status = "sufficient", reserved_quantity = 0, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $reservation['stock_check_item_id']]
            );
        }
    }
}
