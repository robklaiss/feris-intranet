<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class ClientBillingContactRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function byClientId(int $clientId): array
    {
        return $this->fetchAll(
            'SELECT client_billing_contacts.*, client_dependencies.name AS dependency_name
             FROM client_billing_contacts
             LEFT JOIN client_dependencies ON client_dependencies.id = client_billing_contacts.dependency_id
             WHERE client_billing_contacts.client_id = :client_id
             ORDER BY client_billing_contacts.is_default DESC, client_billing_contacts.name',
            ['client_id' => $clientId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForSelection(): array
    {
        return $this->fetchAll(
            'SELECT client_billing_contacts.*, clients.name AS client_name, client_dependencies.name AS dependency_name
             FROM client_billing_contacts
             INNER JOIN clients ON clients.id = client_billing_contacts.client_id
             LEFT JOIN client_dependencies ON client_dependencies.id = client_billing_contacts.dependency_id
             ORDER BY clients.name, client_billing_contacts.is_default DESC, client_billing_contacts.name'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForClient(int $clientId, int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM client_billing_contacts WHERE client_id = :client_id AND id = :id',
            ['client_id' => $clientId, 'id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $clientId, array $data): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $params = $this->params($clientId, $data);
            if ((int) $params['is_default'] === 1) {
                $this->clearDefault($clientId);
            }

            $this->execute(
                'INSERT INTO client_billing_contacts (
                    client_id, dependency_id, name, role, phone, email, ruc, business_name,
                    address, is_default, notes, updated_at
                ) VALUES (
                    :client_id, :dependency_id, :name, :role, :phone, :email, :ruc, :business_name,
                    :address, :is_default, :notes, CURRENT_TIMESTAMP
                )',
                $params
            );

            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $clientId, int $id, array $data): void
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $params = $this->params($clientId, $data);
            $params['id'] = $id;
            if ((int) $params['is_default'] === 1) {
                $this->clearDefault($clientId);
            }

            $this->execute(
                'UPDATE client_billing_contacts SET
                    dependency_id = :dependency_id,
                    name = :name,
                    role = :role,
                    phone = :phone,
                    email = :email,
                    ruc = :ruc,
                    business_name = :business_name,
                    address = :address,
                    is_default = :is_default,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE client_id = :client_id AND id = :id',
                $params
            );

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function clearDefault(int $clientId): void
    {
        $this->execute(
            'UPDATE client_billing_contacts SET is_default = 0, updated_at = CURRENT_TIMESTAMP WHERE client_id = :client_id',
            ['client_id' => $clientId]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function params(int $clientId, array $data): array
    {
        return [
            'client_id' => $clientId,
            'dependency_id' => !empty($data['dependency_id']) ? (int) $data['dependency_id'] : null,
            'name' => trim((string) ($data['name'] ?? '')),
            'role' => $this->nullable($data['role'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'email' => $this->nullable($data['email'] ?? null),
            'ruc' => $this->nullable($data['ruc'] ?? null),
            'business_name' => $this->nullable($data['business_name'] ?? null),
            'address' => $this->nullable($data['address'] ?? null),
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'notes' => $this->nullable($data['notes'] ?? null),
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
