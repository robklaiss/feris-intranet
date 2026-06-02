<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class QualityControlRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    quality_control_checks.*,
                    production_orders.production_number,
                    sewing_orders.sewing_number,
                    external_work_orders.external_work_number,
                    external_work_receipts.receipt_number,
                    clients.name AS client_name,
                    contracts.contract_number,
                    checked_user.full_name AS checked_by_name
                FROM quality_control_checks
                INNER JOIN production_orders ON production_orders.id = quality_control_checks.production_order_id
                LEFT JOIN sewing_orders ON sewing_orders.id = quality_control_checks.sewing_order_id
                LEFT JOIN external_work_orders ON external_work_orders.id = quality_control_checks.external_work_order_id
                LEFT JOIN external_work_receipts ON external_work_receipts.id = quality_control_checks.external_work_receipt_id
                LEFT JOIN clients ON clients.id = production_orders.client_id
                LEFT JOIN contracts ON contracts.id = production_orders.contract_id
                LEFT JOIN users AS checked_user ON checked_user.id = quality_control_checks.checked_by
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                quality_control_checks.qc_number LIKE :q
                OR production_orders.production_number LIKE :q
                OR sewing_orders.sewing_number LIKE :q
                OR external_work_orders.external_work_number LIKE :q
                OR external_work_receipts.receipt_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'production_order_id', 'sewing_order_id', 'external_work_order_id', 'external_work_receipt_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND quality_control_checks.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY quality_control_checks.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                quality_control_checks.*,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                production_orders.contract_id,
                production_orders.client_id,
                production_orders.production_stage,
                sewing_orders.sewing_number,
                sewing_orders.seamster_id,
                seamsters.name AS seamster_name,
                external_work_orders.external_work_number,
                external_work_receipts.receipt_number,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number,
                checked_user.full_name AS checked_by_name
             FROM quality_control_checks
             INNER JOIN production_orders ON production_orders.id = quality_control_checks.production_order_id
             LEFT JOIN sewing_orders ON sewing_orders.id = quality_control_checks.sewing_order_id
             LEFT JOIN seamsters ON seamsters.id = sewing_orders.seamster_id
             LEFT JOIN external_work_orders ON external_work_orders.id = quality_control_checks.external_work_order_id
             LEFT JOIN external_work_receipts ON external_work_receipts.id = quality_control_checks.external_work_receipt_id
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             LEFT JOIN users AS checked_user ON checked_user.id = quality_control_checks.checked_by
             WHERE quality_control_checks.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithDetails(int $id): ?array
    {
        $check = $this->find($id);
        if (!$check) {
            return null;
        }

        $check['items'] = $this->items($id);
        $check['rework_orders'] = (new QualityReworkRepository())->search(['quality_control_check_id' => $id]);

        return $check;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $qualityControlCheckId): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM quality_control_check_items
             WHERE quality_control_check_id = :id
             ORDER BY id',
            ['id' => $qualityControlCheckId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDraftContextFromSewingOrder(int $sewingOrderId): array
    {
        $order = (new SewingOrderRepository())->findWithDetails($sewingOrderId);
        if (!$order) {
            throw new InvalidArgumentException('Orden de confección no encontrada.');
        }
        if (!in_array((string) $order['status'], ['completed', 'closed'], true)) {
            throw new InvalidArgumentException('Solo se puede crear control de calidad desde confección completed o closed.');
        }

        $order['items'] = $this->availableItemsFromSewingOrder($sewingOrderId);
        $order['quality_checks'] = $this->search(['sewing_order_id' => $sewingOrderId]);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDraftContextFromExternalReceipt(int $receiptId): array
    {
        $receipt = $this->externalReceiptContext($receiptId);
        if (!$receipt) {
            throw new InvalidArgumentException('Recepción externa no encontrada.');
        }
        if ((string) $receipt['status'] !== 'confirmed' || (string) $receipt['next_stage'] !== 'quality_control') {
            throw new InvalidArgumentException('Solo se puede crear control de calidad desde retorno externo confirmado con next_stage quality_control.');
        }

        $receipt['items'] = $this->availableItemsFromExternalReceipt($receiptId);
        $receipt['quality_checks'] = $this->search(['external_work_receipt_id' => $receiptId]);

        return $receipt;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableItemsFromSewingOrder(int $sewingOrderId): array
    {
        $order = (new SewingOrderRepository())->findWithDetails($sewingOrderId);
        if (!$order || !in_array((string) $order['status'], ['completed', 'closed'], true)) {
            return [];
        }

        $items = $this->fetchAll(
            'SELECT
                sewing_order_items.*,
                sewing_orders.production_order_id,
                sewing_orders.sewing_number,
                sewing_orders.seamster_id,
                production_orders.production_number,
                clients.name AS client_name,
                contracts.contract_number
             FROM sewing_order_items
             INNER JOIN sewing_orders ON sewing_orders.id = sewing_order_items.sewing_order_id
             INNER JOIN production_orders ON production_orders.id = sewing_orders.production_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             WHERE sewing_order_items.sewing_order_id = :id
               AND sewing_order_items.quantity_completed > 0
             ORDER BY sewing_order_items.id',
            ['id' => $sewingOrderId]
        );

        foreach ($items as &$item) {
            $item['quantity_already_checked'] = $this->checkedQuantityForSewingItem((int) $item['id']);
            $item['quantity_available_to_quality'] = max(0.0, (float) $item['quantity_completed'] - (float) $item['quantity_already_checked']);
            $item['source_type'] = 'sewing';
        }
        unset($item);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => (float) $item['quantity_available_to_quality'] > 0.0001
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableItemsFromExternalReceipt(int $receiptId): array
    {
        $receipt = $this->externalReceiptContext($receiptId);
        if (!$receipt || (string) $receipt['status'] !== 'confirmed' || (string) $receipt['next_stage'] !== 'quality_control') {
            return [];
        }

        $items = $this->fetchAll(
            'SELECT
                external_work_receipt_items.*,
                external_work_order_items.cutting_order_item_id,
                external_work_order_items.production_order_item_id,
                external_work_order_items.contract_item_spec_id,
                external_work_order_items.item_code,
                external_work_order_items.product_type,
                external_work_order_items.description,
                external_work_order_items.size,
                external_work_order_items.color,
                cutting_order_items.unit,
                external_work_orders.production_order_id,
                external_work_orders.external_work_number,
                production_orders.production_number,
                clients.name AS client_name,
                contracts.contract_number
             FROM external_work_receipt_items
             INNER JOIN external_work_order_items ON external_work_order_items.id = external_work_receipt_items.external_work_order_item_id
             INNER JOIN external_work_receipts ON external_work_receipts.id = external_work_receipt_items.external_work_receipt_id
             INNER JOIN external_work_orders ON external_work_orders.id = external_work_receipts.external_work_order_id
             INNER JOIN production_orders ON production_orders.id = external_work_orders.production_order_id
             LEFT JOIN cutting_order_items ON cutting_order_items.id = external_work_order_items.cutting_order_item_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             WHERE external_work_receipt_items.external_work_receipt_id = :id
               AND external_work_receipt_items.next_stage = "quality_control"
               AND external_work_receipt_items.status IN ("accepted", "partial")
               AND external_work_receipt_items.quantity_accepted > 0
             ORDER BY external_work_receipt_items.id',
            ['id' => $receiptId]
        );

        foreach ($items as &$item) {
            $item['quantity_already_checked'] = $this->checkedQuantityForExternalReceiptItem((int) $item['id']);
            $item['quantity_available_to_quality'] = max(0.0, (float) $item['quantity_accepted'] - (float) $item['quantity_already_checked']);
            $item['source_type'] = 'external_receipt';
        }
        unset($item);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => (float) $item['quantity_available_to_quality'] > 0.0001
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createFromSewingOrder(int $sewingOrderId, array $data, array $items): int
    {
        $order = $this->buildDraftContextFromSewingOrder($sewingOrderId);
        $header = $this->normalizeHeader(
            $data,
            (int) $order['production_order_id'],
            $sewingOrderId,
            $order['external_work_order_id'] ? (int) $order['external_work_order_id'] : null,
            $order['external_work_receipt_id'] ? (int) $order['external_work_receipt_id'] : null
        );
        $normalized = $this->normalizeSewingItems($sewingOrderId, $items);

        return $this->create($header, $normalized);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createFromExternalReceipt(int $receiptId, array $data, array $items): int
    {
        $receipt = $this->buildDraftContextFromExternalReceipt($receiptId);
        $header = $this->normalizeHeader(
            $data,
            (int) $receipt['production_order_id'],
            null,
            (int) $receipt['external_work_order_id'],
            $receiptId
        );
        $normalized = $this->normalizeExternalReceiptItems($receiptId, $items);

        return $this->create($header, $normalized);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    public function registerResults(int $id, array $entries, ?string $notes = null): void
    {
        $check = $this->findWithDetails($id);
        if (!$check) {
            throw new InvalidArgumentException('Control de calidad no encontrado.');
        }
        if ((string) $check['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se pueden registrar resultados en un control de calidad draft.');
        }

        $normalized = $this->normalizeResultEntries($check, $entries);
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            foreach ($normalized as $entry) {
                $this->execute(
                    'UPDATE quality_control_check_items
                     SET quantity_approved = :quantity_approved,
                         quantity_rejected = :quantity_rejected,
                         quantity_rework = :quantity_rework,
                         model_ok = :model_ok,
                         size_ok = :size_ok,
                         quantity_ok = :quantity_ok,
                         sewing_ok = :sewing_ok,
                         finishing_ok = :finishing_ok,
                         notes = :notes,
                         status = :status,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id',
                    [
                        'id' => $entry['item']['id'],
                        'quantity_approved' => $entry['quantity_approved'],
                        'quantity_rejected' => $entry['quantity_rejected'],
                        'quantity_rework' => $entry['quantity_rework'],
                        'model_ok' => $entry['model_ok'],
                        'size_ok' => $entry['size_ok'],
                        'quantity_ok' => $entry['quantity_ok'],
                        'sewing_ok' => $entry['sewing_ok'],
                        'finishing_ok' => $entry['finishing_ok'],
                        'notes' => $this->nullable($entry['notes'] ?? null),
                        'status' => $entry['status'],
                    ]
                );
            }
            $this->execute(
                'UPDATE quality_control_checks
                 SET checked_by = COALESCE(checked_by, :user_id),
                     checked_at = COALESCE(checked_at, CURRENT_TIMESTAMP),
                     notes = COALESCE(:notes, notes),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $id, 'user_id' => Auth::id(), 'notes' => $this->nullable($notes)]
            );
            $pdo->commit();
            $this->audit('register_quality_control_results', (string) $check['status'], 'draft', $this->findWithDetails($id), $id);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function confirm(int $id): void
    {
        $check = $this->findWithDetails($id);
        if (!$check) {
            throw new InvalidArgumentException('Control de calidad no encontrado.');
        }
        if ((string) $check['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede confirmar un control de calidad draft.');
        }
        if (($check['items'] ?? []) === []) {
            throw new InvalidArgumentException('El control de calidad no tiene ítems.');
        }

        $this->assertReadyToConfirm($check);
        $nextStatus = $this->checkStatus($check['items']);

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'UPDATE quality_control_checks
                 SET status = :status,
                     confirmed_by = :user_id,
                     confirmed_at = CURRENT_TIMESTAMP,
                     checked_by = COALESCE(checked_by, :user_id),
                     checked_at = COALESCE(checked_at, CURRENT_TIMESTAMP),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $id, 'status' => $nextStatus, 'user_id' => Auth::id()]
            );

            foreach ($check['items'] as $item) {
                if ((float) $item['quantity_rework'] > 0.0001) {
                    $this->createReworkOrder($check, $item);
                }
            }

            if ($nextStatus === 'rework_required') {
                $this->execute(
                    'UPDATE production_orders
                     SET production_stage = "rework_required", updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND status = "confirmed"',
                    ['id' => $check['production_order_id']]
                );
            }

            $pdo->commit();
            $this->audit('confirm_quality_control_check', (string) $check['status'], $nextStatus, $this->findWithDetails($id), $id);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancelDraft(int $id): void
    {
        $check = $this->find($id);
        if (!$check) {
            throw new InvalidArgumentException('Control de calidad no encontrado.');
        }
        if ((string) $check['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede cancelar un control de calidad draft.');
        }

        $this->execute(
            'UPDATE quality_control_checks
             SET status = "cancelled",
                 cancelled_by = :user_id,
                 cancelled_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->audit('cancel_quality_control_check', (string) $check['status'], 'cancelled', $this->findWithDetails($id), $id);
    }

    public function close(int $id): void
    {
        $check = $this->findWithDetails($id);
        if (!$check) {
            throw new InvalidArgumentException('Control de calidad no encontrado.');
        }
        if (!in_array((string) $check['status'], ['approved', 'partially_approved', 'rejected', 'rework_required'], true)) {
            throw new InvalidArgumentException('Solo se puede cerrar un control de calidad aprobado, rechazado o con reproceso requerido.');
        }
        if ((string) $check['status'] === 'rework_required' && $this->hasOpenReworkOrders($id)) {
            throw new InvalidArgumentException('No se puede cerrar un control con reprocesos abiertos.');
        }

        $this->execute(
            'UPDATE quality_control_checks
             SET status = "closed",
                 closed_by = :user_id,
                 closed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->audit('close_quality_control_check', (string) $check['status'], 'closed', $this->findWithDetails($id), $id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byProductionOrder(int $productionOrderId): array
    {
        return $this->search(['production_order_id' => $productionOrderId]);
    }

    /**
     * @param array<string, mixed> $header
     * @param array<int, array<string, mixed>> $items
     */
    private function create(array $header, array $items): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO quality_control_checks (
                    production_order_id, sewing_order_id, external_work_order_id, external_work_receipt_id,
                    qc_number, status, notes, created_by, updated_at
                ) VALUES (
                    :production_order_id, :sewing_order_id, :external_work_order_id, :external_work_receipt_id,
                    :qc_number, "draft", :notes, :created_by, CURRENT_TIMESTAMP
                )',
                $header + ['created_by' => Auth::id()]
            );
            $id = (int) $pdo->lastInsertId();
            foreach ($items as $item) {
                $this->insertItem($id, $item);
            }
            $pdo->commit();
            $this->audit('create_quality_control_check', null, 'draft', $this->findWithDetails($id), $id);

            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeHeader(array $data, int $productionOrderId, ?int $sewingOrderId, ?int $externalWorkOrderId, ?int $externalWorkReceiptId): array
    {
        $number = trim((string) ($data['qc_number'] ?? ''));
        if ($number === '') {
            $number = $this->nextQcNumber($productionOrderId);
        }

        return [
            'production_order_id' => $productionOrderId,
            'sewing_order_id' => $sewingOrderId,
            'external_work_order_id' => $externalWorkOrderId,
            'external_work_receipt_id' => $externalWorkReceiptId,
            'qc_number' => $number,
            'notes' => $this->nullable($data['notes'] ?? null),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSewingItems(int $sewingOrderId, array $items): array
    {
        $sourceItems = $this->availableItemsFromSewingOrder($sewingOrderId);
        $sourceById = [];
        foreach ($sourceItems as $source) {
            $sourceById[(int) $source['id']] = $source;
        }

        if ($items === []) {
            foreach ($sourceItems as $source) {
                $items[] = [
                    'sewing_order_item_id' => (int) $source['id'],
                    'quantity_received' => (float) $source['quantity_available_to_quality'],
                    'notes' => '',
                ];
            }
        }
        if ($items === []) {
            throw new InvalidArgumentException('No hay saldo confeccionado pendiente de control de calidad.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $sourceId = (int) ($item['sewing_order_item_id'] ?? 0);
            if (!isset($sourceById[$sourceId])) {
                throw new InvalidArgumentException('El ítem de confección no está disponible para control de calidad.');
            }
            $source = $sourceById[$sourceId];
            $quantity = (float) ($item['quantity_received'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('quantity_received debe ser mayor a cero.');
            }
            if ($quantity > (float) $source['quantity_available_to_quality'] + 0.0001) {
                throw new InvalidArgumentException('quantity_received supera el saldo pendiente de calidad para ' . (string) $source['item_code'] . '.');
            }

            $normalized[] = [
                'production_order_item_id' => (int) $source['production_order_item_id'],
                'sewing_order_item_id' => $sourceId,
                'external_work_receipt_item_id' => null,
                'contract_item_spec_id' => $source['contract_item_spec_id'] ?: null,
                'item_code' => $source['item_code'],
                'product_type' => $source['product_type'],
                'description' => $source['description'],
                'size' => $source['size'],
                'color' => $source['color'],
                'quantity_received' => $quantity,
                'notes' => $this->nullable($item['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeExternalReceiptItems(int $receiptId, array $items): array
    {
        $sourceItems = $this->availableItemsFromExternalReceipt($receiptId);
        $sourceById = [];
        foreach ($sourceItems as $source) {
            $sourceById[(int) $source['id']] = $source;
        }

        if ($items === []) {
            foreach ($sourceItems as $source) {
                $items[] = [
                    'external_work_receipt_item_id' => (int) $source['id'],
                    'quantity_received' => (float) $source['quantity_available_to_quality'],
                    'notes' => '',
                ];
            }
        }
        if ($items === []) {
            throw new InvalidArgumentException('No hay saldo aceptado pendiente de control de calidad.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $sourceId = (int) ($item['external_work_receipt_item_id'] ?? 0);
            if (!isset($sourceById[$sourceId])) {
                throw new InvalidArgumentException('El ítem de recepción externa no está disponible para control de calidad.');
            }
            $source = $sourceById[$sourceId];
            $quantity = (float) ($item['quantity_received'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('quantity_received debe ser mayor a cero.');
            }
            if ($quantity > (float) $source['quantity_available_to_quality'] + 0.0001) {
                throw new InvalidArgumentException('quantity_received supera el saldo aceptado pendiente de calidad para ' . (string) $source['item_code'] . '.');
            }

            $normalized[] = [
                'production_order_item_id' => (int) $source['production_order_item_id'],
                'sewing_order_item_id' => null,
                'external_work_receipt_item_id' => $sourceId,
                'contract_item_spec_id' => $source['contract_item_spec_id'] ?: null,
                'item_code' => $source['item_code'],
                'product_type' => $source['product_type'],
                'description' => $source['description'],
                'size' => $source['size'],
                'color' => $source['color'],
                'quantity_received' => $quantity,
                'notes' => $this->nullable($item['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function insertItem(int $qualityControlCheckId, array $item): void
    {
        $this->execute(
            'INSERT INTO quality_control_check_items (
                quality_control_check_id, production_order_item_id, sewing_order_item_id, external_work_receipt_item_id,
                contract_item_spec_id, item_code, product_type, description, size, color, quantity_received,
                quantity_approved, quantity_rejected, quantity_rework, model_ok, size_ok, quantity_ok,
                sewing_ok, finishing_ok, notes, status, updated_at
            ) VALUES (
                :quality_control_check_id, :production_order_item_id, :sewing_order_item_id, :external_work_receipt_item_id,
                :contract_item_spec_id, :item_code, :product_type, :description, :size, :color, :quantity_received,
                0, 0, 0, 0, 0, 0, 0, 0, :notes, "pending", CURRENT_TIMESTAMP
            )',
            $item + ['quality_control_check_id' => $qualityControlCheckId]
        );
    }

    /**
     * @param array<string, mixed> $check
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResultEntries(array $check, array $entries): array
    {
        $items = [];
        foreach (($check['items'] ?? []) as $item) {
            $items[(int) $item['id']] = $item;
        }
        if ($entries === []) {
            throw new InvalidArgumentException('Debe registrar al menos un resultado de calidad.');
        }

        $normalized = [];
        foreach ($entries as $entry) {
            $itemId = (int) ($entry['quality_control_check_item_id'] ?? 0);
            if (!isset($items[$itemId])) {
                throw new InvalidArgumentException('El ítem de calidad no pertenece al control.');
            }
            $approved = (float) ($entry['quantity_approved'] ?? 0);
            $rejected = (float) ($entry['quantity_rejected'] ?? 0);
            $rework = (float) ($entry['quantity_rework'] ?? 0);
            if ($approved < 0 || $rejected < 0 || $rework < 0) {
                throw new InvalidArgumentException('No se permiten cantidades negativas en calidad.');
            }
            $item = $items[$itemId];
            if ($approved + $rejected + $rework > (float) $item['quantity_received'] + 0.0001) {
                throw new InvalidArgumentException('quantity_approved + quantity_rejected + quantity_rework no puede superar quantity_received para ' . (string) $item['item_code'] . '.');
            }

            $normalized[] = [
                'item' => $item,
                'quantity_approved' => $approved,
                'quantity_rejected' => $rejected,
                'quantity_rework' => $rework,
                'model_ok' => $this->boolValue($entry['model_ok'] ?? 0),
                'size_ok' => $this->boolValue($entry['size_ok'] ?? 0),
                'quantity_ok' => $this->boolValue($entry['quantity_ok'] ?? 0),
                'sewing_ok' => $this->boolValue($entry['sewing_ok'] ?? 0),
                'finishing_ok' => $this->boolValue($entry['finishing_ok'] ?? 0),
                'notes' => $this->nullable($entry['notes'] ?? null),
                'status' => $this->itemStatus($approved, $rejected, $rework, (float) $item['quantity_received']),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $check
     */
    private function assertReadyToConfirm(array $check): void
    {
        foreach (($check['items'] ?? []) as $item) {
            $used = (float) $item['quantity_approved'] + (float) $item['quantity_rejected'] + (float) $item['quantity_rework'];
            if ($used <= 0.0001) {
                throw new InvalidArgumentException('Debe registrar resultado de calidad para ' . (string) $item['item_code'] . '.');
            }
            if ($used > (float) $item['quantity_received'] + 0.0001) {
                throw new InvalidArgumentException('Las cantidades de calidad superan lo recibido para ' . (string) $item['item_code'] . '.');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function checkStatus(array $items): string
    {
        $received = 0.0;
        $approved = 0.0;
        $rejected = 0.0;
        $rework = 0.0;

        foreach ($items as $item) {
            $received += (float) $item['quantity_received'];
            $approved += (float) $item['quantity_approved'];
            $rejected += (float) $item['quantity_rejected'];
            $rework += (float) $item['quantity_rework'];
        }

        if ($rework > 0.0001) {
            return 'rework_required';
        }
        if ($approved >= $received - 0.0001) {
            return 'approved';
        }
        if ($rejected >= $received - 0.0001) {
            return 'rejected';
        }

        return 'partially_approved';
    }

    private function itemStatus(float $approved, float $rejected, float $rework, float $received): string
    {
        $used = $approved + $rejected + $rework;
        if ($used < $received - 0.0001) {
            return 'partial';
        }
        if ($rework > 0.0001 && $approved <= 0.0001 && $rejected <= 0.0001) {
            return 'rework_required';
        }
        if ($approved >= $received - 0.0001) {
            return 'approved';
        }
        if ($rejected >= $received - 0.0001) {
            return 'rejected';
        }
        if ($rework > 0.0001) {
            return 'rework_required';
        }

        return 'partial';
    }

    /**
     * @param array<string, mixed> $check
     * @param array<string, mixed> $item
     */
    private function createReworkOrder(array $check, array $item): void
    {
        $exists = $this->fetchOne(
            'SELECT id FROM quality_rework_orders WHERE quality_control_check_item_id = :id LIMIT 1',
            ['id' => $item['id']]
        );
        if ($exists) {
            return;
        }

        $reason = trim((string) ($item['notes'] ?? ''));
        if ($reason === '') {
            $reason = 'Reproceso requerido por control de calidad.';
        }

        $this->execute(
            'INSERT INTO quality_rework_orders (
                quality_control_check_id, quality_control_check_item_id, production_order_id, sewing_order_id,
                seamster_id, rework_number, status, reason, quantity, assigned_to, due_date, notes, created_by, updated_at
            ) VALUES (
                :quality_control_check_id, :quality_control_check_item_id, :production_order_id, :sewing_order_id,
                :seamster_id, :rework_number, "draft", :reason, :quantity, :assigned_to, NULL, :notes, :created_by, CURRENT_TIMESTAMP
            )',
            [
                'quality_control_check_id' => $check['id'],
                'quality_control_check_item_id' => $item['id'],
                'production_order_id' => $check['production_order_id'],
                'sewing_order_id' => $check['sewing_order_id'] ?: null,
                'seamster_id' => $check['seamster_id'] ?? null,
                'rework_number' => $this->nextReworkNumber((int) $check['id']),
                'reason' => $reason,
                'quantity' => $item['quantity_rework'],
                'assigned_to' => $check['seamster_name'] ?? null,
                'notes' => $item['notes'] ?? null,
                'created_by' => Auth::id(),
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function externalReceiptContext(int $receiptId): ?array
    {
        return $this->fetchOne(
            'SELECT
                external_work_receipts.*,
                external_work_orders.id AS external_work_order_id,
                external_work_orders.external_work_number,
                external_work_orders.production_order_id,
                external_work_orders.cutting_order_id,
                cutting_orders.cutting_number,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                production_orders.contract_id,
                production_orders.client_id,
                production_orders.production_stage,
                clients.name AS client_name,
                contracts.contract_number
             FROM external_work_receipts
             INNER JOIN external_work_orders ON external_work_orders.id = external_work_receipts.external_work_order_id
             INNER JOIN cutting_orders ON cutting_orders.id = external_work_orders.cutting_order_id
             INNER JOIN production_orders ON production_orders.id = external_work_orders.production_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             WHERE external_work_receipts.id = :id',
            ['id' => $receiptId]
        );
    }

    private function checkedQuantityForSewingItem(int $sewingOrderItemId): float
    {
        return (float) ($this->fetchOne(
            'SELECT COALESCE(SUM(quality_control_check_items.quantity_received), 0) AS total
             FROM quality_control_check_items
             INNER JOIN quality_control_checks ON quality_control_checks.id = quality_control_check_items.quality_control_check_id
             WHERE quality_control_check_items.sewing_order_item_id = :id
               AND quality_control_checks.status != "cancelled"',
            ['id' => $sewingOrderItemId]
        )['total'] ?? 0);
    }

    private function checkedQuantityForExternalReceiptItem(int $receiptItemId): float
    {
        return (float) ($this->fetchOne(
            'SELECT COALESCE(SUM(quality_control_check_items.quantity_received), 0) AS total
             FROM quality_control_check_items
             INNER JOIN quality_control_checks ON quality_control_checks.id = quality_control_check_items.quality_control_check_id
             WHERE quality_control_check_items.external_work_receipt_item_id = :id
               AND quality_control_checks.status != "cancelled"',
            ['id' => $receiptItemId]
        )['total'] ?? 0);
    }

    private function hasOpenReworkOrders(int $qualityControlCheckId): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total
             FROM quality_rework_orders
             WHERE quality_control_check_id = :id
               AND status IN ("draft", "assigned")',
            ['id' => $qualityControlCheckId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    private function nextQcNumber(int $productionOrderId): string
    {
        $row = $this->fetchOne('SELECT production_number FROM production_orders WHERE id = :id', ['id' => $productionOrderId]);
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM quality_control_checks WHERE production_order_id = :id',
            ['id' => $productionOrderId]
        )['total'] ?? 0);

        return sprintf('QC-%s-%02d', (string) ($row['production_number'] ?? $productionOrderId), $count + 1);
    }

    private function nextReworkNumber(int $qualityControlCheckId): string
    {
        $row = $this->fetchOne('SELECT qc_number FROM quality_control_checks WHERE id = :id', ['id' => $qualityControlCheckId]);
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM quality_rework_orders WHERE quality_control_check_id = :id',
            ['id' => $qualityControlCheckId]
        )['total'] ?? 0);

        return sprintf('REP-%s-%02d', (string) ($row['qc_number'] ?? $qualityControlCheckId), $count + 1);
    }

    private function boolValue(mixed $value): int
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $check
     */
    private function audit(string $action, ?string $previous, string $next, ?array $check, int $qualityControlCheckId): void
    {
        $items = is_array($check['items'] ?? null) ? $check['items'] : [];
        $firstItem = $items[0] ?? [];

        (new OperationalAuditService())->logDocumentAction(
            'quality_control_checks',
            $qualityControlCheckId,
            (string) ($check['qc_number'] ?? $qualityControlCheckId),
            $action,
            $previous,
            $next,
            [
                'quality_control_check_id' => $qualityControlCheckId,
                'qc_number' => $check['qc_number'] ?? null,
                'production_order_id' => $check['production_order_id'] ?? null,
                'sewing_order_id' => $check['sewing_order_id'] ?? null,
                'external_work_order_id' => $check['external_work_order_id'] ?? null,
                'external_work_receipt_id' => $check['external_work_receipt_id'] ?? null,
                'item_code' => $firstItem['item_code'] ?? null,
                'quantity_received' => $firstItem['quantity_received'] ?? null,
                'quantity_approved' => $firstItem['quantity_approved'] ?? null,
                'quantity_rejected' => $firstItem['quantity_rejected'] ?? null,
                'quantity_rework' => $firstItem['quantity_rework'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
                'item_count' => count($items),
            ]
        );
    }
}
