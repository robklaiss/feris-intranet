<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class RemissionRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                remissions.*,
                clients.name AS client_name,
                contracts.contract_number,
                GROUP_CONCAT(delivery_notes.note_number, ", ") AS notes_numbers
             FROM remissions
             LEFT JOIN remission_source_notes ON remission_source_notes.remission_id = remissions.id
             LEFT JOIN delivery_notes ON delivery_notes.id = remission_source_notes.delivery_note_id
             LEFT JOIN clients ON clients.id = COALESCE(remissions.client_id, delivery_notes.client_id)
             LEFT JOIN contracts ON contracts.id = COALESCE(remissions.contract_id, delivery_notes.contract_id)
             WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                remissions.remission_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
                OR delivery_notes.note_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND remissions.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' GROUP BY remissions.id
             ORDER BY remissions.remission_date DESC, remissions.id DESC';

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
        $remission = $this->fetchOne(
            'SELECT
                remissions.*,
                clients.name AS client_name,
                clients.addresses AS client_addresses,
                contracts.contract_number,
                COALESCE(remissions.client_id, delivery_notes.client_id) AS effective_client_id,
                COALESCE(remissions.contract_id, delivery_notes.contract_id) AS effective_contract_id,
                COALESCE(remissions.reference_number, delivery_notes.identifier_number) AS effective_reference_number,
                COALESCE(remissions.contract_type, delivery_notes.contract_type) AS effective_contract_type,
                COALESCE(remissions.tax_id, delivery_notes.tax_id) AS effective_tax_id
             FROM remissions
             LEFT JOIN remission_source_notes ON remission_source_notes.remission_id = remissions.id
             LEFT JOIN delivery_notes ON delivery_notes.id = remission_source_notes.delivery_note_id
             LEFT JOIN clients ON clients.id = COALESCE(remissions.client_id, delivery_notes.client_id)
             LEFT JOIN contracts ON contracts.id = COALESCE(remissions.contract_id, delivery_notes.contract_id)
             WHERE remissions.id = :id',
            ['id' => $id]
        );

        if (!$remission) {
            return null;
        }

        $remission['source_notes'] = $this->fetchAll(
            'SELECT delivery_notes.*
             FROM remission_source_notes
             INNER JOIN delivery_notes ON delivery_notes.id = remission_source_notes.delivery_note_id
             WHERE remission_source_notes.remission_id = :id',
            ['id' => $id]
        );
        $remission['items'] = $this->fetchAll('SELECT * FROM remission_items WHERE remission_id = :id ORDER BY id', ['id' => $id]);

        return $remission;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $noteIds
     */
    public function create(array $data, array $items, array $noteIds): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO remissions (
                    remission_number, remission_date, status, notes, total_amount, client_id, contract_id,
                    reference_number, contract_type, tax_id, origin_address, destination_address,
                    transfer_start_date, transfer_end_date, vehicle_brand, vehicle_plate, carrier_name,
                    carrier_tax_id, driver_name, driver_document, updated_at
                 )
                 VALUES (
                    :remission_number, :remission_date, :status, :notes, :total_amount, :client_id, :contract_id,
                    :reference_number, :contract_type, :tax_id, :origin_address, :destination_address,
                    :transfer_start_date, :transfer_end_date, :vehicle_brand, :vehicle_plate, :carrier_name,
                    :carrier_tax_id, :driver_name, :driver_document, CURRENT_TIMESTAMP
                 )',
                [
                    'remission_number' => $data['remission_number'],
                    'remission_date' => $data['remission_date'],
                    'status' => $data['status'] ?: 'draft',
                    'notes' => $data['notes'] ?: null,
                    'total_amount' => $data['total_amount'],
                    'client_id' => $data['client_id'] ?: null,
                    'contract_id' => $data['contract_id'] ?: null,
                    'reference_number' => $data['reference_number'] ?: null,
                    'contract_type' => $data['contract_type'] ?: null,
                    'tax_id' => $data['tax_id'] ?: null,
                    'origin_address' => $data['origin_address'] ?: null,
                    'destination_address' => $data['destination_address'] ?: null,
                    'transfer_start_date' => $data['transfer_start_date'] ?: null,
                    'transfer_end_date' => $data['transfer_end_date'] ?: null,
                    'vehicle_brand' => $data['vehicle_brand'] ?: null,
                    'vehicle_plate' => $data['vehicle_plate'] ?: null,
                    'carrier_name' => $data['carrier_name'] ?: null,
                    'carrier_tax_id' => $data['carrier_tax_id'] ?: null,
                    'driver_name' => $data['driver_name'] ?: null,
                    'driver_document' => $data['driver_document'] ?: null,
                ]
            );

            $remissionId = (int) $pdo->lastInsertId();

            foreach ($noteIds as $noteId) {
                $this->execute(
                    'INSERT INTO remission_source_notes (remission_id, delivery_note_id) VALUES (:remission_id, :delivery_note_id)',
                    [
                        'remission_id' => $remissionId,
                        'delivery_note_id' => $noteId,
                    ]
                );
            }

            foreach ($items as $item) {
                $this->execute(
                    'INSERT INTO remission_items (
                        remission_id, delivery_note_item_id, product_name, unit_measure, quantity, unit_price, total_item, updated_at
                    ) VALUES (
                        :remission_id, :delivery_note_item_id, :product_name, :unit_measure, :quantity, :unit_price, :total_item, CURRENT_TIMESTAMP
                    )',
                    [
                        'remission_id' => $remissionId,
                        'delivery_note_item_id' => $item['delivery_note_item_id'],
                        'product_name' => $item['product_name'],
                        'unit_measure' => $item['unit_measure'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_item' => $item['total_item'],
                    ]
                );
            }

            $pdo->commit();
            return $remissionId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function count(): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM remissions');
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openForSelection(): array
    {
        return $this->fetchAll(
            'SELECT
                remissions.id,
                remissions.remission_number,
                remissions.remission_date,
                COALESCE(remissions.client_id, delivery_notes.client_id) AS client_id,
                COALESCE(remissions.contract_id, delivery_notes.contract_id) AS contract_id,
                COALESCE(remissions.reference_number, delivery_notes.identifier_number) AS reference_number,
                COALESCE(remissions.contract_type, delivery_notes.contract_type) AS contract_type,
                COALESCE(remissions.tax_id, delivery_notes.tax_id) AS tax_id,
                clients.name AS client_name,
                contracts.contract_number
             FROM remissions
             LEFT JOIN remission_source_notes ON remission_source_notes.remission_id = remissions.id
             LEFT JOIN delivery_notes ON delivery_notes.id = remission_source_notes.delivery_note_id
             LEFT JOIN clients ON clients.id = COALESCE(remissions.client_id, delivery_notes.client_id)
             LEFT JOIN contracts ON contracts.id = COALESCE(remissions.contract_id, delivery_notes.contract_id)
             WHERE status = "confirmed"
             GROUP BY remissions.id
             ORDER BY remission_number DESC'
        );
    }
}
