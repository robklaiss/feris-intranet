<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class ProductionOrderRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                production_orders.*,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name
             FROM production_orders
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = production_orders.dependency_id
             WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                production_orders.production_number LIKE :q
                OR customer_purchase_orders.po_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
                OR client_dependencies.name LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'production_stage', 'client_id', 'contract_id', 'dependency_id', 'customer_purchase_order_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND production_orders.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY production_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                production_orders.*,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name
             FROM production_orders
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = production_orders.dependency_id
             WHERE production_orders.id = :id',
            ['id' => $id]
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
     * @return array<int, array<string, mixed>>
     */
    public function items(int $id): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM production_order_items
             WHERE production_order_id = :id
             ORDER BY id',
            ['id' => $id]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingItemsForCustomerPurchaseOrder(int $customerPurchaseOrderId): array
    {
        $items = (new CustomerPurchaseOrderRepository())->items($customerPurchaseOrderId);

        foreach ($items as &$item) {
            $produced = $this->productionQuantity((int) $item['id']);
            $item['produced_quantity'] = $produced;
            $item['balance_quantity'] = max(0.0, (float) $item['quantity'] - $produced);
        }
        unset($item);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => (float) $item['balance_quantity'] > 0.0001
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function create(array $data, array $items): int
    {
        $customerOrder = (new CustomerPurchaseOrderRepository())->find((int) ($data['customer_purchase_order_id'] ?? 0));
        if (!$customerOrder || (string) $customerOrder['status'] !== 'confirmed') {
            throw new InvalidArgumentException('Solo se pueden crear órdenes de producción desde órdenes de compra cliente confirmadas.');
        }

        $items = $this->normalizeItems((int) $customerOrder['id'], $items);

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO production_orders (
                    customer_purchase_order_id, contract_id, client_id, dependency_id, production_number,
                    status, production_stage, planned_start_date, planned_end_date, notes, created_by, updated_at
                ) VALUES (
                    :customer_purchase_order_id, :contract_id, :client_id, :dependency_id, :production_number,
                    :status, :production_stage, :planned_start_date, :planned_end_date, :notes, :created_by, CURRENT_TIMESTAMP
                )',
                [
                    'customer_purchase_order_id' => $customerOrder['id'],
                    'contract_id' => $customerOrder['contract_id'],
                    'client_id' => $customerOrder['client_id'],
                    'dependency_id' => $customerOrder['dependency_id'] ?: null,
                    'production_number' => trim((string) $data['production_number']),
                    'status' => 'draft',
                    'production_stage' => 'pending',
                    'planned_start_date' => $data['planned_start_date'] ?: null,
                    'planned_end_date' => $data['planned_end_date'] ?: null,
                    'notes' => $data['notes'] ?: null,
                    'created_by' => Auth::id(),
                ]
            );

            $id = (int) $pdo->lastInsertId();
            $this->insertItems($id, $items);
            $pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function updateDraft(int $id, array $data, array $items): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Orden de producción no encontrada.');
        }
        if ((string) $existing['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede editar una orden de producción en borrador.');
        }

        $items = $this->normalizeItems((int) $existing['customer_purchase_order_id'], $items, $id);

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'UPDATE production_orders SET
                    production_number = :production_number,
                    planned_start_date = :planned_start_date,
                    planned_end_date = :planned_end_date,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'id' => $id,
                    'production_number' => trim((string) $data['production_number']),
                    'planned_start_date' => $data['planned_start_date'] ?: null,
                    'planned_end_date' => $data['planned_end_date'] ?: null,
                    'notes' => $data['notes'] ?: null,
                ]
            );
            $this->execute('DELETE FROM production_order_items WHERE production_order_id = :id', ['id' => $id]);
            $this->insertItems($id, $items);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function confirm(int $id): void
    {
        $this->execute(
            'UPDATE production_orders
             SET status = "confirmed",
                 production_stage = "ready_for_stock_check",
                 confirmed_by = :user_id,
                 confirmed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = "draft"',
            ['id' => $id, 'user_id' => Auth::id()]
        );
    }

    public function cancel(int $id): void
    {
        $this->transition($id, 'cancelled', 'cancelled_by', 'cancelled_at');
    }

    public function close(int $id): void
    {
        foreach ($this->items($id) as $item) {
            if ((float) $item['balance_quantity'] > 0.0001) {
                throw new InvalidArgumentException('La orden de producción todavía tiene saldo pendiente.');
            }
        }

        $this->transition($id, 'closed', 'closed_by', 'closed_at');
    }

    public function totalQuantity(int $id): float
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(SUM(quantity), 0) AS total FROM production_order_items WHERE production_order_id = :id',
            ['id' => $id]
        );

        return (float) ($row['total'] ?? 0);
    }

    private function transition(int $id, string $status, string $userField, string $dateField): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Orden de producción no encontrada.');
        }
        if ((string) $existing['status'] === 'closed' && $status !== 'closed') {
            throw new InvalidArgumentException('La orden cerrada no puede cambiar de estado.');
        }

        $this->execute(
            "UPDATE production_orders
             SET status = :status, {$userField} = :user_id, {$dateField} = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
            ['id' => $id, 'status' => $status, 'user_id' => Auth::id()]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(int $customerPurchaseOrderId, array $items, ?int $excludeProductionOrderId = null): array
    {
        if (trim((string) ($items[0]['customer_purchase_order_item_id'] ?? '')) === '' && count($items) === 1) {
            $items = [];
        }
        if ($items === []) {
            throw new InvalidArgumentException('Debe incluir al menos un item con saldo pendiente de producción.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $customerItemId = (int) ($item['customer_purchase_order_item_id'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('No se permite cantidad cero o negativa.');
            }

            $customerItem = $this->fetchOne(
                'SELECT
                    customer_purchase_order_items.*,
                    contract_item_specs.size,
                    contract_item_specs.color,
                    contract_item_specs.has_embroidery,
                    contract_item_specs.has_screen_printing
                 FROM customer_purchase_order_items
                 INNER JOIN contract_item_specs ON contract_item_specs.id = customer_purchase_order_items.contract_item_spec_id
                 WHERE customer_purchase_order_items.id = :id
                   AND customer_purchase_order_items.customer_purchase_order_id = :order_id',
                ['id' => $customerItemId, 'order_id' => $customerPurchaseOrderId]
            );

            if (!$customerItem) {
                throw new InvalidArgumentException('El item no pertenece a la orden de compra cliente.');
            }

            $available = (float) $customerItem['quantity'] - $this->productionQuantity($customerItemId, $excludeProductionOrderId);
            if ($quantity > $available + 0.0001) {
                throw new InvalidArgumentException('La cantidad supera el saldo pendiente de producción para ' . (string) $customerItem['item_code'] . '.');
            }

            $normalized[] = [
                'customer_purchase_order_item_id' => $customerItemId,
                'contract_item_spec_id' => (int) $customerItem['contract_item_spec_id'],
                'item_code' => $customerItem['item_code'],
                'product_type' => $customerItem['product_type'],
                'description' => $customerItem['description'],
                'size' => $customerItem['size'],
                'color' => $customerItem['color'],
                'quantity' => $quantity,
                'unit' => $customerItem['unit'] ?: 'unidad',
                'requires_embroidery' => !empty($customerItem['has_embroidery']) ? 1 : 0,
                'requires_screen_printing' => !empty($customerItem['has_screen_printing']) ? 1 : 0,
                'notes' => trim((string) ($item['notes'] ?? '')) ?: null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function insertItems(int $productionOrderId, array $items): void
    {
        foreach ($items as $item) {
            $this->execute(
                'INSERT INTO production_order_items (
                    production_order_id, customer_purchase_order_item_id, contract_item_spec_id, item_code,
                    product_type, description, size, color, quantity, unit, produced_quantity, balance_quantity,
                    requires_embroidery, requires_screen_printing, status, notes, updated_at
                ) VALUES (
                    :production_order_id, :customer_purchase_order_item_id, :contract_item_spec_id, :item_code,
                    :product_type, :description, :size, :color, :quantity, :unit, 0, :balance_quantity,
                    :requires_embroidery, :requires_screen_printing, "pending", :notes, CURRENT_TIMESTAMP
                )',
                [
                    'production_order_id' => $productionOrderId,
                    'customer_purchase_order_item_id' => $item['customer_purchase_order_item_id'],
                    'contract_item_spec_id' => $item['contract_item_spec_id'],
                    'item_code' => $item['item_code'],
                    'product_type' => $item['product_type'],
                    'description' => $item['description'],
                    'size' => $item['size'],
                    'color' => $item['color'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'balance_quantity' => $item['quantity'],
                    'requires_embroidery' => $item['requires_embroidery'],
                    'requires_screen_printing' => $item['requires_screen_printing'],
                    'notes' => $item['notes'],
                ]
            );
        }
    }

    private function productionQuantity(int $customerPurchaseOrderItemId, ?int $excludeProductionOrderId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(production_order_items.quantity), 0) AS total
             FROM production_order_items
             INNER JOIN production_orders ON production_orders.id = production_order_items.production_order_id
             WHERE production_order_items.customer_purchase_order_item_id = :item_id
               AND production_orders.status != "cancelled"';
        $params = ['item_id' => $customerPurchaseOrderItemId];

        if ($excludeProductionOrderId !== null) {
            $sql .= ' AND production_orders.id != :exclude_id';
            $params['exclude_id'] = $excludeProductionOrderId;
        }

        $row = $this->fetchOne($sql, $params);

        return (float) ($row['total'] ?? 0);
    }
}
