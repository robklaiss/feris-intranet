<?php

declare(strict_types=1);

namespace App\Repositories;

final class ClientDependencyRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function byClientId(int $clientId): array
    {
        return $this->fetchAll(
            'SELECT client_dependencies.*, client_billing_contacts.name AS billing_contact_name
             FROM client_dependencies
             LEFT JOIN client_billing_contacts ON client_billing_contacts.id = client_dependencies.billing_contact_id
             WHERE client_dependencies.client_id = :client_id
             ORDER BY client_dependencies.name',
            ['client_id' => $clientId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForClient(int $clientId, int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM client_dependencies WHERE client_id = :client_id AND id = :id',
            ['client_id' => $clientId, 'id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $clientId, array $data): int
    {
        $this->execute(
            'INSERT INTO client_dependencies (
                client_id, name, address, city, phone, email, operational_contact_name,
                operational_contact_phone, reception_contact_name, reception_contact_phone,
                billing_contact_id, notes, updated_at
            ) VALUES (
                :client_id, :name, :address, :city, :phone, :email, :operational_contact_name,
                :operational_contact_phone, :reception_contact_name, :reception_contact_phone,
                :billing_contact_id, :notes, CURRENT_TIMESTAMP
            )',
            $this->params($clientId, $data)
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $clientId, int $id, array $data): void
    {
        $params = $this->params($clientId, $data);
        $params['id'] = $id;

        $this->execute(
            'UPDATE client_dependencies SET
                name = :name,
                address = :address,
                city = :city,
                phone = :phone,
                email = :email,
                operational_contact_name = :operational_contact_name,
                operational_contact_phone = :operational_contact_phone,
                reception_contact_name = :reception_contact_name,
                reception_contact_phone = :reception_contact_phone,
                billing_contact_id = :billing_contact_id,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
             WHERE client_id = :client_id AND id = :id',
            $params
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
            'name' => trim((string) ($data['name'] ?? '')),
            'address' => $this->nullable($data['address'] ?? null),
            'city' => $this->nullable($data['city'] ?? null),
            'phone' => $this->nullable($data['phone'] ?? null),
            'email' => $this->nullable($data['email'] ?? null),
            'operational_contact_name' => $this->nullable($data['operational_contact_name'] ?? null),
            'operational_contact_phone' => $this->nullable($data['operational_contact_phone'] ?? null),
            'reception_contact_name' => $this->nullable($data['reception_contact_name'] ?? null),
            'reception_contact_phone' => $this->nullable($data['reception_contact_phone'] ?? null),
            'billing_contact_id' => !empty($data['billing_contact_id']) ? (int) $data['billing_contact_id'] : null,
            'notes' => $this->nullable($data['notes'] ?? null),
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
