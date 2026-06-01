<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class PurchaseOrderRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                purchase_orders.*,
                clients.name AS client_name,
                contracts.contract_number,
                COALESCE(purchase_orders.identifier_number, contracts.reference_number) AS effective_reference_number,
                COALESCE(purchase_orders.contract_type, contracts.contract_type) AS effective_contract_type,
                COALESCE(purchase_orders.tax_id, contracts.tax_id, clients.tax_id) AS effective_tax_id
             FROM purchase_orders
             LEFT JOIN clients ON clients.id = purchase_orders.client_id
             LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
             WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                purchase_orders.order_number LIKE :q
                OR purchase_orders.identifier_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND purchase_orders.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY purchase_orders.order_date DESC, purchase_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->search();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithItems(int $id): ?array
    {
        $order = $this->fetchOne(
            'SELECT
                purchase_orders.*,
                clients.name AS client_name,
                clients.addresses AS client_addresses,
                contracts.contract_number,
                COALESCE(purchase_orders.identifier_number, contracts.reference_number) AS effective_reference_number,
                COALESCE(purchase_orders.contract_type, contracts.contract_type) AS effective_contract_type,
                COALESCE(purchase_orders.tax_id, contracts.tax_id, clients.tax_id) AS effective_tax_id
             FROM purchase_orders
             LEFT JOIN clients ON clients.id = purchase_orders.client_id
             LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
             WHERE purchase_orders.id = :id',
            ['id' => $id]
        );

        if (!$order) {
            return null;
        }

        $order['items'] = $this->fetchAll(
            'SELECT purchase_order_items.*, contract_items.product_name AS source_product
             FROM purchase_order_items
             LEFT JOIN contract_items ON contract_items.id = purchase_order_items.contract_item_id
             WHERE purchase_order_id = :id
             ORDER BY purchase_order_items.id',
            ['id' => $id]
        );

        return $order;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function create(array $data, array $items): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO purchase_orders (
                    client_id, contract_id, order_number, order_date, identifier_number, status, notes, total_amount,
                    is_manual, is_provisional, provisional_data, contract_type, tax_id, linked_contract_snapshot, live_sync_fields, updated_at
                ) VALUES (
                    :client_id, :contract_id, :order_number, :order_date, :identifier_number, :status, :notes, :total_amount,
                    :is_manual, :is_provisional, :provisional_data, :contract_type, :tax_id, :linked_contract_snapshot, :live_sync_fields, CURRENT_TIMESTAMP
                )',
                [
                    'client_id' => $data['client_id'] ?: null,
                    'contract_id' => $data['contract_id'] ?: null,
                    'order_number' => $data['order_number'],
                    'order_date' => $data['order_date'],
                    'identifier_number' => $data['identifier_number'] ?: null,
                    'status' => $data['status'] ?: 'draft',
                    'notes' => $data['notes'] ?: null,
                    'total_amount' => $data['total_amount'],
                    'is_manual' => $data['is_manual'],
                    'is_provisional' => $data['is_provisional'],
                    'provisional_data' => $data['provisional_data'] ?: null,
                    'contract_type' => $data['contract_type'] ?: null,
                    'tax_id' => $data['tax_id'] ?: null,
                    'linked_contract_snapshot' => $data['linked_contract_snapshot'] ?: null,
                    'live_sync_fields' => $data['live_sync_fields'] ?: null,
                ]
            );

            $orderId = (int) $pdo->lastInsertId();

            foreach ($items as $item) {
                $this->execute(
                    'INSERT INTO purchase_order_items (
                        purchase_order_id, contract_item_id, product_name, unit_measure, quantity, unit_price, total_item, updated_at
                    ) VALUES (
                        :purchase_order_id, :contract_item_id, :product_name, :unit_measure, :quantity, :unit_price, :total_item, CURRENT_TIMESTAMP
                    )',
                    [
                        'purchase_order_id' => $orderId,
                        'contract_item_id' => $item['contract_item_id'] ?: null,
                        'product_name' => $item['product_name'],
                        'unit_measure' => $item['unit_measure'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_item' => $item['total_item'],
                    ]
                );
            }

            $pdo->commit();
            return $orderId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function count(): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM purchase_orders');
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openForSelection(): array
    {
        return $this->fetchAll(
            'SELECT
                purchase_orders.id,
                purchase_orders.order_number,
                purchase_orders.contract_id,
                purchase_orders.client_id,
                purchase_orders.identifier_number,
                COALESCE(purchase_orders.contract_type, contracts.contract_type) AS contract_type,
                COALESCE(purchase_orders.tax_id, contracts.tax_id, clients.tax_id) AS tax_id,
                clients.addresses AS client_addresses,
                clients.name AS client_name
             FROM purchase_orders
             LEFT JOIN clients ON clients.id = purchase_orders.client_id
             LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
             WHERE purchase_orders.status = "confirmed"
             ORDER BY purchase_orders.order_number DESC'
        );
    }
}
