<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class SewingOrderRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    sewing_orders.*,
                    seamsters.name AS seamster_name,
                    production_orders.production_number,
                    cutting_orders.cutting_number,
                    external_work_orders.external_work_number,
                    external_work_receipts.receipt_number,
                    clients.name AS client_name,
                    contracts.contract_number
                FROM sewing_orders
                INNER JOIN seamsters ON seamsters.id = sewing_orders.seamster_id
                INNER JOIN production_orders ON production_orders.id = sewing_orders.production_order_id
                LEFT JOIN cutting_orders ON cutting_orders.id = sewing_orders.cutting_order_id
                LEFT JOIN external_work_orders ON external_work_orders.id = sewing_orders.external_work_order_id
                LEFT JOIN external_work_receipts ON external_work_receipts.id = sewing_orders.external_work_receipt_id
                LEFT JOIN clients ON clients.id = production_orders.client_id
                LEFT JOIN contracts ON contracts.id = production_orders.contract_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                sewing_orders.sewing_number LIKE :q
                OR seamsters.name LIKE :q
                OR production_orders.production_number LIKE :q
                OR cutting_orders.cutting_number LIKE :q
                OR external_work_orders.external_work_number LIKE :q
                OR external_work_receipts.receipt_number LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'seamster_id', 'production_order_id', 'cutting_order_id', 'external_work_order_id', 'external_work_receipt_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND sewing_orders.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY sewing_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                sewing_orders.*,
                seamsters.name AS seamster_name,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                production_orders.contract_id,
                production_orders.client_id,
                production_orders.production_stage,
                cutting_orders.cutting_number,
                external_work_orders.external_work_number,
                external_work_receipts.receipt_number,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number
             FROM sewing_orders
             INNER JOIN seamsters ON seamsters.id = sewing_orders.seamster_id
             INNER JOIN production_orders ON production_orders.id = sewing_orders.production_order_id
             LEFT JOIN cutting_orders ON cutting_orders.id = sewing_orders.cutting_order_id
             LEFT JOIN external_work_orders ON external_work_orders.id = sewing_orders.external_work_order_id
             LEFT JOIN external_work_receipts ON external_work_receipts.id = sewing_orders.external_work_receipt_id
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             WHERE sewing_orders.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithDetails(int $id): ?array
    {
        $order = $this->find($id);
        if (!$order) {
            return null;
        }

        $order['items'] = $this->items($id);
        $order['progress_entries'] = $this->progressEntries($id);

        return $order;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byCuttingOrder(int $cuttingOrderId): array
    {
        return $this->search(['cutting_order_id' => $cuttingOrderId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byExternalWorkOrder(int $externalWorkOrderId): array
    {
        return $this->search(['external_work_order_id' => $externalWorkOrderId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $sewingOrderId): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM sewing_order_items
             WHERE sewing_order_id = :id
             ORDER BY id',
            ['id' => $sewingOrderId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function progressEntries(int $sewingOrderId): array
    {
        return $this->fetchAll(
            'SELECT
                sewing_progress_entries.*,
                sewing_order_items.item_code,
                sewing_order_items.size,
                sewing_order_items.color,
                users.full_name AS created_by_name
             FROM sewing_progress_entries
             LEFT JOIN sewing_order_items ON sewing_order_items.id = sewing_progress_entries.sewing_order_item_id
             LEFT JOIN users ON users.id = sewing_progress_entries.created_by
             WHERE sewing_progress_entries.sewing_order_id = :id
             ORDER BY sewing_progress_entries.progress_date DESC, sewing_progress_entries.id DESC',
            ['id' => $sewingOrderId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDraftContextFromCuttingOrder(int $cuttingOrderId): array
    {
        $cuttingOrder = (new CuttingOrderRepository())->find($cuttingOrderId);
        if (!$cuttingOrder) {
            throw new InvalidArgumentException('Orden de corte no encontrada.');
        }
        if (!in_array((string) $cuttingOrder['status'], ['completed', 'closed'], true)) {
            throw new InvalidArgumentException('Solo se puede crear confección desde corte completed o closed.');
        }

        $cuttingOrder['items'] = $this->availableItemsFromCuttingOrder($cuttingOrderId);
        $cuttingOrder['sewing_orders'] = $this->byCuttingOrder($cuttingOrderId);

        return $cuttingOrder;
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
        if ((string) $receipt['status'] !== 'confirmed' || (string) $receipt['next_stage'] !== 'sewing') {
            throw new InvalidArgumentException('Solo se puede crear confección desde retorno externo confirmado con next_stage sewing.');
        }

        $receipt['items'] = $this->availableItemsFromExternalReceipt($receiptId);
        $receipt['sewing_orders'] = $this->search(['external_work_receipt_id' => $receiptId]);

        return $receipt;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableItemsFromCuttingOrder(int $cuttingOrderId): array
    {
        $cuttingOrder = (new CuttingOrderRepository())->find($cuttingOrderId);
        if (!$cuttingOrder) {
            throw new InvalidArgumentException('Orden de corte no encontrada.');
        }
        if (!in_array((string) $cuttingOrder['status'], ['completed', 'closed'], true)) {
            return [];
        }

        $items = $this->fetchAll(
            'SELECT
                cutting_order_items.*,
                production_order_items.requires_embroidery,
                production_order_items.requires_screen_printing,
                contract_item_specs.has_embroidery,
                contract_item_specs.has_screen_printing
             FROM cutting_order_items
             INNER JOIN production_order_items ON production_order_items.id = cutting_order_items.production_order_item_id
             LEFT JOIN contract_item_specs ON contract_item_specs.id = cutting_order_items.contract_item_spec_id
             WHERE cutting_order_items.cutting_order_id = :id
               AND cutting_order_items.quantity_cut > 0
               AND cutting_order_items.status IN ("cut", "partial")
             ORDER BY cutting_order_items.id',
            ['id' => $cuttingOrderId]
        );

        foreach ($items as &$item) {
            $item['requires_external_work'] = $this->itemRequiresExternalWork($item) ? 1 : 0;
            $item['quantity_already_assigned'] = $this->assignedQuantityForCuttingItem((int) $item['id']);
            $item['quantity_available_to_sew'] = max(0.0, (float) $item['quantity_cut'] - (float) $item['quantity_already_assigned']);
            $item['source_type'] = 'cutting';
        }
        unset($item);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => empty($item['requires_external_work'])
                && (float) $item['quantity_available_to_sew'] > 0.0001
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableItemsFromExternalReceipt(int $receiptId): array
    {
        $receipt = $this->externalReceiptContext($receiptId);
        if (!$receipt || (string) $receipt['status'] !== 'confirmed' || (string) $receipt['next_stage'] !== 'sewing') {
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
                cutting_order_items.unit
             FROM external_work_receipt_items
             INNER JOIN external_work_order_items ON external_work_order_items.id = external_work_receipt_items.external_work_order_item_id
             LEFT JOIN cutting_order_items ON cutting_order_items.id = external_work_order_items.cutting_order_item_id
             WHERE external_work_receipt_items.external_work_receipt_id = :id
               AND external_work_receipt_items.next_stage = "sewing"
               AND external_work_receipt_items.status IN ("accepted", "partial")
               AND external_work_receipt_items.quantity_accepted > 0
             ORDER BY external_work_receipt_items.id',
            ['id' => $receiptId]
        );

        foreach ($items as &$item) {
            $item['quantity_already_assigned'] = $this->assignedQuantityForExternalReceiptItem((int) $item['id']);
            $item['quantity_available_to_sew'] = max(0.0, (float) $item['quantity_accepted'] - (float) $item['quantity_already_assigned']);
            $item['source_type'] = 'external_receipt';
        }
        unset($item);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => (float) $item['quantity_available_to_sew'] > 0.0001
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createFromCuttingOrder(int $cuttingOrderId, array $data, array $items): int
    {
        $cuttingOrder = $this->buildDraftContextFromCuttingOrder($cuttingOrderId);
        $header = $this->normalizeHeader($data, (int) $cuttingOrder['production_order_id'], $cuttingOrderId, null, null);
        $normalized = $this->normalizeCuttingItems($cuttingOrderId, $items);

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
            (int) $receipt['cutting_order_id'],
            (int) $receipt['external_work_order_id'],
            $receiptId
        );
        $normalized = $this->normalizeExternalReceiptItems($receiptId, $items);

        return $this->create($header, $normalized);
    }

    public function confirm(int $id): void
    {
        $order = $this->findWithDetails($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de confección no encontrada.');
        }
        if ((string) $order['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede confirmar una orden de confección en borrador.');
        }
        if (($order['items'] ?? []) === []) {
            throw new InvalidArgumentException('La orden de confección no tiene ítems.');
        }

        $this->execute(
            'UPDATE sewing_orders
             SET status = "confirmed",
                 assigned_at = COALESCE(assigned_at, CURRENT_TIMESTAMP),
                 confirmed_by = :user_id,
                 confirmed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->execute(
            'UPDATE production_orders
             SET production_stage = "in_sewing", updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = "confirmed"',
            ['id' => $order['production_order_id']]
        );
        $this->audit('confirm_sewing_order', (string) $order['status'], 'confirmed', $this->findWithDetails($id), $id);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    public function registerProgress(int $id, array $entries, ?string $progressDate = null, ?string $notes = null): void
    {
        $order = $this->findWithDetails($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de confección no encontrada.');
        }
        if (!in_array((string) $order['status'], ['confirmed', 'in_progress', 'partially_completed'], true)) {
            throw new InvalidArgumentException('Solo se puede registrar avance en una orden confirmada o en proceso.');
        }

        $normalized = $this->normalizeProgressEntries($order, $entries);
        $date = $this->nullable($progressDate) ?? date('Y-m-d');

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            foreach ($normalized as $entry) {
                $item = $entry['item'];
                $newCompleted = (float) $item['quantity_completed'] + (float) $entry['quantity_completed'];
                $newRejected = (float) $item['quantity_rejected'] + (float) $entry['quantity_rejected'];
                $newPending = max(0.0, (float) $item['quantity_assigned'] - $newCompleted - $newRejected);
                $itemStatus = $this->itemStatus($newCompleted, $newRejected, (float) $item['quantity_assigned']);

                $this->execute(
                    'INSERT INTO sewing_progress_entries (
                        sewing_order_id, sewing_order_item_id, quantity_completed, quantity_rejected,
                        progress_date, notes, created_by
                    ) VALUES (
                        :sewing_order_id, :sewing_order_item_id, :quantity_completed, :quantity_rejected,
                        :progress_date, :notes, :created_by
                    )',
                    [
                        'sewing_order_id' => $id,
                        'sewing_order_item_id' => $item['id'],
                        'quantity_completed' => $entry['quantity_completed'],
                        'quantity_rejected' => $entry['quantity_rejected'],
                        'progress_date' => $date,
                        'notes' => $this->nullable($entry['notes'] ?? $notes),
                        'created_by' => Auth::id(),
                    ]
                );

                $this->execute(
                    'UPDATE sewing_order_items
                     SET quantity_completed = :quantity_completed,
                         quantity_rejected = :quantity_rejected,
                         quantity_pending = :quantity_pending,
                         status = :status,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id',
                    [
                        'id' => $item['id'],
                        'quantity_completed' => $newCompleted,
                        'quantity_rejected' => $newRejected,
                        'quantity_pending' => $newPending,
                        'status' => $itemStatus,
                    ]
                );
            }

            $nextStatus = $this->orderProgressStatus($id);
            $this->execute(
                'UPDATE sewing_orders
                 SET status = :status,
                     started_at = COALESCE(started_at, CURRENT_TIMESTAMP),
                     completed_at = CASE WHEN :status = "completed" THEN CURRENT_TIMESTAMP ELSE completed_at END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $id, 'status' => $nextStatus]
            );
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = :stage, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = "confirmed"',
                [
                    'id' => $order['production_order_id'],
                    'stage' => $nextStatus === 'completed' ? 'quality_control' : 'in_sewing',
                ]
            );
            $pdo->commit();
            $this->audit('register_sewing_progress', (string) $order['status'], $nextStatus, $this->findWithDetails($id), $id);
            if ($nextStatus === 'completed') {
                $this->audit('complete_sewing_order', (string) $order['status'], 'completed', $this->findWithDetails($id), $id);
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancelDraft(int $id): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de confección no encontrada.');
        }
        if ((string) $order['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede cancelar una orden de confección en borrador. Las órdenes con avance requieren reversa auditada.');
        }
        if ($this->hasProgress($id)) {
            throw new InvalidArgumentException('No se puede cancelar una orden de confección con avances registrados.');
        }

        $this->execute(
            'UPDATE sewing_orders
             SET status = "cancelled",
                 cancelled_by = :user_id,
                 cancelled_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->execute(
            'UPDATE sewing_order_items SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE sewing_order_id = :id',
            ['id' => $id]
        );
        $this->audit('cancel_sewing_order', (string) $order['status'], 'cancelled', $this->findWithDetails($id), $id);
    }

    public function close(int $id): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de confección no encontrada.');
        }
        if ((string) $order['status'] !== 'completed') {
            throw new InvalidArgumentException('Solo se puede cerrar una orden de confección completada.');
        }

        $this->execute(
            'UPDATE sewing_orders
             SET status = "closed",
                 closed_by = :user_id,
                 closed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->audit('close_sewing_order', (string) $order['status'], 'closed', $this->findWithDetails($id), $id);
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
                'INSERT INTO sewing_orders (
                    production_order_id, cutting_order_id, external_work_order_id, external_work_receipt_id,
                    seamster_id, sewing_number, status, assigned_at, expected_completion_date, notes, created_by, updated_at
                ) VALUES (
                    :production_order_id, :cutting_order_id, :external_work_order_id, :external_work_receipt_id,
                    :seamster_id, :sewing_number, "draft", :assigned_at, :expected_completion_date, :notes, :created_by, CURRENT_TIMESTAMP
                )',
                $header + ['created_by' => Auth::id()]
            );

            $id = (int) $pdo->lastInsertId();
            foreach ($items as $item) {
                $this->insertItem($id, $item);
            }
            $pdo->commit();
            $this->audit('create_sewing_order', null, 'draft', $this->findWithDetails($id), $id);

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
    private function normalizeHeader(array $data, int $productionOrderId, ?int $cuttingOrderId, ?int $externalWorkOrderId, ?int $externalWorkReceiptId): array
    {
        $seamsterId = (int) ($data['seamster_id'] ?? 0);
        $seamster = (new SeamsterRepository())->find($seamsterId);
        if (!$seamster) {
            throw new InvalidArgumentException('Costurero no encontrado.');
        }
        if ((string) $seamster['status'] !== 'active') {
            throw new InvalidArgumentException('No se puede crear orden de confección para costurero inactive.');
        }

        $number = trim((string) ($data['sewing_number'] ?? ''));
        if ($number === '') {
            $number = $this->nextSewingNumber($productionOrderId);
        }

        return [
            'production_order_id' => $productionOrderId,
            'cutting_order_id' => $cuttingOrderId,
            'external_work_order_id' => $externalWorkOrderId,
            'external_work_receipt_id' => $externalWorkReceiptId,
            'seamster_id' => $seamsterId,
            'sewing_number' => $number,
            'assigned_at' => $this->nullable($data['assigned_at'] ?? null),
            'expected_completion_date' => $this->nullable($data['expected_completion_date'] ?? null),
            'notes' => $this->nullable($data['notes'] ?? null),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCuttingItems(int $cuttingOrderId, array $items): array
    {
        $sourceItems = $this->availableItemsFromCuttingOrder($cuttingOrderId);
        $sourceById = [];
        foreach ($sourceItems as $source) {
            $sourceById[(int) $source['id']] = $source;
        }

        if ($items === []) {
            foreach ($sourceItems as $source) {
                $items[] = [
                    'cutting_order_item_id' => (int) $source['id'],
                    'quantity_assigned' => (float) $source['quantity_available_to_sew'],
                    'notes' => '',
                ];
            }
        }
        if ($items === []) {
            throw new InvalidArgumentException('No hay saldo disponible de corte para confección.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $sourceId = (int) ($item['cutting_order_item_id'] ?? 0);
            if (!isset($sourceById[$sourceId])) {
                throw new InvalidArgumentException('El ítem de corte no está disponible para confección.');
            }
            $source = $sourceById[$sourceId];
            $quantity = (float) ($item['quantity_assigned'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('La cantidad asignada debe ser mayor a cero.');
            }
            if ($quantity > (float) $source['quantity_available_to_sew'] + 0.0001) {
                throw new InvalidArgumentException('La cantidad asignada supera el saldo pendiente de confección para ' . (string) $source['item_code'] . '.');
            }

            $normalized[] = [
                'production_order_item_id' => (int) $source['production_order_item_id'],
                'cutting_order_item_id' => $sourceId,
                'external_work_receipt_item_id' => null,
                'contract_item_spec_id' => $source['contract_item_spec_id'] ?: null,
                'item_code' => $source['item_code'],
                'product_type' => $source['product_type'],
                'description' => $source['description'],
                'size' => $source['size'],
                'color' => $source['color'],
                'quantity_assigned' => $quantity,
                'unit' => $source['unit'] ?: 'unidad',
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
                    'quantity_assigned' => (float) $source['quantity_available_to_sew'],
                    'notes' => '',
                ];
            }
        }
        if ($items === []) {
            throw new InvalidArgumentException('No hay saldo aceptado disponible para confección.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $sourceId = (int) ($item['external_work_receipt_item_id'] ?? 0);
            if (!isset($sourceById[$sourceId])) {
                throw new InvalidArgumentException('El ítem de recepción externa no está disponible para confección.');
            }
            $source = $sourceById[$sourceId];
            $quantity = (float) ($item['quantity_assigned'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('La cantidad asignada debe ser mayor a cero.');
            }
            if ($quantity > (float) $source['quantity_available_to_sew'] + 0.0001) {
                throw new InvalidArgumentException('La cantidad asignada supera el saldo aceptado pendiente de confección para ' . (string) $source['item_code'] . '.');
            }

            $normalized[] = [
                'production_order_item_id' => (int) $source['production_order_item_id'],
                'cutting_order_item_id' => (int) $source['cutting_order_item_id'],
                'external_work_receipt_item_id' => $sourceId,
                'contract_item_spec_id' => $source['contract_item_spec_id'] ?: null,
                'item_code' => $source['item_code'],
                'product_type' => $source['product_type'],
                'description' => $source['description'],
                'size' => $source['size'],
                'color' => $source['color'],
                'quantity_assigned' => $quantity,
                'unit' => $source['unit'] ?: 'unidad',
                'notes' => $this->nullable($item['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function insertItem(int $sewingOrderId, array $item): void
    {
        $this->execute(
            'INSERT INTO sewing_order_items (
                sewing_order_id, production_order_item_id, cutting_order_item_id, external_work_receipt_item_id,
                contract_item_spec_id, item_code, product_type, description, size, color, quantity_assigned,
                quantity_completed, quantity_rejected, quantity_pending, unit, status, notes, updated_at
            ) VALUES (
                :sewing_order_id, :production_order_item_id, :cutting_order_item_id, :external_work_receipt_item_id,
                :contract_item_spec_id, :item_code, :product_type, :description, :size, :color, :quantity_assigned,
                0, 0, :quantity_assigned, :unit, "pending", :notes, CURRENT_TIMESTAMP
            )',
            $item + ['sewing_order_id' => $sewingOrderId]
        );
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProgressEntries(array $order, array $entries): array
    {
        $items = [];
        foreach (($order['items'] ?? []) as $item) {
            $items[(int) $item['id']] = $item;
        }

        if ($entries === []) {
            throw new InvalidArgumentException('Debe registrar al menos un avance de confección.');
        }

        $normalized = [];
        foreach ($entries as $entry) {
            $itemId = (int) ($entry['sewing_order_item_id'] ?? 0);
            if (!isset($items[$itemId])) {
                throw new InvalidArgumentException('El ítem de avance no pertenece a la orden de confección.');
            }
            $completed = (float) ($entry['quantity_completed'] ?? 0);
            $rejected = (float) ($entry['quantity_rejected'] ?? 0);
            if ($completed < 0 || $rejected < 0 || $completed + $rejected <= 0) {
                throw new InvalidArgumentException('El avance debe registrar cantidad confeccionada o rechazada mayor a cero.');
            }
            $item = $items[$itemId];
            $used = (float) $item['quantity_completed'] + (float) $item['quantity_rejected'] + $completed + $rejected;
            if ($used > (float) $item['quantity_assigned'] + 0.0001) {
                throw new InvalidArgumentException('quantity_completed + quantity_rejected no puede superar quantity_assigned para ' . (string) $item['item_code'] . '.');
            }

            $normalized[] = [
                'item' => $item,
                'quantity_completed' => $completed,
                'quantity_rejected' => $rejected,
                'notes' => $this->nullable($entry['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    private function itemStatus(float $completed, float $rejected, float $assigned): string
    {
        if ($completed + $rejected >= $assigned - 0.0001) {
            return $completed > 0 ? 'completed' : 'rejected';
        }

        return $completed > 0 || $rejected > 0 ? 'partially_completed' : 'pending';
    }

    private function orderProgressStatus(int $sewingOrderId): string
    {
        $row = $this->fetchOne(
            'SELECT
                SUM(CASE WHEN quantity_completed + quantity_rejected > 0 THEN 1 ELSE 0 END) AS touched_items,
                SUM(CASE WHEN quantity_completed + quantity_rejected + 0.0001 >= quantity_assigned THEN 0 ELSE 1 END) AS pending_items
             FROM sewing_order_items
             WHERE sewing_order_id = :id',
            ['id' => $sewingOrderId]
        );

        if ((int) ($row['pending_items'] ?? 0) === 0) {
            return 'completed';
        }

        return (int) ($row['touched_items'] ?? 0) > 0 ? 'partially_completed' : 'in_progress';
    }

    private function hasProgress(int $sewingOrderId): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM sewing_progress_entries WHERE sewing_order_id = :id',
            ['id' => $sewingOrderId]
        );

        return (int) ($row['total'] ?? 0) > 0;
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

    private function assignedQuantityForCuttingItem(int $cuttingOrderItemId): float
    {
        return (float) ($this->fetchOne(
            'SELECT COALESCE(SUM(sewing_order_items.quantity_assigned), 0) AS total
             FROM sewing_order_items
             INNER JOIN sewing_orders ON sewing_orders.id = sewing_order_items.sewing_order_id
             WHERE sewing_order_items.cutting_order_item_id = :id
               AND sewing_order_items.external_work_receipt_item_id IS NULL
               AND sewing_orders.status != "cancelled"',
            ['id' => $cuttingOrderItemId]
        )['total'] ?? 0);
    }

    private function assignedQuantityForExternalReceiptItem(int $receiptItemId): float
    {
        return (float) ($this->fetchOne(
            'SELECT COALESCE(SUM(sewing_order_items.quantity_assigned), 0) AS total
             FROM sewing_order_items
             INNER JOIN sewing_orders ON sewing_orders.id = sewing_order_items.sewing_order_id
             WHERE sewing_order_items.external_work_receipt_item_id = :id
               AND sewing_orders.status != "cancelled"',
            ['id' => $receiptItemId]
        )['total'] ?? 0);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function itemRequiresExternalWork(array $item): bool
    {
        return !empty($item['requires_embroidery'])
            || !empty($item['requires_screen_printing'])
            || !empty($item['has_embroidery'])
            || !empty($item['has_screen_printing']);
    }

    private function nextSewingNumber(int $productionOrderId): string
    {
        $row = $this->fetchOne('SELECT production_number FROM production_orders WHERE id = :id', ['id' => $productionOrderId]);
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM sewing_orders WHERE production_order_id = :id',
            ['id' => $productionOrderId]
        )['total'] ?? 0);

        return sprintf('CONF-%s-%02d', (string) ($row['production_number'] ?? $productionOrderId), $count + 1);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $order
     */
    private function audit(string $action, ?string $previous, string $next, ?array $order, int $sewingOrderId): void
    {
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $firstItem = $items[0] ?? [];

        (new OperationalAuditService())->logDocumentAction(
            'sewing_orders',
            $sewingOrderId,
            (string) ($order['sewing_number'] ?? $sewingOrderId),
            $action,
            $previous,
            $next,
            [
                'sewing_order_id' => $sewingOrderId,
                'sewing_number' => $order['sewing_number'] ?? null,
                'seamster_id' => $order['seamster_id'] ?? null,
                'production_order_id' => $order['production_order_id'] ?? null,
                'cutting_order_id' => $order['cutting_order_id'] ?? null,
                'external_work_order_id' => $order['external_work_order_id'] ?? null,
                'external_work_receipt_id' => $order['external_work_receipt_id'] ?? null,
                'item_code' => $firstItem['item_code'] ?? null,
                'quantity_assigned' => $firstItem['quantity_assigned'] ?? null,
                'quantity_completed' => $firstItem['quantity_completed'] ?? null,
                'quantity_rejected' => $firstItem['quantity_rejected'] ?? null,
                'quantity_pending' => $firstItem['quantity_pending'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
                'item_count' => count($items),
            ]
        );
    }
}
