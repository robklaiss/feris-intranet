<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use InvalidArgumentException;

final class SupplierRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT * FROM suppliers WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (name LIKE :q OR ruc LIKE :q OR contact_name LIKE :q OR email LIKE :q OR phone LIKE :q)';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND status = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        $sql .= ' ORDER BY status = "active" DESC, name';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM suppliers WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->normalize($data);

        $this->execute(
            'INSERT INTO suppliers (
                name, ruc, contact_name, phone, email, address, payment_terms, delivery_terms, status, notes, updated_at
            ) VALUES (
                :name, :ruc, :contact_name, :phone, :email, :address, :payment_terms, :delivery_terms, :status, :notes, CURRENT_TIMESTAMP
            )',
            $data
        );

        $id = (int) $this->db()->lastInsertId();
        $this->audit('create_supplier', null, (string) $data['status'], $this->find($id), $id);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Proveedor no encontrado.');
        }

        $data = $this->normalize($data);
        $this->execute(
            'UPDATE suppliers SET
                name = :name,
                ruc = :ruc,
                contact_name = :contact_name,
                phone = :phone,
                email = :email,
                address = :address,
                payment_terms = :payment_terms,
                delivery_terms = :delivery_terms,
                status = :status,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            $data + ['id' => $id]
        );

        $this->audit('update_supplier', (string) $existing['status'], (string) $data['status'], $this->find($id), $id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $normalized = [
            'name' => trim((string) ($data['name'] ?? '')),
            'ruc' => $this->nullable($data['ruc'] ?? null),
            'contact_name' => $this->nullable($data['contact_name'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'email' => $this->nullable($data['email'] ?? null),
            'address' => $this->nullable($data['address'] ?? null),
            'payment_terms' => $this->nullable($data['payment_terms'] ?? null),
            'delivery_terms' => $this->nullable($data['delivery_terms'] ?? null),
            'status' => trim((string) ($data['status'] ?? 'active')) ?: 'active',
            'notes' => $this->nullable($data['notes'] ?? null),
        ];

        if ($normalized['name'] === '') {
            throw new InvalidArgumentException('El nombre del proveedor es obligatorio.');
        }
        if (!in_array($normalized['status'], ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Estado de proveedor inválido.');
        }

        return $normalized;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $supplier
     */
    private function audit(string $action, ?string $previous, ?string $next, ?array $supplier, int $supplierId): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'suppliers',
            $supplierId,
            (string) ($supplier['name'] ?? $supplierId),
            $action,
            $previous,
            $next,
            [
                'supplier_id' => $supplierId,
                'status_previous' => $previous,
                'status_new' => $next,
                'ruc' => $supplier['ruc'] ?? null,
            ]
        );
    }
}
