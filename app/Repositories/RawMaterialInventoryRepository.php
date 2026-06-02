<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Auth;
use InvalidArgumentException;

final class RawMaterialInventoryRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT *, (quantity_available - quantity_reserved) AS available_to_reserve
                FROM raw_material_inventory
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                internal_code LIKE :q
                OR material_type LIKE :q
                OR description LIKE :q
                OR supplier_name LIKE :q
                OR related_item_code LIKE :q
                OR related_product_type LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'material_type', 'related_item_code', 'related_product_type'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND {$field} = :{$field}";
                $params[$field] = trim((string) $filters[$field]);
            }
        }

        $sql .= ' ORDER BY status = "active" DESC, material_type, internal_code';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT *, (quantity_available - quantity_reserved) AS available_to_reserve
             FROM raw_material_inventory
             WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->normalize($data);

        $this->execute(
            'INSERT INTO raw_material_inventory (
                internal_code, material_type, description, unit, quantity_available, quantity_reserved,
                minimum_stock, supplier_name, supplier_ruc, lot_number, location, cost,
                related_item_code, related_product_type, status, notes, created_by, updated_at
            ) VALUES (
                :internal_code, :material_type, :description, :unit, :quantity_available, :quantity_reserved,
                :minimum_stock, :supplier_name, :supplier_ruc, :lot_number, :location, :cost,
                :related_item_code, :related_product_type, :status, :notes, :created_by, CURRENT_TIMESTAMP
            )',
            $data + ['created_by' => Auth::id()]
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Insumo no encontrado.');
        }

        $data = $this->normalize($data, (float) $existing['quantity_reserved']);

        $this->execute(
            'UPDATE raw_material_inventory SET
                internal_code = :internal_code,
                material_type = :material_type,
                description = :description,
                unit = :unit,
                quantity_available = :quantity_available,
                quantity_reserved = :quantity_reserved,
                minimum_stock = :minimum_stock,
                supplier_name = :supplier_name,
                supplier_ruc = :supplier_ruc,
                lot_number = :lot_number,
                location = :location,
                cost = :cost,
                related_item_code = :related_item_code,
                related_product_type = :related_product_type,
                status = :status,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            $data + ['id' => $id]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeReservations(int $rawMaterialInventoryId): array
    {
        return $this->fetchAll(
            'SELECT
                raw_material_reservations.*,
                production_orders.production_number,
                production_order_items.item_code,
                production_order_items.description AS item_description
             FROM raw_material_reservations
             INNER JOIN production_orders ON production_orders.id = raw_material_reservations.production_order_id
             INNER JOIN production_order_items ON production_order_items.id = raw_material_reservations.production_order_item_id
             WHERE raw_material_inventory_id = :id
               AND raw_material_reservations.status = "reserved"
             ORDER BY raw_material_reservations.id DESC',
            ['id' => $rawMaterialInventoryId]
        );
    }

    public function availability(int $id): float
    {
        $row = $this->find($id);
        if (!$row) {
            throw new InvalidArgumentException('Insumo no encontrado.');
        }

        return max(0.0, (float) $row['quantity_available'] - (float) $row['quantity_reserved']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, float $minimumReserved = 0.0): array
    {
        $normalized = [
            'internal_code' => trim((string) ($data['internal_code'] ?? '')),
            'material_type' => trim((string) ($data['material_type'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'unit' => trim((string) ($data['unit'] ?? '')),
            'quantity_available' => (float) ($data['quantity_available'] ?? 0),
            'quantity_reserved' => (float) ($data['quantity_reserved'] ?? 0),
            'minimum_stock' => (float) ($data['minimum_stock'] ?? 0),
            'supplier_name' => $this->nullable($data['supplier_name'] ?? null),
            'supplier_ruc' => $this->nullable($data['supplier_ruc'] ?? null),
            'lot_number' => $this->nullable($data['lot_number'] ?? null),
            'location' => $this->nullable($data['location'] ?? null),
            'cost' => trim((string) ($data['cost'] ?? '')) === '' ? null : (float) $data['cost'],
            'related_item_code' => $this->nullable($data['related_item_code'] ?? null),
            'related_product_type' => $this->nullable($data['related_product_type'] ?? null),
            'status' => trim((string) ($data['status'] ?? 'active')) ?: 'active',
            'notes' => $this->nullable($data['notes'] ?? null),
        ];

        if ($normalized['internal_code'] === '') {
            throw new InvalidArgumentException('El código interno es obligatorio.');
        }
        if ($normalized['material_type'] === '') {
            throw new InvalidArgumentException('El tipo de insumo es obligatorio.');
        }
        if ($normalized['description'] === '') {
            throw new InvalidArgumentException('La descripción del insumo es obligatoria.');
        }
        if ($normalized['unit'] === '') {
            throw new InvalidArgumentException('La unidad es obligatoria.');
        }
        if (!in_array($normalized['status'], ['active', 'inactive', 'depleted'], true)) {
            throw new InvalidArgumentException('Estado de insumo inválido.');
        }
        foreach (['quantity_available', 'quantity_reserved', 'minimum_stock'] as $field) {
            if ((float) $normalized[$field] < 0) {
                throw new InvalidArgumentException('No se permiten cantidades negativas.');
            }
        }
        if ((float) $normalized['quantity_reserved'] < $minimumReserved - 0.0001) {
            throw new InvalidArgumentException('No se puede reducir la reserva por debajo de reservas activas.');
        }
        if ((float) $normalized['quantity_reserved'] > (float) $normalized['quantity_available'] + 0.0001) {
            throw new InvalidArgumentException('La cantidad reservada no puede superar la disponible.');
        }

        return $normalized;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
