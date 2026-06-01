<?php

declare(strict_types=1);

namespace App\Repositories;

final class ClientRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT * FROM clients WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                name LIKE :q
                OR tax_id LIKE :q
                OR addresses LIKE :q
                OR contacts LIKE :q
                OR EXISTS (
                    SELECT 1
                    FROM client_dependencies
                    WHERE client_dependencies.client_id = clients.id
                      AND (
                        client_dependencies.name LIKE :q
                        OR client_dependencies.city LIKE :q
                        OR client_dependencies.email LIKE :q
                      )
                )
                OR EXISTS (
                    SELECT 1
                    FROM client_billing_contacts
                    WHERE client_billing_contacts.client_id = clients.id
                      AND (
                        client_billing_contacts.name LIKE :q
                        OR client_billing_contacts.ruc LIKE :q
                        OR client_billing_contacts.business_name LIKE :q
                        OR client_billing_contacts.email LIKE :q
                      )
                )
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY name';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM clients WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $this->execute(
            'INSERT INTO clients (name, tax_id, addresses, contacts, status, updated_at) VALUES (:name, :tax_id, :addresses, :contacts, :status, CURRENT_TIMESTAMP)',
            [
                'name' => $data['name'],
                'tax_id' => $data['tax_id'] ?: null,
                'addresses' => $data['addresses'] ?: null,
                'contacts' => $data['contacts'] ?: null,
                'status' => $data['status'] ?: 'active',
            ]
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->execute(
            'UPDATE clients SET name = :name, tax_id = :tax_id, addresses = :addresses, contacts = :contacts, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [
                'id' => $id,
                'name' => $data['name'],
                'tax_id' => $data['tax_id'] ?: null,
                'addresses' => $data['addresses'] ?: null,
                'contacts' => $data['contacts'] ?: null,
                'status' => $data['status'] ?: 'active',
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM clients WHERE id = :id', ['id' => $id]);
    }

    public function count(): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM clients');
        return (int) ($row['total'] ?? 0);
    }
}
