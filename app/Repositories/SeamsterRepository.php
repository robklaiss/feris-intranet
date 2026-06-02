<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use InvalidArgumentException;

final class SeamsterRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT * FROM seamsters WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (name LIKE :q OR document_number LIKE :q OR email LIKE :q OR phone LIKE :q)';
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
        return $this->fetchOne('SELECT * FROM seamsters WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->normalize($data);

        $this->execute(
            'INSERT INTO seamsters (
                name, document_number, phone, email, address, status, notes, updated_at
            ) VALUES (
                :name, :document_number, :phone, :email, :address, :status, :notes, CURRENT_TIMESTAMP
            )',
            $data
        );

        $id = (int) $this->db()->lastInsertId();
        $this->audit('create_seamster', null, (string) $data['status'], $this->find($id), $id);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Costurero no encontrado.');
        }

        $data = $this->normalize($data);
        $this->execute(
            'UPDATE seamsters SET
                name = :name,
                document_number = :document_number,
                phone = :phone,
                email = :email,
                address = :address,
                status = :status,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            $data + ['id' => $id]
        );

        $this->audit('update_seamster', (string) $existing['status'], (string) $data['status'], $this->find($id), $id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $normalized = [
            'name' => trim((string) ($data['name'] ?? '')),
            'document_number' => $this->nullable($data['document_number'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'email' => $this->nullable($data['email'] ?? null),
            'address' => $this->nullable($data['address'] ?? null),
            'status' => trim((string) ($data['status'] ?? 'active')) ?: 'active',
            'notes' => $this->nullable($data['notes'] ?? null),
        ];

        if ($normalized['name'] === '') {
            throw new InvalidArgumentException('El nombre del costurero es obligatorio.');
        }
        if (!in_array($normalized['status'], ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Estado de costurero inválido.');
        }

        return $normalized;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $seamster
     */
    private function audit(string $action, ?string $previous, ?string $next, ?array $seamster, int $seamsterId): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'seamsters',
            $seamsterId,
            (string) ($seamster['name'] ?? $seamsterId),
            $action,
            $previous,
            $next,
            [
                'seamster_id' => $seamsterId,
                'document_number' => $seamster['document_number'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
            ]
        );
    }
}
