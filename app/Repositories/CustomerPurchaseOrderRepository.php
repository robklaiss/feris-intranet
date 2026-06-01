<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class CustomerPurchaseOrderRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                customer_purchase_orders.*,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name
             FROM customer_purchase_orders
             LEFT JOIN clients ON clients.id = customer_purchase_orders.client_id
             LEFT JOIN contracts ON contracts.id = customer_purchase_orders.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = customer_purchase_orders.dependency_id
             WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                customer_purchase_orders.po_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
                OR client_dependencies.name LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'client_id', 'contract_id', 'dependency_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND customer_purchase_orders.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY customer_purchase_orders.po_date DESC, customer_purchase_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                customer_purchase_orders.*,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name,
                client_billing_contacts.name AS billing_contact_name
             FROM customer_purchase_orders
             LEFT JOIN clients ON clients.id = customer_purchase_orders.client_id
             LEFT JOIN contracts ON contracts.id = customer_purchase_orders.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = customer_purchase_orders.dependency_id
             LEFT JOIN client_billing_contacts ON client_billing_contacts.id = customer_purchase_orders.billing_contact_id
             WHERE customer_purchase_orders.id = :id',
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
            'SELECT
                customer_purchase_order_items.*,
                contract_item_specs.size,
                contract_item_specs.color,
                contract_item_specs.has_embroidery,
                contract_item_specs.has_screen_printing
             FROM customer_purchase_order_items
             LEFT JOIN contract_item_specs ON contract_item_specs.id = customer_purchase_order_items.contract_item_spec_id
             WHERE customer_purchase_order_items.customer_purchase_order_id = :id
             ORDER BY customer_purchase_order_items.id',
            ['id' => $id]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byContract(int $contractId): array
    {
        return $this->search(['contract_id' => $contractId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableContractItems(int $contractId, ?int $excludeOrderId = null): array
    {
        $items = $this->fetchAll(
            'SELECT *
             FROM contract_item_specs
             WHERE contract_id = :contract_id
               AND status = "confirmed"
             ORDER BY item_code, id',
            ['contract_id' => $contractId]
        );

        foreach ($items as &$item) {
            $item['ordered_quantity'] = $this->orderedQuantity((int) $item['id'], $excludeOrderId);
            $item['available_quantity'] = max(0.0, (float) $item['quantity'] - (float) $item['ordered_quantity']);
        }
        unset($item);

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function create(array $data, array $items): int
    {
        $contract = $this->confirmedContract((int) ($data['contract_id'] ?? 0));
        $items = $this->normalizeItems($contract, $items);

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO customer_purchase_orders (
                    client_id, dependency_id, contract_id, billing_contact_id, po_number, po_date, received_date,
                    status, notes, attachment_path, created_by, updated_at
                ) VALUES (
                    :client_id, :dependency_id, :contract_id, :billing_contact_id, :po_number, :po_date, :received_date,
                    :status, :notes, :attachment_path, :created_by, CURRENT_TIMESTAMP
                )',
                [
                    'client_id' => $contract['client_id'],
                    'dependency_id' => $data['dependency_id'] ?: null,
                    'contract_id' => $contract['id'],
                    'billing_contact_id' => $data['billing_contact_id'] ?: null,
                    'po_number' => trim((string) $data['po_number']),
                    'po_date' => $data['po_date'],
                    'received_date' => $data['received_date'],
                    'status' => 'draft',
                    'notes' => $data['notes'] ?: null,
                    'attachment_path' => $data['attachment_path'] ?: null,
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
            throw new InvalidArgumentException('Orden de compra cliente no encontrada.');
        }
        if ((string) $existing['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede editar una orden de compra cliente en borrador.');
        }

        $contract = $this->confirmedContract((int) $existing['contract_id']);
        $items = $this->normalizeItems($contract, $items, $id);

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'UPDATE customer_purchase_orders SET
                    dependency_id = :dependency_id,
                    billing_contact_id = :billing_contact_id,
                    po_number = :po_number,
                    po_date = :po_date,
                    received_date = :received_date,
                    notes = :notes,
                    attachment_path = :attachment_path,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                [
                    'id' => $id,
                    'dependency_id' => $data['dependency_id'] ?: null,
                    'billing_contact_id' => $data['billing_contact_id'] ?: null,
                    'po_number' => trim((string) $data['po_number']),
                    'po_date' => $data['po_date'],
                    'received_date' => $data['received_date'],
                    'notes' => $data['notes'] ?: null,
                    'attachment_path' => $data['attachment_path'] ?: null,
                ]
            );
            $this->execute('DELETE FROM customer_purchase_order_items WHERE customer_purchase_order_id = :id', ['id' => $id]);
            $this->insertItems($id, $items);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function confirm(int $id): void
    {
        $this->transition($id, 'confirmed', 'confirmed_by', 'confirmed_at');
    }

    public function cancel(int $id): void
    {
        $this->transition($id, 'cancelled', 'cancelled_by', 'cancelled_at');
    }

    public function close(int $id): void
    {
        $balances = $this->itemBalances($id);
        foreach ($balances as $balance) {
            if ((float) $balance['balance_quantity'] > 0.0001) {
                throw new InvalidArgumentException('La orden de compra cliente todavía tiene saldo pendiente de producción.');
            }
        }

        $this->transition($id, 'closed', 'closed_by', 'closed_at');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemBalances(int $id): array
    {
        $items = $this->items($id);
        foreach ($items as &$item) {
            $produced = $this->productionQuantity((int) $item['id']);
            $item['produced_quantity'] = $produced;
            $item['balance_quantity'] = max(0.0, (float) $item['quantity'] - $produced);
        }
        unset($item);

        return $items;
    }

    public function totalQuantity(int $id): float
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(SUM(quantity), 0) AS total FROM customer_purchase_order_items WHERE customer_purchase_order_id = :id',
            ['id' => $id]
        );

        return (float) ($row['total'] ?? 0);
    }

    private function transition(int $id, string $status, string $userField, string $dateField): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Orden de compra cliente no encontrada.');
        }
        if ((string) $existing['status'] === 'closed' && $status !== 'closed') {
            throw new InvalidArgumentException('La orden cerrada no puede cambiar de estado.');
        }

        $this->execute(
            "UPDATE customer_purchase_orders
             SET status = :status, {$userField} = :user_id, {$dateField} = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
            ['id' => $id, 'status' => $status, 'user_id' => Auth::id()]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmedContract(int $contractId): array
    {
        $contract = (new ContractRepository())->findWithItems($contractId);
        if (!$contract || (string) $contract['status'] !== 'confirmed') {
            throw new InvalidArgumentException('Solo se pueden crear órdenes de compra cliente desde contratos confirmados.');
        }

        return $contract;
    }

    /**
     * @param array<string, mixed> $contract
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $contract, array $items, ?int $excludeOrderId = null): array
    {
        if (trim((string) ($items[0]['contract_item_spec_id'] ?? '')) === '' && count($items) === 1) {
            $items = [];
        }
        if ($items === []) {
            throw new InvalidArgumentException('Debe incluir al menos un item técnico confirmado.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $specId = (int) ($item['contract_item_spec_id'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('No se permite cantidad cero o negativa.');
            }

            $spec = (new ContractItemSpecRepository())->findForContract((int) $contract['id'], $specId);
            if (!$spec || (string) $spec['status'] !== 'confirmed') {
                throw new InvalidArgumentException('Solo se pueden incluir ítems técnicos confirmados del contrato.');
            }

            $available = (float) $spec['quantity'] - $this->orderedQuantity($specId, $excludeOrderId);
            if ($quantity > $available + 0.0001) {
                throw new InvalidArgumentException('La cantidad supera el saldo contratado pendiente para ' . (string) $spec['item_code'] . '.');
            }

            $normalized[] = [
                'contract_item_spec_id' => $specId,
                'item_code' => $spec['item_code'],
                'description' => $spec['description'] ?: $spec['product_type'] ?: $spec['item_code'],
                'product_type' => $spec['product_type'],
                'quantity' => $quantity,
                'unit' => $spec['unit'] ?: 'unidad',
                'notes' => trim((string) ($item['notes'] ?? '')) ?: null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function insertItems(int $orderId, array $items): void
    {
        foreach ($items as $item) {
            $this->execute(
                'INSERT INTO customer_purchase_order_items (
                    customer_purchase_order_id, contract_item_spec_id, item_code, description, product_type,
                    quantity, unit, produced_quantity, balance_quantity, notes, updated_at
                ) VALUES (
                    :customer_purchase_order_id, :contract_item_spec_id, :item_code, :description, :product_type,
                    :quantity, :unit, 0, :balance_quantity, :notes, CURRENT_TIMESTAMP
                )',
                [
                    'customer_purchase_order_id' => $orderId,
                    'contract_item_spec_id' => $item['contract_item_spec_id'],
                    'item_code' => $item['item_code'],
                    'description' => $item['description'],
                    'product_type' => $item['product_type'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'balance_quantity' => $item['quantity'],
                    'notes' => $item['notes'],
                ]
            );
        }
    }

    private function orderedQuantity(int $specId, ?int $excludeOrderId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(customer_purchase_order_items.quantity), 0) AS total
             FROM customer_purchase_order_items
             INNER JOIN customer_purchase_orders ON customer_purchase_orders.id = customer_purchase_order_items.customer_purchase_order_id
             WHERE customer_purchase_order_items.contract_item_spec_id = :spec_id
               AND customer_purchase_orders.status != "cancelled"';
        $params = ['spec_id' => $specId];

        if ($excludeOrderId !== null) {
            $sql .= ' AND customer_purchase_orders.id != :exclude_id';
            $params['exclude_id'] = $excludeOrderId;
        }

        $row = $this->fetchOne($sql, $params);
        return (float) ($row['total'] ?? 0);
    }

    private function productionQuantity(int $customerPurchaseOrderItemId): float
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(SUM(production_order_items.quantity), 0) AS total
             FROM production_order_items
             INNER JOIN production_orders ON production_orders.id = production_order_items.production_order_id
             WHERE production_order_items.customer_purchase_order_item_id = :item_id
               AND production_orders.status != "cancelled"',
            ['item_id' => $customerPurchaseOrderItemId]
        );

        return (float) ($row['total'] ?? 0);
    }
}
