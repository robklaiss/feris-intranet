<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use InvalidArgumentException;
use RuntimeException;

final class FinishedGoodsInventoryRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    finished_goods_inventory.*,
                    (finished_goods_inventory.quantity_available - finished_goods_inventory.quantity_reserved) AS real_available,
                    packaging_orders.packaging_number,
                    production_orders.production_number,
                    clients.name AS client_name,
                    contracts.contract_number,
                    client_dependencies.name AS dependency_name
                FROM finished_goods_inventory
                INNER JOIN packaging_orders ON packaging_orders.id = finished_goods_inventory.packaging_order_id
                INNER JOIN production_orders ON production_orders.id = finished_goods_inventory.production_order_id
                INNER JOIN clients ON clients.id = finished_goods_inventory.client_id
                INNER JOIN contracts ON contracts.id = finished_goods_inventory.contract_id
                LEFT JOIN client_dependencies ON client_dependencies.id = finished_goods_inventory.dependency_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                finished_goods_inventory.internal_code LIKE :q
                OR finished_goods_inventory.package_code LIKE :q
                OR finished_goods_inventory.item_code LIKE :q
                OR finished_goods_inventory.description LIKE :q
                OR finished_goods_inventory.label LIKE :q
                OR clients.name LIKE :q
                OR clients.tax_id LIKE :q
                OR contracts.contract_number LIKE :q
                OR contracts.reference_number LIKE :q
                OR client_dependencies.name LIKE :q
                OR production_orders.production_number LIKE :q
                OR packaging_orders.packaging_number LIKE :q
                OR EXISTS (
                    SELECT 1
                    FROM remission_items
                    INNER JOIN remissions ON remissions.id = remission_items.remission_id
                    WHERE remission_items.finished_goods_inventory_id = finished_goods_inventory.id
                      AND (
                        remissions.remission_number LIKE :q
                        OR remissions.reference_number LIKE :q
                      )
                )
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'client_id', 'contract_id', 'dependency_id', 'packaging_order_id', 'item_code', 'size', 'color', 'label', 'location'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND finished_goods_inventory.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        if (!empty($filters['available_only'])) {
            $sql .= ' AND finished_goods_inventory.status IN ("available", "reserved")
                      AND (finished_goods_inventory.quantity_available - finished_goods_inventory.quantity_reserved) > 0.0001';
        }

        $sql .= ' ORDER BY finished_goods_inventory.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                finished_goods_inventory.*,
                (finished_goods_inventory.quantity_available - finished_goods_inventory.quantity_reserved) AS real_available,
                packaging_orders.packaging_number,
                quality_control_checks.id AS quality_control_check_id,
                quality_control_checks.qc_number,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name
             FROM finished_goods_inventory
             INNER JOIN packaging_orders ON packaging_orders.id = finished_goods_inventory.packaging_order_id
             INNER JOIN quality_control_checks ON quality_control_checks.id = packaging_orders.quality_control_check_id
             INNER JOIN production_orders ON production_orders.id = finished_goods_inventory.production_order_id
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             INNER JOIN clients ON clients.id = finished_goods_inventory.client_id
             INNER JOIN contracts ON contracts.id = finished_goods_inventory.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = finished_goods_inventory.dependency_id
             WHERE finished_goods_inventory.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byPackagingOrder(int $packagingOrderId): array
    {
        return $this->search(['packaging_order_id' => $packagingOrderId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byProductionOrder(int $productionOrderId): array
    {
        return $this->fetchAll(
            'SELECT finished_goods_inventory.*, packaging_orders.packaging_number
             FROM finished_goods_inventory
             INNER JOIN packaging_orders ON packaging_orders.id = finished_goods_inventory.packaging_order_id
             WHERE finished_goods_inventory.production_order_id = :id
             ORDER BY finished_goods_inventory.id DESC',
            ['id' => $productionOrderId]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function availableForRemission(array $filters = []): array
    {
        $filters['available_only'] = true;
        return $this->search($filters);
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function availableByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->fetchAll(
            "SELECT
                finished_goods_inventory.*,
                (finished_goods_inventory.quantity_available - finished_goods_inventory.quantity_reserved) AS real_available,
                packaging_orders.packaging_number,
                quality_control_checks.id AS quality_control_check_id,
                quality_control_checks.qc_number,
                production_orders.production_number,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name,
                COALESCE(
                    (SELECT contract_items.unit_price
                     FROM contract_items
                     WHERE contract_items.contract_id = finished_goods_inventory.contract_id
                     ORDER BY contract_items.id
                     LIMIT 1),
                    0
                ) AS unit_price
             FROM finished_goods_inventory
             INNER JOIN packaging_orders ON packaging_orders.id = finished_goods_inventory.packaging_order_id
             INNER JOIN quality_control_checks ON quality_control_checks.id = packaging_orders.quality_control_check_id
             INNER JOIN production_orders ON production_orders.id = finished_goods_inventory.production_order_id
             INNER JOIN clients ON clients.id = finished_goods_inventory.client_id
             INNER JOIN contracts ON contracts.id = finished_goods_inventory.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = finished_goods_inventory.dependency_id
             WHERE finished_goods_inventory.id IN ($placeholders)
             ORDER BY finished_goods_inventory.id",
            $ids
        );
    }

    /**
     * @param array<int, int> $ids
     * @param array<int, float> $quantities
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<string, mixed>}
     */
    public function prepareRemissionItems(array $ids, array $quantities = []): array
    {
        $rows = $this->availableByIds($ids);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $errors = [];
        foreach ($ids as $id) {
            if ($id > 0 && !isset($byId[$id])) {
                $errors[] = 'Se detectó inventario terminado no disponible o inexistente.';
            }
        }

        $clientIds = [];
        $contractIds = [];
        $items = [];

        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                continue;
            }

            $inventory = $byId[$id];
            $clientIds[(string) $inventory['client_id']] = true;
            $contractIds[(string) $inventory['contract_id']] = true;

            $available = (float) $inventory['real_available'];
            $quantity = $quantities[$id] ?? $available;

            if (!in_array((string) $inventory['status'], ['available', 'reserved'], true)) {
                $errors[] = 'Solo inventario disponible o reservado puede alimentar una remisión.';
                continue;
            }

            if ($quantity <= 0) {
                $errors[] = 'La cantidad a remitir debe ser mayor a cero.';
                continue;
            }

            if ($quantity - $available > 0.0001) {
                $errors[] = 'No se puede remitir más que la disponibilidad real del inventario terminado.';
                continue;
            }

            $productName = trim((string) ($inventory['description'] ?: $inventory['product_type'] ?: $inventory['item_code']));
            $unitPrice = (float) ($inventory['unit_price'] ?? 0);
            $items[] = [
                'finished_goods_inventory_id' => (int) $inventory['id'],
                'packaging_order_id' => (int) $inventory['packaging_order_id'],
                'production_order_id' => (int) $inventory['production_order_id'],
                'contract_item_spec_id' => $inventory['contract_item_spec_id'] ?: null,
                'item_code' => $inventory['item_code'],
                'product_type' => $inventory['product_type'],
                'description' => $inventory['description'],
                'size' => $inventory['size'],
                'color' => $inventory['color'],
                'label' => $inventory['label'],
                'product_name' => $productName,
                'unit_measure' => $inventory['unit'] ?: 'unidad',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_item' => $quantity * $unitPrice,
                'quantity_available_before' => (float) $inventory['quantity_available'],
                'quantity_available_after' => null,
                'real_available' => $available,
                'client_id' => (int) $inventory['client_id'],
                'contract_id' => (int) $inventory['contract_id'],
                'dependency_id' => $inventory['dependency_id'] ?: null,
                'internal_code' => $inventory['internal_code'],
                'packaging_number' => $inventory['packaging_number'],
                'production_number' => $inventory['production_number'],
                'qc_number' => $inventory['qc_number'],
            ];
        }

        if (count($clientIds) > 1) {
            $errors[] = 'No se pueden mezclar inventarios de distinto cliente en una misma remisión.';
        }

        if (count($contractIds) > 1) {
            $errors[] = 'No se pueden mezclar inventarios de distinto contrato en una misma remisión.';
        }

        $first = $rows[0] ?? null;
        return [
            $items,
            array_values(array_unique($errors)),
            [
                'document' => [
                    'client_id' => $first['client_id'] ?? null,
                    'client_name' => $first['client_name'] ?? '',
                    'contract_id' => $first['contract_id'] ?? null,
                    'contract_number' => $first['contract_number'] ?? '',
                    'dependency_id' => $first['dependency_id'] ?? null,
                    'dependency_name' => $first['dependency_name'] ?? '',
                    'source_finished_goods' => $rows,
                ],
                'items' => $items,
            ],
        ];
    }

    public function hasRemissionConsumption(int $remissionId): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM remission_items
             WHERE remission_id = :id
               AND finished_goods_inventory_id IS NOT NULL',
            ['id' => $remissionId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function hasConsumedRemissionInventory(int $remissionId): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM remission_items
             WHERE remission_id = :id
               AND finished_goods_inventory_id IS NOT NULL
               AND quantity_available_after IS NOT NULL',
            ['id' => $remissionId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function consumeForRemission(int $remissionId): void
    {
        $items = $this->fetchAll(
            'SELECT
                remission_items.*,
                remissions.remission_number,
                remissions.client_id AS remission_client_id,
                remissions.contract_id AS remission_contract_id,
                finished_goods_inventory.client_id AS inventory_client_id,
                finished_goods_inventory.contract_id AS inventory_contract_id,
                finished_goods_inventory.dependency_id,
                finished_goods_inventory.quantity_available,
                finished_goods_inventory.quantity_reserved,
                finished_goods_inventory.quantity_remitted,
                finished_goods_inventory.status AS inventory_status
             FROM remission_items
             INNER JOIN remissions ON remissions.id = remission_items.remission_id
             INNER JOIN finished_goods_inventory ON finished_goods_inventory.id = remission_items.finished_goods_inventory_id
             WHERE remission_items.remission_id = :id
               AND remission_items.finished_goods_inventory_id IS NOT NULL
             ORDER BY remission_items.id',
            ['id' => $remissionId]
        );

        foreach ($items as $item) {
            $this->consumeItem($item);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function consumeItem(array $item): void
    {
        $quantity = (float) $item['quantity'];
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad a remitir debe ser mayor a cero.');
        }

        if ((int) $item['remission_client_id'] !== (int) $item['inventory_client_id']
            || (int) $item['remission_contract_id'] !== (int) $item['inventory_contract_id']) {
            throw new InvalidArgumentException('El cliente y contrato de la remisión deben coincidir con el inventario terminado.');
        }

        if (!in_array((string) $item['inventory_status'], ['available', 'reserved'], true)) {
            throw new InvalidArgumentException('No se puede remitir inventario terminado en estado ' . (string) $item['inventory_status'] . '.');
        }

        $availableBefore = (float) $item['quantity_available'];
        $reservedBefore = (float) $item['quantity_reserved'];
        $realAvailable = $availableBefore - $reservedBefore;
        if ($quantity - $realAvailable > 0.0001) {
            throw new InvalidArgumentException('No hay disponibilidad suficiente en inventario terminado.');
        }

        $availableAfter = $availableBefore - $quantity;
        if ($availableAfter < -0.0001) {
            throw new RuntimeException('La disponibilidad de inventario terminado no puede quedar negativa.');
        }

        $nextStatus = $availableAfter <= 0.0001 ? 'remitted' : 'available';
        $statement = $this->db()->prepare(
             'UPDATE finished_goods_inventory
             SET quantity_available = quantity_available - CAST(:available_decrement AS NUMERIC),
                 quantity_remitted = quantity_remitted + CAST(:remitted_increment AS NUMERIC),
                 status = CASE
                    WHEN quantity_available - CAST(:status_quantity AS NUMERIC) <= 0.0001 THEN "remitted"
                    ELSE "available"
                 END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status IN ("available", "reserved")
               AND quantity_available - quantity_reserved >= CAST(:available_quantity AS NUMERIC)'
        );
        $statement->execute([
            'id' => (int) $item['finished_goods_inventory_id'],
            'available_decrement' => $quantity,
            'remitted_increment' => $quantity,
            'status_quantity' => $quantity,
            'available_quantity' => $quantity,
        ]);

        $updatedInventory = $this->fetchOne(
            'SELECT quantity_available, quantity_remitted
             FROM finished_goods_inventory
             WHERE id = :id',
            ['id' => (int) $item['finished_goods_inventory_id']]
        );
        $updatedAvailable = (float) ($updatedInventory['quantity_available'] ?? $availableBefore);
        $updatedRemitted = (float) ($updatedInventory['quantity_remitted'] ?? $item['quantity_remitted']);
        $expectedRemitted = (float) $item['quantity_remitted'] + $quantity;
        if (abs($updatedAvailable - max(0, $availableAfter)) > 0.0001 || abs($updatedRemitted - $expectedRemitted) > 0.0001) {
            throw new RuntimeException(sprintf(
                'No se pudo consumir inventario terminado. id=%d status=%s available=%s reserved=%s quantity=%s',
                (int) $item['finished_goods_inventory_id'],
                (string) $item['inventory_status'],
                (string) $item['quantity_available'],
                (string) $item['quantity_reserved'],
                (string) $quantity
            ));
        }

        $this->execute(
            'UPDATE remission_items
             SET quantity_available_before = :before,
                 quantity_available_after = :after,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'id' => (int) $item['id'],
                'before' => $availableBefore,
                'after' => max(0, $availableAfter),
            ]
        );

        $this->auditConsumption($item, $availableBefore, max(0, $availableAfter), $nextStatus);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function auditConsumption(array $item, float $before, float $after, string $nextStatus): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'remissions',
            (int) $item['remission_id'],
            (string) $item['remission_number'],
            'consume_finished_goods_inventory',
            (string) $item['inventory_status'],
            $nextStatus,
            [
                'remission_id' => (int) $item['remission_id'],
                'remission_number' => $item['remission_number'],
                'finished_goods_inventory_id' => (int) $item['finished_goods_inventory_id'],
                'packaging_order_id' => $item['packaging_order_id'],
                'production_order_id' => $item['production_order_id'],
                'contract_id' => $item['inventory_contract_id'],
                'client_id' => $item['inventory_client_id'],
                'dependency_id' => $item['dependency_id'],
                'item_code' => $item['item_code'],
                'quantity' => (float) $item['quantity'],
                'quantity_available_before' => $before,
                'quantity_available_after' => $after,
                'quantity_remitted' => (float) $item['quantity_remitted'] + (float) $item['quantity'],
                'status_previous' => $item['inventory_status'],
                'status_new' => $nextStatus,
            ]
        );
    }
}
