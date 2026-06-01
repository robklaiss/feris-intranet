<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class ContractRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT contracts.*, clients.name AS client_name
            FROM contracts
            LEFT JOIN clients ON clients.id = contracts.client_id
            WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                contracts.contract_number LIKE :q
                OR contracts.reference_number LIKE :q
                OR contracts.tax_id LIKE :q
                OR clients.name LIKE :q
                OR EXISTS (
                    SELECT 1
                    FROM contract_item_specs
                    LEFT JOIN client_dependencies ON client_dependencies.id = contract_item_specs.destination_dependency_id
                    WHERE contract_item_specs.contract_id = contracts.id
                      AND (
                        contract_item_specs.item_code LIKE :q
                        OR contract_item_specs.description LIKE :q
                        OR contract_item_specs.product_type LIKE :q
                        OR contract_item_specs.label LIKE :q
                        OR client_dependencies.name LIKE :q
                      )
                )
                OR EXISTS (
                    SELECT 1
                    FROM contract_dncp_data
                    WHERE contract_dncp_data.contract_id = contracts.id
                      AND (
                        contract_dncp_data.tender_id LIKE :q
                        OR contract_dncp_data.contract_number LIKE :q
                        OR contract_dncp_data.customer_purchase_order_number LIKE :q
                        OR contract_dncp_data.public_entity LIKE :q
                        OR contract_dncp_data.requesting_dependency LIKE :q
                        OR contract_dncp_data.fiscal_ruc LIKE :q
                        OR contract_dncp_data.fiscal_business_name LIKE :q
                      )
                )
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND contracts.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY contracts.date DESC, contracts.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithItems(int $id): ?array
    {
        $contract = $this->fetchOne(
            'SELECT contracts.*, clients.name AS client_name
            FROM contracts
            LEFT JOIN clients ON clients.id = contracts.client_id
            WHERE contracts.id = :id',
            ['id' => $id]
        );

        if (!$contract) {
            return null;
        }

        $contract['items'] = $this->fetchAll(
            'SELECT * FROM contract_items WHERE contract_id = :contract_id ORDER BY id',
            ['contract_id' => $id]
        );
        $contract['item_specs'] = (new ContractItemSpecRepository())->byContractId($id);
        $contract['dncp_data'] = (new ContractDncpDataRepository())->findByContractId($id) ?? $this->emptyDncpData();

        return $contract;
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
                'INSERT INTO contracts (
                    client_id, date, contract_number, reference_number, contract_type, tax_id, status, notes, total_amount, is_provisional, provisional_data, updated_at
                ) VALUES (
                    :client_id, :date, :contract_number, :reference_number, :contract_type, :tax_id, :status, :notes, :total_amount, :is_provisional, :provisional_data, CURRENT_TIMESTAMP
                )',
                [
                    'client_id' => $data['client_id'] ?: null,
                    'date' => $data['date'],
                    'contract_number' => $data['contract_number'],
                    'reference_number' => $data['reference_number'] ?: null,
                    'contract_type' => $data['contract_type'] ?: null,
                    'tax_id' => $data['tax_id'] ?: null,
                    'status' => $data['status'] ?: 'draft',
                    'notes' => $data['notes'] ?: null,
                    'total_amount' => $data['total_amount'],
                    'is_provisional' => $data['is_provisional'],
                    'provisional_data' => $data['provisional_data'] ?: null,
                ]
            );

            $contractId = (int) $pdo->lastInsertId();
            $this->syncItems($contractId, $items);
            $pdo->commit();

            return $contractId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function update(int $id, array $data, array $items): void
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'UPDATE contracts SET
                    client_id = :client_id,
                    date = :date,
                    contract_number = :contract_number,
                    reference_number = :reference_number,
                    contract_type = :contract_type,
                    tax_id = :tax_id,
                    status = :status,
                    notes = :notes,
                    total_amount = :total_amount,
                    is_provisional = :is_provisional,
                    provisional_data = :provisional_data,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id',
                [
                    'id' => $id,
                    'client_id' => $data['client_id'] ?: null,
                    'date' => $data['date'],
                    'contract_number' => $data['contract_number'],
                    'reference_number' => $data['reference_number'] ?: null,
                    'contract_type' => $data['contract_type'] ?: null,
                    'tax_id' => $data['tax_id'] ?: null,
                    'status' => $data['status'] ?: 'draft',
                    'notes' => $data['notes'] ?: null,
                    'total_amount' => $data['total_amount'],
                    'is_provisional' => $data['is_provisional'],
                    'provisional_data' => $data['provisional_data'] ?: null,
                ]
            );

            $this->execute('DELETE FROM contract_items WHERE contract_id = :contract_id', ['contract_id' => $id]);
            $this->syncItems($id, $items);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM contracts WHERE id = :id', ['id' => $id]);
    }

    public function count(): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM contracts');
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeForSelection(): array
    {
        return $this->fetchAll(
            'SELECT
                contracts.id,
                contracts.client_id,
                contracts.contract_number,
                contracts.reference_number,
                contracts.contract_type,
                contracts.tax_id,
                contracts.is_provisional,
                clients.name AS client_name
             FROM contracts
             LEFT JOIN clients ON clients.id = contracts.client_id
             WHERE contracts.status = "confirmed"
             ORDER BY contracts.contract_number'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byClientId(int $clientId): array
    {
        return $this->fetchAll(
            'SELECT * FROM contracts WHERE client_id = :client_id ORDER BY date DESC, id DESC',
            ['client_id' => $clientId]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(int $contractId, array $items): void
    {
        foreach ($items as $item) {
            $this->execute(
                'INSERT INTO contract_items (
                    contract_id, product_name, unit_measure, quantity, unit_price, total_item, notes, updated_at
                ) VALUES (
                    :contract_id, :product_name, :unit_measure, :quantity, :unit_price, :total_item, :notes, CURRENT_TIMESTAMP
                )',
                [
                    'contract_id' => $contractId,
                    'product_name' => $item['product_name'],
                    'unit_measure' => $item['unit_measure'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_item' => $item['total_item'],
                    'notes' => $item['notes'] ?: null,
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDncpData(): array
    {
        return [
            'tender_id' => '',
            'contract_number' => '',
            'customer_purchase_order_number' => '',
            'public_entity' => '',
            'requesting_dependency' => '',
            'procurement_modality' => '',
            'procurement_code' => '',
            'contract_date' => '',
            'valid_from' => '',
            'valid_until' => '',
            'currency' => 'PYG',
            'fiscal_business_name' => '',
            'fiscal_ruc' => '',
            'billing_contact_id' => '',
            'notes' => '',
        ];
    }
}
