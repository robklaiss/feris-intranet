<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use InvalidArgumentException;

final class QualityReworkRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    quality_rework_orders.*,
                    quality_control_checks.qc_number,
                    quality_control_check_items.item_code,
                    quality_control_check_items.description,
                    quality_control_check_items.size,
                    quality_control_check_items.color,
                    production_orders.production_number,
                    sewing_orders.sewing_number,
                    seamsters.name AS seamster_name,
                    clients.name AS client_name,
                    contracts.contract_number
                FROM quality_rework_orders
                INNER JOIN quality_control_checks ON quality_control_checks.id = quality_rework_orders.quality_control_check_id
                INNER JOIN quality_control_check_items ON quality_control_check_items.id = quality_rework_orders.quality_control_check_item_id
                INNER JOIN production_orders ON production_orders.id = quality_rework_orders.production_order_id
                LEFT JOIN sewing_orders ON sewing_orders.id = quality_rework_orders.sewing_order_id
                LEFT JOIN seamsters ON seamsters.id = quality_rework_orders.seamster_id
                LEFT JOIN clients ON clients.id = production_orders.client_id
                LEFT JOIN contracts ON contracts.id = production_orders.contract_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                quality_rework_orders.rework_number LIKE :q
                OR quality_control_checks.qc_number LIKE :q
                OR quality_control_check_items.item_code LIKE :q
                OR production_orders.production_number LIKE :q
                OR sewing_orders.sewing_number LIKE :q
                OR seamsters.name LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'production_order_id', 'sewing_order_id', 'seamster_id', 'quality_control_check_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND quality_rework_orders.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY quality_rework_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                quality_rework_orders.*,
                quality_control_checks.qc_number,
                quality_control_check_items.item_code,
                quality_control_check_items.description,
                quality_control_check_items.size,
                quality_control_check_items.color,
                production_orders.production_number,
                sewing_orders.sewing_number,
                seamsters.name AS seamster_name,
                clients.name AS client_name,
                contracts.contract_number
             FROM quality_rework_orders
             INNER JOIN quality_control_checks ON quality_control_checks.id = quality_rework_orders.quality_control_check_id
             INNER JOIN quality_control_check_items ON quality_control_check_items.id = quality_rework_orders.quality_control_check_item_id
             INNER JOIN production_orders ON production_orders.id = quality_rework_orders.production_order_id
             LEFT JOIN sewing_orders ON sewing_orders.id = quality_rework_orders.sewing_order_id
             LEFT JOIN seamsters ON seamsters.id = quality_rework_orders.seamster_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             WHERE quality_rework_orders.id = :id',
            ['id' => $id]
        );
    }

    public function complete(int $id, ?string $notes = null): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de reproceso no encontrada.');
        }
        if (!in_array((string) $order['status'], ['draft', 'assigned'], true)) {
            throw new InvalidArgumentException('Solo se puede completar un reproceso draft o assigned.');
        }

        $this->execute(
            'UPDATE quality_rework_orders
             SET status = "completed",
                 completed_at = CURRENT_TIMESTAMP,
                 notes = COALESCE(:notes, notes),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'notes' => $this->nullable($notes)]
        );
        $this->audit('complete_quality_rework_order', (string) $order['status'], 'completed', $this->find($id), $id);
    }

    public function close(int $id): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de reproceso no encontrada.');
        }
        if ((string) $order['status'] !== 'completed') {
            throw new InvalidArgumentException('Solo se puede cerrar un reproceso completado.');
        }

        $this->execute(
            'UPDATE quality_rework_orders SET status = "closed", updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $id]
        );
        $this->audit('close_quality_rework_order', (string) $order['status'], 'closed', $this->find($id), $id);
    }

    public function cancel(int $id): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de reproceso no encontrada.');
        }
        if (!in_array((string) $order['status'], ['draft', 'assigned'], true)) {
            throw new InvalidArgumentException('Solo se puede anular un reproceso draft o assigned.');
        }

        $this->execute(
            'UPDATE quality_rework_orders SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $id]
        );
        $this->audit('cancel_quality_rework_order', (string) $order['status'], 'cancelled', $this->find($id), $id);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $order
     */
    private function audit(string $action, ?string $previous, string $next, ?array $order, int $reworkOrderId): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'quality_rework_orders',
            $reworkOrderId,
            (string) ($order['rework_number'] ?? $reworkOrderId),
            $action,
            $previous,
            $next,
            [
                'quality_rework_order_id' => $reworkOrderId,
                'quality_control_check_id' => $order['quality_control_check_id'] ?? null,
                'quality_control_check_item_id' => $order['quality_control_check_item_id'] ?? null,
                'production_order_id' => $order['production_order_id'] ?? null,
                'sewing_order_id' => $order['sewing_order_id'] ?? null,
                'seamster_id' => $order['seamster_id'] ?? null,
                'item_code' => $order['item_code'] ?? null,
                'quantity' => $order['quantity'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
            ]
        );
    }
}
