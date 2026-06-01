<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class InvoiceRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                invoices.*,
                clients.name AS client_name,
                contracts.contract_number,
                GROUP_CONCAT(remissions.remission_number, ", ") AS remission_numbers
             FROM invoices
             LEFT JOIN clients ON clients.id = invoices.client_id
             LEFT JOIN contracts ON contracts.id = invoices.contract_id
             LEFT JOIN invoice_source_remissions ON invoice_source_remissions.invoice_id = invoices.id
             LEFT JOIN remissions ON remissions.id = invoice_source_remissions.remission_id
             WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                invoices.invoice_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
                OR remissions.remission_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND invoices.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' GROUP BY invoices.id
             ORDER BY invoices.invoice_date DESC, invoices.id DESC';

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
        $invoice = $this->fetchOne(
            'SELECT
                invoices.*,
                clients.name AS client_name,
                contracts.contract_number
             FROM invoices
             LEFT JOIN clients ON clients.id = invoices.client_id
             LEFT JOIN contracts ON contracts.id = invoices.contract_id
             WHERE invoices.id = :id',
            ['id' => $id]
        );

        if (!$invoice) {
            return null;
        }

        $invoice['source_remissions'] = $this->fetchAll(
            'SELECT remissions.*
             FROM invoice_source_remissions
             INNER JOIN remissions ON remissions.id = invoice_source_remissions.remission_id
             WHERE invoice_source_remissions.invoice_id = :id',
            ['id' => $id]
        );
        $invoice['items'] = $this->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = :id ORDER BY id', ['id' => $id]);

        return $invoice;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $remissionIds
     */
    public function create(array $data, array $items, array $remissionIds): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO invoices (
                    invoice_number, invoice_date, status, notes, total_amount, billing_status, billing_payload,
                    client_id, contract_id, reference_number, contract_type, tax_id, billing_address, sale_condition, updated_at
                 ) VALUES (
                    :invoice_number, :invoice_date, :status, :notes, :total_amount, :billing_status, :billing_payload,
                    :client_id, :contract_id, :reference_number, :contract_type, :tax_id, :billing_address, :sale_condition, CURRENT_TIMESTAMP
                 )',
                [
                    'invoice_number' => $data['invoice_number'],
                    'invoice_date' => $data['invoice_date'],
                    'status' => $data['status'] ?: 'draft',
                    'notes' => $data['notes'] ?: null,
                    'total_amount' => $data['total_amount'],
                    'billing_status' => $data['billing_status'] ?: 'pending',
                    'billing_payload' => $data['billing_payload'] ?: null,
                    'client_id' => $data['client_id'] ?: null,
                    'contract_id' => $data['contract_id'] ?: null,
                    'reference_number' => $data['reference_number'] ?: null,
                    'contract_type' => $data['contract_type'] ?: null,
                    'tax_id' => $data['tax_id'] ?: null,
                    'billing_address' => $data['billing_address'] ?: null,
                    'sale_condition' => $data['sale_condition'] ?: 'contado',
                ]
            );

            $invoiceId = (int) $pdo->lastInsertId();

            foreach ($remissionIds as $remissionId) {
                $this->execute(
                    'INSERT INTO invoice_source_remissions (invoice_id, remission_id) VALUES (:invoice_id, :remission_id)',
                    [
                        'invoice_id' => $invoiceId,
                        'remission_id' => $remissionId,
                    ]
                );
            }

            foreach ($items as $item) {
                $this->execute(
                    'INSERT INTO invoice_items (
                        invoice_id, remission_item_id, product_name, unit_measure, quantity, unit_price, total_item, updated_at
                    ) VALUES (
                        :invoice_id, :remission_item_id, :product_name, :unit_measure, :quantity, :unit_price, :total_item, CURRENT_TIMESTAMP
                    )',
                    [
                        'invoice_id' => $invoiceId,
                        'remission_item_id' => $item['remission_item_id'],
                        'product_name' => $item['product_name'],
                        'unit_measure' => $item['unit_measure'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_item' => $item['total_item'],
                    ]
                );
            }

            $pdo->commit();
            return $invoiceId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function updateBillingStatus(int $id, string $status, array $payload): void
    {
        $this->execute(
            'UPDATE invoices SET billing_status = :status, billing_payload = :billing_payload, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [
                'id' => $id,
                'status' => $status,
                'billing_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    public function count(): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM invoices');
        return (int) ($row['total'] ?? 0);
    }
}
