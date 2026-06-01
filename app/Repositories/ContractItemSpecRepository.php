<?php

declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;

final class ContractItemSpecRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function byContractId(int $contractId): array
    {
        return $this->fetchAll(
            'SELECT contract_item_specs.*, client_dependencies.name AS destination_dependency_name
             FROM contract_item_specs
             LEFT JOIN client_dependencies ON client_dependencies.id = contract_item_specs.destination_dependency_id
             WHERE contract_item_specs.contract_id = :contract_id
             ORDER BY contract_item_specs.id',
            ['contract_id' => $contractId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT contract_item_specs.*, client_dependencies.name AS destination_dependency_name
             FROM contract_item_specs
             LEFT JOIN client_dependencies ON client_dependencies.id = contract_item_specs.destination_dependency_id
             WHERE contract_item_specs.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForContract(int $contractId, int $id): ?array
    {
        return $this->fetchOne(
            'SELECT contract_item_specs.*, client_dependencies.name AS destination_dependency_name
             FROM contract_item_specs
             LEFT JOIN client_dependencies ON client_dependencies.id = contract_item_specs.destination_dependency_id
             WHERE contract_item_specs.contract_id = :contract_id
               AND contract_item_specs.id = :id',
            ['contract_id' => $contractId, 'id' => $id]
        );
    }

    public function belongsToContract(int $contractId, int $id): bool
    {
        return $this->findForContract($contractId, $id) !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $contractId, array $data): int
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('No se puede crear un item tecnico sin contrato.');
        }

        $params = $this->params($contractId, $data);
        $this->validate($contractId, $params);

        $this->execute(
            'INSERT INTO contract_item_specs (
                contract_id, item_code, product_type, product_category, description, is_textile,
                size, color, fabric, grammage, measurements, finishing, has_embroidery,
                embroidery_details, has_screen_printing, screen_printing_details, logo_position,
                quantity, unit, label, destination_dependency_id, technical_notes, attachment_path,
                status, updated_at
            ) VALUES (
                :contract_id, :item_code, :product_type, :product_category, :description, :is_textile,
                :size, :color, :fabric, :grammage, :measurements, :finishing, :has_embroidery,
                :embroidery_details, :has_screen_printing, :screen_printing_details, :logo_position,
                :quantity, :unit, :label, :destination_dependency_id, :technical_notes, :attachment_path,
                :status, CURRENT_TIMESTAMP
            )',
            $params
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $contractId, int $id, array $data): void
    {
        $existing = $this->findForContract($contractId, $id);
        if (!$existing) {
            throw new InvalidArgumentException('El item tecnico no pertenece al contrato indicado.');
        }

        if (($existing['status'] ?? '') === 'confirmed') {
            throw new InvalidArgumentException('No se puede editar libremente un item tecnico confirmado. Cancelalo o reabri el contrato para registrar uno nuevo.');
        }

        $params = $this->params($contractId, $data, (string) ($existing['status'] ?? 'draft'));
        $params['id'] = $id;
        $this->validate($contractId, $params);

        $this->execute(
            'UPDATE contract_item_specs SET
                item_code = :item_code,
                product_type = :product_type,
                product_category = :product_category,
                description = :description,
                is_textile = :is_textile,
                size = :size,
                color = :color,
                fabric = :fabric,
                grammage = :grammage,
                measurements = :measurements,
                finishing = :finishing,
                has_embroidery = :has_embroidery,
                embroidery_details = :embroidery_details,
                has_screen_printing = :has_screen_printing,
                screen_printing_details = :screen_printing_details,
                logo_position = :logo_position,
                quantity = :quantity,
                unit = :unit,
                label = :label,
                destination_dependency_id = :destination_dependency_id,
                technical_notes = :technical_notes,
                attachment_path = :attachment_path,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
             WHERE contract_id = :contract_id AND id = :id',
            $params
        );
    }

    public function confirm(int $contractId, int $id): void
    {
        $this->transition($contractId, $id, 'confirmed');
    }

    public function cancel(int $contractId, int $id): void
    {
        $this->transition($contractId, $id, 'cancelled');
    }

    private function transition(int $contractId, int $id, string $status): void
    {
        if (!$this->belongsToContract($contractId, $id)) {
            throw new InvalidArgumentException('El item tecnico no pertenece al contrato indicado.');
        }

        $this->execute(
            'UPDATE contract_item_specs
             SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE contract_id = :contract_id AND id = :id',
            ['contract_id' => $contractId, 'id' => $id, 'status' => $status]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function params(int $contractId, array $data, string $fallbackStatus = 'draft'): array
    {
        $category = $this->category((string) ($data['product_category'] ?? 'textil'));
        $isTextile = array_key_exists('is_textile', $data)
            ? (!empty($data['is_textile']) ? 1 : 0)
            : ($category === 'textil' ? 1 : 0);

        return [
            'contract_id' => $contractId,
            'item_code' => trim((string) ($data['item_code'] ?? '')),
            'product_type' => $this->nullable($data['product_type'] ?? null),
            'product_category' => $category,
            'description' => $this->nullable($data['description'] ?? null),
            'is_textile' => $isTextile,
            'size' => $this->nullable($data['size'] ?? null),
            'color' => $this->nullable($data['color'] ?? null),
            'fabric' => $this->nullable($data['fabric'] ?? null),
            'grammage' => $this->nullable($data['grammage'] ?? null),
            'measurements' => $this->nullable($data['measurements'] ?? null),
            'finishing' => $this->nullable($data['finishing'] ?? null),
            'has_embroidery' => !empty($data['has_embroidery']) ? 1 : 0,
            'embroidery_details' => $this->nullable($data['embroidery_details'] ?? null),
            'has_screen_printing' => !empty($data['has_screen_printing']) ? 1 : 0,
            'screen_printing_details' => $this->nullable($data['screen_printing_details'] ?? null),
            'logo_position' => $this->nullable($data['logo_position'] ?? null),
            'quantity' => (float) ($data['quantity'] ?? 0),
            'unit' => $this->nullable($data['unit'] ?? 'unidad'),
            'label' => $this->nullable($data['label'] ?? null),
            'destination_dependency_id' => !empty($data['destination_dependency_id']) ? (int) $data['destination_dependency_id'] : null,
            'technical_notes' => $this->nullable($data['technical_notes'] ?? null),
            'attachment_path' => $this->nullable($data['attachment_path'] ?? null),
            'status' => in_array(($data['status'] ?? $fallbackStatus), ['draft', 'confirmed', 'cancelled'], true)
                ? (string) ($data['status'] ?? $fallbackStatus)
                : $fallbackStatus,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function validate(int $contractId, array $params): void
    {
        if (trim((string) $params['item_code']) === '') {
            throw new InvalidArgumentException('El codigo de item es obligatorio.');
        }

        if ((float) $params['quantity'] <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        $dependencyId = $params['destination_dependency_id'];
        if ($dependencyId === null) {
            return;
        }

        $row = $this->fetchOne(
            'SELECT client_dependencies.id
             FROM contracts
             INNER JOIN client_dependencies ON client_dependencies.client_id = contracts.client_id
             WHERE contracts.id = :contract_id
               AND client_dependencies.id = :dependency_id',
            ['contract_id' => $contractId, 'dependency_id' => $dependencyId]
        );

        if (!$row) {
            throw new InvalidArgumentException('La dependencia destino no pertenece al cliente del contrato.');
        }
    }

    private function category(string $category): string
    {
        $category = strtolower(trim($category));
        return in_array($category, ['textil', 'consumo', 'otro'], true) ? $category : 'otro';
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
