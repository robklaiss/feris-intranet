<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class PackagingOrderRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    packaging_orders.*,
                    quality_control_checks.qc_number,
                    production_orders.production_number,
                    clients.name AS client_name,
                    contracts.contract_number,
                    packed_user.full_name AS packed_by_name
                FROM packaging_orders
                INNER JOIN quality_control_checks ON quality_control_checks.id = packaging_orders.quality_control_check_id
                INNER JOIN production_orders ON production_orders.id = packaging_orders.production_order_id
                LEFT JOIN clients ON clients.id = production_orders.client_id
                LEFT JOIN contracts ON contracts.id = production_orders.contract_id
                LEFT JOIN users AS packed_user ON packed_user.id = packaging_orders.packed_by
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                packaging_orders.packaging_number LIKE :q
                OR quality_control_checks.qc_number LIKE :q
                OR production_orders.production_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'production_order_id', 'quality_control_check_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND packaging_orders.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY packaging_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                packaging_orders.*,
                quality_control_checks.qc_number,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                production_orders.contract_id,
                production_orders.client_id,
                production_orders.dependency_id,
                production_orders.production_stage,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name,
                packed_user.full_name AS packed_by_name
             FROM packaging_orders
             INNER JOIN quality_control_checks ON quality_control_checks.id = packaging_orders.quality_control_check_id
             INNER JOIN production_orders ON production_orders.id = packaging_orders.production_order_id
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = production_orders.dependency_id
             LEFT JOIN users AS packed_user ON packed_user.id = packaging_orders.packed_by
             WHERE packaging_orders.id = :id',
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
        $order['inventory'] = $this->inventory($id);

        return $order;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $packagingOrderId): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM packaging_order_items
             WHERE packaging_order_id = :id
             ORDER BY id',
            ['id' => $packagingOrderId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inventory(int $packagingOrderId): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM finished_goods_inventory
             WHERE packaging_order_id = :id
             ORDER BY id',
            ['id' => $packagingOrderId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDraftContextFromQualityControl(int $qualityControlCheckId): array
    {
        $check = $this->qualityControlContext($qualityControlCheckId);
        if (!$check) {
            throw new InvalidArgumentException('Control de calidad no encontrado.');
        }
        if (!in_array((string) $check['status'], ['approved', 'partially_approved'], true)) {
            throw new InvalidArgumentException('Solo se puede crear empaquetado desde calidad approved o partially_approved.');
        }

        $check['items'] = $this->availableItemsFromQualityControl($qualityControlCheckId);
        $check['packaging_orders'] = $this->search(['quality_control_check_id' => $qualityControlCheckId]);

        if ($check['items'] === []) {
            throw new InvalidArgumentException('El control de calidad no tiene saldo aprobado pendiente para empaquetar.');
        }

        return $check;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableItemsFromQualityControl(int $qualityControlCheckId): array
    {
        $check = $this->qualityControlContext($qualityControlCheckId);
        if (!$check || !in_array((string) $check['status'], ['approved', 'partially_approved'], true)) {
            return [];
        }

        $items = $this->fetchAll(
            'SELECT
                quality_control_check_items.*,
                production_order_items.unit,
                production_orders.production_number,
                production_orders.contract_id,
                production_orders.client_id,
                production_orders.dependency_id,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name
             FROM quality_control_check_items
             INNER JOIN quality_control_checks ON quality_control_checks.id = quality_control_check_items.quality_control_check_id
             INNER JOIN production_order_items ON production_order_items.id = quality_control_check_items.production_order_item_id
             INNER JOIN production_orders ON production_orders.id = quality_control_checks.production_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = production_orders.dependency_id
             WHERE quality_control_check_items.quality_control_check_id = :id
               AND quality_control_check_items.quantity_approved > 0
             ORDER BY quality_control_check_items.id',
            ['id' => $qualityControlCheckId]
        );

        foreach ($items as &$item) {
            $reserved = $this->reservedQuantityForQualityItem((int) $item['id']);
            $item['quantity_already_packaged'] = $reserved;
            $item['quantity_available_to_pack'] = max(0.0, (float) $item['quantity_approved'] - $reserved);
        }
        unset($item);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => (float) $item['quantity_available_to_pack'] > 0.0001
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createFromQualityControl(int $qualityControlCheckId, array $data, array $items): int
    {
        $check = $this->buildDraftContextFromQualityControl($qualityControlCheckId);
        $normalized = $this->normalizeItems($qualityControlCheckId, $items);
        $packagingNumber = trim((string) ($data['packaging_number'] ?? ''));
        if ($packagingNumber === '') {
            $packagingNumber = $this->nextPackagingNumber((int) $check['production_order_id']);
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO packaging_orders (
                    production_order_id, quality_control_check_id, packaging_number, status, notes, created_by, updated_at
                ) VALUES (
                    :production_order_id, :quality_control_check_id, :packaging_number, "draft", :notes, :created_by, CURRENT_TIMESTAMP
                )',
                [
                    'production_order_id' => $check['production_order_id'],
                    'quality_control_check_id' => $qualityControlCheckId,
                    'packaging_number' => $packagingNumber,
                    'notes' => $this->nullable($data['notes'] ?? null),
                    'created_by' => Auth::id(),
                ]
            );
            $id = (int) $pdo->lastInsertId();
            foreach ($normalized as $item) {
                $this->insertItem($id, $item);
            }
            $pdo->commit();
            $this->audit('create_packaging_order', null, 'draft', $this->findWithDetails($id), $id);

            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    public function pack(int $id, array $entries, ?string $location = null): void
    {
        $order = $this->findWithDetails($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de empaquetado no encontrada.');
        }
        if (!in_array((string) $order['status'], ['draft', 'confirmed'], true)) {
            throw new InvalidArgumentException('Solo se puede empacar una orden draft o confirmed.');
        }
        if (($order['items'] ?? []) === []) {
            throw new InvalidArgumentException('La orden de empaquetado no tiene ítems.');
        }

        $normalized = $this->normalizePackEntries($order, $entries);
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            foreach ($normalized as $entry) {
                $status = abs((float) $entry['quantity_packed'] - (float) $entry['item']['quantity_to_pack']) < 0.0001 ? 'packed' : 'partial';
                $packageCode = $entry['package_code'] ?: $this->defaultPackageCode((string) $order['packaging_number'], (int) $entry['item']['id']);
                $this->execute(
                    'UPDATE packaging_order_items
                     SET quantity_packed = :quantity_packed,
                         package_code = :package_code,
                         status = :status,
                         notes = COALESCE(:notes, notes),
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id',
                    [
                        'id' => $entry['item']['id'],
                        'quantity_packed' => $entry['quantity_packed'],
                        'package_code' => $packageCode,
                        'status' => $status,
                        'notes' => $this->nullable($entry['notes'] ?? null),
                    ]
                );
            }

            $this->execute(
                'UPDATE packaging_orders
                 SET status = "packed",
                     packed_by = :user_id,
                     packed_at = CURRENT_TIMESTAMP,
                     confirmed_by = COALESCE(confirmed_by, :user_id),
                     confirmed_at = COALESCE(confirmed_at, CURRENT_TIMESTAMP),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $id, 'user_id' => Auth::id()]
            );
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = "packaging", updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = "confirmed"',
                ['id' => $order['production_order_id']]
            );

            foreach ($normalized as $entry) {
                $fresh = $this->fetchOne('SELECT * FROM packaging_order_items WHERE id = :id', ['id' => $entry['item']['id']]);
                if (!$fresh) {
                    continue;
                }
                $this->createInventoryRow($order, $fresh, $this->nullable($location));
            }

            $pdo->commit();
            $this->audit('pack_packaging_order', (string) $order['status'], 'packed', $this->findWithDetails($id), $id);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancelDraft(int $id): void
    {
        $order = $this->findWithDetails($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de empaquetado no encontrada.');
        }
        if ((string) $order['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede cancelar empaquetado draft.');
        }
        if (($order['inventory'] ?? []) !== []) {
            throw new InvalidArgumentException('No se puede cancelar empaquetado que ya generó inventario terminado.');
        }

        $this->execute(
            'UPDATE packaging_orders
             SET status = "cancelled",
                 cancelled_by = :user_id,
                 cancelled_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->execute(
            'UPDATE packaging_order_items
             SET status = "cancelled", updated_at = CURRENT_TIMESTAMP
             WHERE packaging_order_id = :id',
            ['id' => $id]
        );
        $this->audit('cancel_packaging_order', (string) $order['status'], 'cancelled', $this->findWithDetails($id), $id);
    }

    public function close(int $id): void
    {
        $order = $this->findWithDetails($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de empaquetado no encontrada.');
        }
        if ((string) $order['status'] !== 'packed') {
            throw new InvalidArgumentException('Solo se puede cerrar empaquetado packed.');
        }

        $this->execute(
            'UPDATE packaging_orders
             SET status = "closed",
                 closed_by = :user_id,
                 closed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->audit('close_packaging_order', (string) $order['status'], 'closed', $this->findWithDetails($id), $id);
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
    public function byQualityControl(int $qualityControlCheckId): array
    {
        return $this->search(['quality_control_check_id' => $qualityControlCheckId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function qualityControlContext(int $qualityControlCheckId): ?array
    {
        return $this->fetchOne(
            'SELECT
                quality_control_checks.*,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                production_orders.contract_id,
                production_orders.client_id,
                production_orders.dependency_id,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name
             FROM quality_control_checks
             INNER JOIN production_orders ON production_orders.id = quality_control_checks.production_order_id
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = production_orders.dependency_id
             WHERE quality_control_checks.id = :id',
            ['id' => $qualityControlCheckId]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(int $qualityControlCheckId, array $items): array
    {
        $available = [];
        foreach ($this->availableItemsFromQualityControl($qualityControlCheckId) as $item) {
            $available[(int) $item['id']] = $item;
        }

        $normalized = [];
        foreach ($items as $row) {
            $qualityItemId = (int) ($row['quality_control_check_item_id'] ?? 0);
            $quantity = (float) ($row['quantity_to_pack'] ?? 0);
            if ($qualityItemId <= 0 || $quantity <= 0.0001) {
                continue;
            }
            if (!isset($available[$qualityItemId])) {
                throw new InvalidArgumentException('Ítem de calidad sin saldo aprobado disponible.');
            }
            $source = $available[$qualityItemId];
            if ($quantity - (float) $source['quantity_available_to_pack'] > 0.0001) {
                throw new InvalidArgumentException('No se puede empaquetar por encima del saldo aprobado pendiente.');
            }
            $normalized[] = [
                'source' => $source,
                'quantity_to_pack' => $quantity,
                'label' => trim((string) ($row['label'] ?? ($source['label'] ?? ''))),
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Debe seleccionar al menos un ítem aprobado para empaquetar.');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function insertItem(int $packagingOrderId, array $item): void
    {
        $source = $item['source'];
        $this->execute(
            'INSERT INTO packaging_order_items (
                packaging_order_id, quality_control_check_item_id, production_order_item_id, contract_item_spec_id,
                item_code, product_type, description, size, color, quantity_approved, quantity_to_pack,
                quantity_packed, unit, label, package_code, status, notes, updated_at
             ) VALUES (
                :packaging_order_id, :quality_control_check_item_id, :production_order_item_id, :contract_item_spec_id,
                :item_code, :product_type, :description, :size, :color, :quantity_approved, :quantity_to_pack,
                0, :unit, :label, NULL, "pending", :notes, CURRENT_TIMESTAMP
             )',
            [
                'packaging_order_id' => $packagingOrderId,
                'quality_control_check_item_id' => $source['id'],
                'production_order_item_id' => $source['production_order_item_id'],
                'contract_item_spec_id' => $source['contract_item_spec_id'],
                'item_code' => $source['item_code'],
                'product_type' => $source['product_type'],
                'description' => $source['description'],
                'size' => $source['size'],
                'color' => $source['color'],
                'quantity_approved' => $source['quantity_approved'],
                'quantity_to_pack' => $item['quantity_to_pack'],
                'unit' => $source['unit'],
                'label' => $this->nullable($item['label'] ?? null),
                'notes' => $this->nullable($item['notes'] ?? null),
            ]
        );
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function normalizePackEntries(array $order, array $entries): array
    {
        $byId = [];
        foreach (($order['items'] ?? []) as $item) {
            $byId[(int) $item['id']] = $item;
        }

        $normalized = [];
        foreach ($entries as $entry) {
            $itemId = (int) ($entry['packaging_order_item_id'] ?? 0);
            $quantityPacked = (float) ($entry['quantity_packed'] ?? 0);
            if ($itemId <= 0 || $quantityPacked <= 0.0001) {
                continue;
            }
            if (!isset($byId[$itemId])) {
                throw new InvalidArgumentException('Ítem de empaquetado inválido.');
            }
            $item = $byId[$itemId];
            if ($quantityPacked - (float) $item['quantity_to_pack'] > 0.0001) {
                throw new InvalidArgumentException('La cantidad empacada no puede superar quantity_to_pack.');
            }
            $available = (float) $item['quantity_approved'] - $this->reservedQuantityForQualityItem((int) $item['quality_control_check_item_id'], (int) $order['id']);
            if ($quantityPacked - $available > 0.0001) {
                throw new InvalidArgumentException('La cantidad empacada supera el saldo aprobado disponible.');
            }
            $normalized[] = [
                'item' => $item,
                'quantity_packed' => $quantityPacked,
                'package_code' => trim((string) ($entry['package_code'] ?? '')),
                'notes' => trim((string) ($entry['notes'] ?? '')),
            ];
        }

        if (count($normalized) !== count($byId)) {
            throw new InvalidArgumentException('Todos los ítems deben tener quantity_packed mayor a cero al empacar.');
        }

        return $normalized;
    }

    private function reservedQuantityForQualityItem(int $qualityControlCheckItemId, ?int $excludePackagingOrderId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(
                    CASE
                        WHEN packaging_orders.status IN ("draft", "confirmed") THEN packaging_order_items.quantity_to_pack
                        ELSE packaging_order_items.quantity_packed
                    END
                ), 0) AS total
                FROM packaging_order_items
                INNER JOIN packaging_orders ON packaging_orders.id = packaging_order_items.packaging_order_id
                WHERE packaging_order_items.quality_control_check_item_id = :id
                  AND packaging_orders.status IN ("draft", "confirmed", "packed", "closed")
                  AND packaging_order_items.status <> "cancelled"';
        $params = ['id' => $qualityControlCheckItemId];
        if ($excludePackagingOrderId !== null) {
            $sql .= ' AND packaging_orders.id <> :exclude_id';
            $params['exclude_id'] = $excludePackagingOrderId;
        }

        $row = $this->fetchOne($sql, $params);

        return (float) ($row['total'] ?? 0);
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $item
     */
    private function createInventoryRow(array $order, array $item, ?string $location): void
    {
        if ((float) $item['quantity_packed'] <= 0.0001) {
            throw new InvalidArgumentException('No se puede crear inventario terminado sin cantidad empacada.');
        }

        $this->execute(
            'INSERT INTO finished_goods_inventory (
                internal_code, packaging_order_id, packaging_order_item_id, production_order_id,
                contract_id, client_id, dependency_id, contract_item_spec_id, item_code, product_type,
                description, size, color, quantity_available, quantity_reserved, quantity_remitted,
                unit, label, package_code, location, status, notes, updated_at
             ) VALUES (
                :internal_code, :packaging_order_id, :packaging_order_item_id, :production_order_id,
                :contract_id, :client_id, :dependency_id, :contract_item_spec_id, :item_code, :product_type,
                :description, :size, :color, :quantity_available, 0, 0,
                :unit, :label, :package_code, :location, "available", :notes, CURRENT_TIMESTAMP
             )',
            [
                'internal_code' => $this->nextInternalCode((string) $order['packaging_number'], (int) $item['id']),
                'packaging_order_id' => $order['id'],
                'packaging_order_item_id' => $item['id'],
                'production_order_id' => $order['production_order_id'],
                'contract_id' => $order['contract_id'],
                'client_id' => $order['client_id'],
                'dependency_id' => $order['dependency_id'],
                'contract_item_spec_id' => $item['contract_item_spec_id'],
                'item_code' => $item['item_code'],
                'product_type' => $item['product_type'],
                'description' => $item['description'],
                'size' => $item['size'],
                'color' => $item['color'],
                'quantity_available' => $item['quantity_packed'],
                'unit' => $item['unit'],
                'label' => $item['label'],
                'package_code' => $item['package_code'],
                'location' => $location,
                'notes' => $item['notes'],
            ]
        );
    }

    private function nextPackagingNumber(int $productionOrderId): string
    {
        $row = $this->fetchOne('SELECT production_number FROM production_orders WHERE id = :id', ['id' => $productionOrderId]);
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM packaging_orders WHERE production_order_id = :id',
            ['id' => $productionOrderId]
        )['total'] ?? 0);

        return sprintf('EMP-%s-%02d', (string) ($row['production_number'] ?? $productionOrderId), $count + 1);
    }

    private function defaultPackageCode(string $packagingNumber, int $itemId): string
    {
        return sprintf('PKG-%s-%02d', $packagingNumber, $itemId);
    }

    private function nextInternalCode(string $packagingNumber, int $itemId): string
    {
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', 'PT-' . $packagingNumber . '-' . $itemId) ?: ('PT-' . $itemId);
        $code = trim($base, '-');
        $suffix = 1;
        while ($this->fetchOne('SELECT id FROM finished_goods_inventory WHERE internal_code = :code', ['code' => $code])) {
            $suffix++;
            $code = trim($base, '-') . '-' . $suffix;
        }

        return $code;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $order
     */
    private function audit(string $action, ?string $previous, string $next, ?array $order, int $packagingOrderId): void
    {
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $firstItem = $items[0] ?? [];

        (new OperationalAuditService())->logDocumentAction(
            'packaging_orders',
            $packagingOrderId,
            (string) ($order['packaging_number'] ?? $packagingOrderId),
            $action,
            $previous,
            $next,
            [
                'packaging_order_id' => $packagingOrderId,
                'packaging_number' => $order['packaging_number'] ?? null,
                'quality_control_check_id' => $order['quality_control_check_id'] ?? null,
                'qc_number' => $order['qc_number'] ?? null,
                'production_order_id' => $order['production_order_id'] ?? null,
                'item_code' => $firstItem['item_code'] ?? null,
                'quantity_to_pack' => $firstItem['quantity_to_pack'] ?? null,
                'quantity_packed' => $firstItem['quantity_packed'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
                'item_count' => count($items),
                'inventory_count' => is_array($order['inventory'] ?? null) ? count($order['inventory']) : 0,
            ]
        );
    }
}
