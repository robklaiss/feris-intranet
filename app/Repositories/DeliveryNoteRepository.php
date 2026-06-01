<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class DeliveryNoteRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                delivery_notes.*,
                clients.name AS client_name,
                contracts.contract_number,
                GROUP_CONCAT(DISTINCT purchase_orders.order_number) AS order_numbers
             FROM delivery_notes
             LEFT JOIN clients ON clients.id = delivery_notes.client_id
             LEFT JOIN contracts ON contracts.id = delivery_notes.contract_id
             LEFT JOIN delivery_note_source_orders ON delivery_note_source_orders.delivery_note_id = delivery_notes.id
             LEFT JOIN purchase_orders ON purchase_orders.id = delivery_note_source_orders.purchase_order_id
             WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                delivery_notes.note_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
                OR purchase_orders.order_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND delivery_notes.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' GROUP BY delivery_notes.id
             ORDER BY delivery_notes.note_date DESC, delivery_notes.id DESC';

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
        $note = $this->fetchOne(
            'SELECT
                delivery_notes.*,
                clients.name AS client_name,
                clients.addresses AS client_addresses,
                contracts.contract_number
             FROM delivery_notes
             LEFT JOIN clients ON clients.id = delivery_notes.client_id
             LEFT JOIN contracts ON contracts.id = delivery_notes.contract_id
             WHERE delivery_notes.id = :id',
            ['id' => $id]
        );

        if (!$note) {
            return null;
        }

        $note['source_orders'] = $this->fetchAll(
            'SELECT purchase_orders.*
             FROM delivery_note_source_orders
             INNER JOIN purchase_orders ON purchase_orders.id = delivery_note_source_orders.purchase_order_id
             WHERE delivery_note_source_orders.delivery_note_id = :id
             ORDER BY purchase_orders.order_date DESC, purchase_orders.id DESC',
            ['id' => $id]
        );
        $note['items'] = $this->fetchAll('SELECT * FROM delivery_note_items WHERE delivery_note_id = :id ORDER BY id', ['id' => $id]);

        return $note;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $purchaseOrderIds
     */
    public function create(array $data, array $items, array $purchaseOrderIds): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO delivery_notes (
                    purchase_order_id, note_number, note_date, status, notes, total_amount, delivery_address,
                    receiver_name, receiver_signature, issuer_name, issuer_signature,
                    client_id, contract_id, identifier_number, contract_type, tax_id, updated_at
                ) VALUES (
                    :purchase_order_id, :note_number, :note_date, :status, :notes, :total_amount, :delivery_address,
                    :receiver_name, :receiver_signature, :issuer_name, :issuer_signature,
                    :client_id, :contract_id, :identifier_number, :contract_type, :tax_id, CURRENT_TIMESTAMP
                )',
                [
                    'purchase_order_id' => $purchaseOrderIds[0] ?? $data['purchase_order_id'],
                    'note_number' => $data['note_number'],
                    'note_date' => $data['note_date'],
                    'status' => $data['status'] ?: 'draft',
                    'notes' => $data['notes'] ?: null,
                    'total_amount' => $data['total_amount'],
                    'delivery_address' => $data['delivery_address'] ?: null,
                    'receiver_name' => $data['receiver_name'] ?: null,
                    'receiver_signature' => $data['receiver_signature'] ?: null,
                    'issuer_name' => $data['issuer_name'] ?: null,
                    'issuer_signature' => $data['issuer_signature'] ?: null,
                    'client_id' => $data['client_id'] ?: null,
                    'contract_id' => $data['contract_id'] ?: null,
                    'identifier_number' => $data['identifier_number'] ?: null,
                    'contract_type' => $data['contract_type'] ?: null,
                    'tax_id' => $data['tax_id'] ?: null,
                ]
            );

            $noteId = (int) $pdo->lastInsertId();

            foreach ($purchaseOrderIds as $purchaseOrderId) {
                $this->execute(
                    'INSERT INTO delivery_note_source_orders (delivery_note_id, purchase_order_id) VALUES (:delivery_note_id, :purchase_order_id)',
                    [
                        'delivery_note_id' => $noteId,
                        'purchase_order_id' => $purchaseOrderId,
                    ]
                );
            }

            foreach ($items as $item) {
                $this->execute(
                    'INSERT INTO delivery_note_items (
                        delivery_note_id, purchase_order_item_id, product_name, unit_measure, quantity, unit_price, total_item, updated_at
                    ) VALUES (
                        :delivery_note_id, :purchase_order_item_id, :product_name, :unit_measure, :quantity, :unit_price, :total_item, CURRENT_TIMESTAMP
                    )',
                    [
                        'delivery_note_id' => $noteId,
                        'purchase_order_item_id' => $item['purchase_order_item_id'],
                        'product_name' => $item['product_name'],
                        'unit_measure' => $item['unit_measure'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_item' => $item['total_item'],
                    ]
                );
            }

            $pdo->commit();
            return $noteId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function count(): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM delivery_notes');
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openForSelection(): array
    {
        return $this->fetchAll(
            'SELECT
                delivery_notes.id,
                delivery_notes.note_number,
                delivery_notes.note_date,
                delivery_notes.delivery_address,
                delivery_notes.contract_id,
                delivery_notes.identifier_number,
                delivery_notes.contract_type,
                delivery_notes.tax_id,
                delivery_notes.client_id,
                clients.name AS client_name,
                contracts.contract_number
             FROM delivery_notes
             LEFT JOIN contracts ON contracts.id = delivery_notes.contract_id
             LEFT JOIN clients ON clients.id = delivery_notes.client_id
             WHERE delivery_notes.status = "confirmed"
             ORDER BY delivery_notes.note_number DESC'
        );
    }
}
