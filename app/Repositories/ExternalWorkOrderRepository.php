<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\OperationalAuditService;
use App\Support\Auth;
use InvalidArgumentException;
use Throwable;

final class ExternalWorkOrderRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    external_work_orders.*,
                    suppliers.name AS supplier_name,
                    production_orders.production_number,
                    cutting_orders.cutting_number,
                    clients.name AS client_name,
                    contracts.contract_number
                FROM external_work_orders
                INNER JOIN production_orders ON production_orders.id = external_work_orders.production_order_id
                INNER JOIN cutting_orders ON cutting_orders.id = external_work_orders.cutting_order_id
                LEFT JOIN suppliers ON suppliers.id = external_work_orders.supplier_id
                LEFT JOIN clients ON clients.id = production_orders.client_id
                LEFT JOIN contracts ON contracts.id = production_orders.contract_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                external_work_orders.external_work_number LIKE :q
                OR external_work_orders.send_note_number LIKE :q
                OR production_orders.production_number LIKE :q
                OR cutting_orders.cutting_number LIKE :q
                OR suppliers.name LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'work_type', 'supplier_id', 'production_order_id', 'cutting_order_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND external_work_orders.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY external_work_orders.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                external_work_orders.*,
                suppliers.name AS supplier_name,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                production_orders.contract_id,
                production_orders.client_id,
                production_orders.production_stage,
                cutting_orders.cutting_number,
                clients.name AS client_name,
                contracts.contract_number,
                customer_purchase_orders.po_number
             FROM external_work_orders
             INNER JOIN production_orders ON production_orders.id = external_work_orders.production_order_id
             INNER JOIN cutting_orders ON cutting_orders.id = external_work_orders.cutting_order_id
             LEFT JOIN suppliers ON suppliers.id = external_work_orders.supplier_id
             LEFT JOIN clients ON clients.id = production_orders.client_id
             LEFT JOIN contracts ON contracts.id = production_orders.contract_id
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             WHERE external_work_orders.id = :id',
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
        $order['receipts'] = $this->receipts($id);
        foreach ($order['receipts'] as &$receipt) {
            $receipt['items'] = $this->receiptItems((int) $receipt['id']);
        }
        unset($receipt);

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
    public function items(int $externalWorkOrderId): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM external_work_order_items
             WHERE external_work_order_id = :id
             ORDER BY id',
            ['id' => $externalWorkOrderId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function receipts(int $externalWorkOrderId): array
    {
        return $this->fetchAll(
            'SELECT *
             FROM external_work_receipts
             WHERE external_work_order_id = :id
             ORDER BY id DESC',
            ['id' => $externalWorkOrderId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function receiptItems(int $receiptId): array
    {
        return $this->fetchAll(
            'SELECT
                external_work_receipt_items.*,
                external_work_order_items.item_code,
                external_work_order_items.description,
                external_work_order_items.size,
                external_work_order_items.color
             FROM external_work_receipt_items
             INNER JOIN external_work_order_items ON external_work_order_items.id = external_work_receipt_items.external_work_order_item_id
             WHERE external_work_receipt_items.external_work_receipt_id = :id
             ORDER BY external_work_receipt_items.id',
            ['id' => $receiptId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eligibleItemsFromCuttingOrder(int $cuttingOrderId, bool $includeManualCandidates = false): array
    {
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
            $item['quantity_already_sent'] = $this->sentQuantityForCuttingItem((int) $item['id']);
            $item['quantity_available_to_send'] = max(0.0, (float) $item['quantity_cut'] - (float) $item['quantity_already_sent']);
        }
        unset($item);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => (float) $item['quantity_available_to_send'] > 0.0001
                && ($includeManualCandidates || !empty($item['requires_external_work']))
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDraftContext(int $cuttingOrderId): array
    {
        $cuttingOrder = (new CuttingOrderRepository())->find($cuttingOrderId);
        if (!$cuttingOrder) {
            throw new InvalidArgumentException('Orden de corte no encontrada.');
        }
        if (!in_array((string) $cuttingOrder['status'], ['completed', 'closed'], true)) {
            throw new InvalidArgumentException('Solo se puede crear trabajo externo desde una orden de corte completada o cerrada.');
        }

        $cuttingOrder['items'] = $this->eligibleItemsFromCuttingOrder($cuttingOrderId, true);
        $cuttingOrder['external_work_orders'] = $this->byCuttingOrder($cuttingOrderId);

        return $cuttingOrder;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createFromCuttingOrder(int $cuttingOrderId, array $data, array $items): int
    {
        $cuttingOrder = (new CuttingOrderRepository())->find($cuttingOrderId);
        if (!$cuttingOrder) {
            throw new InvalidArgumentException('Orden de corte no encontrada.');
        }
        if (!in_array((string) $cuttingOrder['status'], ['completed', 'closed'], true)) {
            throw new InvalidArgumentException('Solo se puede crear trabajo externo desde una orden de corte completed o closed.');
        }

        $normalized = $this->normalizeItems($cuttingOrderId, $items);
        $header = $this->normalizeHeader($data, (int) $cuttingOrder['production_order_id'], $cuttingOrderId);

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO external_work_orders (
                    production_order_id, cutting_order_id, supplier_id, external_work_number, work_type,
                    status, send_note_number, expected_return_date, next_stage, notes, created_by, updated_at
                ) VALUES (
                    :production_order_id, :cutting_order_id, :supplier_id, :external_work_number, :work_type,
                    "draft", :send_note_number, :expected_return_date, :next_stage, :notes, :created_by, CURRENT_TIMESTAMP
                )',
                $header + ['created_by' => Auth::id()]
            );

            $id = (int) $pdo->lastInsertId();
            foreach ($normalized as $item) {
                $this->insertItem($id, $item);
            }
            $pdo->commit();
            $this->audit('create_external_work_order', null, 'draft', $this->findWithDetails($id), $id);

            return $id;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function updateDraft(int $id, array $data, array $items): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Orden de trabajo externo no encontrada.');
        }
        if ((string) $existing['status'] !== 'draft') {
            throw new InvalidArgumentException('Una orden externa enviada no se puede editar libremente.');
        }

        $normalized = $this->normalizeItems((int) $existing['cutting_order_id'], $items, $id);
        $header = $this->normalizeHeader($data, (int) $existing['production_order_id'], (int) $existing['cutting_order_id'], $id);
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'UPDATE external_work_orders SET
                    supplier_id = :supplier_id,
                    external_work_number = :external_work_number,
                    work_type = :work_type,
                    send_note_number = :send_note_number,
                    expected_return_date = :expected_return_date,
                    next_stage = :next_stage,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                $header + ['id' => $id]
            );
            $this->execute('DELETE FROM external_work_order_items WHERE external_work_order_id = :id', ['id' => $id]);
            foreach ($normalized as $item) {
                $this->insertItem($id, $item);
            }
            $pdo->commit();
            $this->audit('update_external_work_order', (string) $existing['status'], 'draft', $this->findWithDetails($id), $id);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function send(int $id, ?string $sendNoteNumber = null): void
    {
        $order = $this->findWithDetails($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de trabajo externo no encontrada.');
        }
        if ((string) $order['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede enviar una orden externa en borrador.');
        }
        if (($order['items'] ?? []) === []) {
            throw new InvalidArgumentException('La orden externa no tiene ítems.');
        }

        $note = trim((string) ($sendNoteNumber ?? $order['send_note_number'] ?? ''));
        if ($note === '') {
            $note = $this->nextSendNoteNumber((int) $order['cutting_order_id']);
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'UPDATE external_work_orders
                 SET status = "sent",
                     send_note_number = :send_note_number,
                     sent_at = CURRENT_TIMESTAMP,
                     confirmed_by = :user_id,
                     confirmed_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $id, 'send_note_number' => $note, 'user_id' => Auth::id()]
            );
            $this->execute(
                'UPDATE external_work_order_items
                 SET status = "sent", updated_at = CURRENT_TIMESTAMP
                 WHERE external_work_order_id = :id',
                ['id' => $id]
            );
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = "external_work_sent", updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = "confirmed"',
                ['id' => $order['production_order_id']]
            );
            $pdo->commit();
            $this->audit('send_external_work_order', (string) $order['status'], 'sent', $this->findWithDetails($id), $id);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createReceipt(int $externalWorkOrderId, array $data, array $items): int
    {
        $order = $this->findWithDetails($externalWorkOrderId);
        if (!$order) {
            throw new InvalidArgumentException('Orden de trabajo externo no encontrada.');
        }
        if (!in_array((string) $order['status'], ['sent', 'partially_returned'], true)) {
            throw new InvalidArgumentException('Solo se puede recibir una orden externa enviada o parcialmente retornada.');
        }

        $nextStage = $this->normalizeNextStage($data['next_stage'] ?? ($order['next_stage'] ?? 'sewing'));
        $receiptNumber = trim((string) ($data['receipt_number'] ?? ''));
        if ($receiptNumber === '') {
            $receiptNumber = $this->nextReceiptNumber($externalWorkOrderId);
        }
        $normalized = $this->normalizeReceiptItems($externalWorkOrderId, $items, $nextStage);

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO external_work_receipts (
                    external_work_order_id, receipt_number, status, next_stage, notes, updated_at
                ) VALUES (
                    :external_work_order_id, :receipt_number, "draft", :next_stage, :notes, CURRENT_TIMESTAMP
                )',
                [
                    'external_work_order_id' => $externalWorkOrderId,
                    'receipt_number' => $receiptNumber,
                    'next_stage' => $nextStage,
                    'notes' => $this->nullable($data['notes'] ?? null),
                ]
            );
            $receiptId = (int) $pdo->lastInsertId();
            foreach ($normalized as $item) {
                $this->execute(
                    'INSERT INTO external_work_receipt_items (
                        external_work_receipt_id, external_work_order_item_id, quantity_received,
                        quantity_accepted, quantity_rejected, quality_notes, next_stage, status, notes, updated_at
                    ) VALUES (
                        :external_work_receipt_id, :external_work_order_item_id, :quantity_received,
                        :quantity_accepted, :quantity_rejected, :quality_notes, :next_stage, :status, :notes, CURRENT_TIMESTAMP
                    )',
                    $item + ['external_work_receipt_id' => $receiptId]
                );
            }
            $pdo->commit();
            $this->audit('create_external_work_receipt', null, 'draft', $this->findWithDetails($externalWorkOrderId), $externalWorkOrderId, $receiptId);

            return $receiptId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function confirmReceipt(int $receiptId): void
    {
        $receipt = $this->fetchOne('SELECT * FROM external_work_receipts WHERE id = :id', ['id' => $receiptId]);
        if (!$receipt) {
            throw new InvalidArgumentException('Recepción externa no encontrada.');
        }
        if ((string) $receipt['status'] !== 'draft') {
            throw new InvalidArgumentException('Solo se puede confirmar una recepción externa en borrador.');
        }

        $order = $this->findWithDetails((int) $receipt['external_work_order_id']);
        if (!$order) {
            throw new InvalidArgumentException('Orden de trabajo externo no encontrada.');
        }
        if (!in_array((string) $order['status'], ['sent', 'partially_returned'], true)) {
            throw new InvalidArgumentException('La orden externa no está disponible para recepción.');
        }

        $items = $this->receiptItems($receiptId);
        if ($items === []) {
            throw new InvalidArgumentException('La recepción externa no tiene ítems.');
        }

        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            foreach ($items as $item) {
                $orderItem = $this->fetchOne('SELECT * FROM external_work_order_items WHERE id = :id', ['id' => $item['external_work_order_item_id']]);
                if (!$orderItem) {
                    throw new InvalidArgumentException('Ítem de trabajo externo no encontrado.');
                }
                $pending = (float) $orderItem['quantity_sent'] - (float) $orderItem['quantity_returned'];
                if ((float) $item['quantity_received'] > $pending + 0.0001) {
                    throw new InvalidArgumentException('No se puede recibir más que el saldo pendiente de retorno.');
                }

                $newReturned = (float) $orderItem['quantity_returned'] + (float) $item['quantity_received'];
                $newRejected = (float) $orderItem['quantity_rejected'] + (float) $item['quantity_rejected'];
                $status = $newReturned >= (float) $orderItem['quantity_sent'] - 0.0001
                    ? ($newRejected >= (float) $orderItem['quantity_sent'] - 0.0001 ? 'rejected' : 'returned')
                    : 'partially_returned';

                $this->execute(
                    'UPDATE external_work_order_items
                     SET quantity_returned = :quantity_returned,
                         quantity_rejected = :quantity_rejected,
                         status = :status,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id',
                    [
                        'id' => $orderItem['id'],
                        'quantity_returned' => $newReturned,
                        'quantity_rejected' => $newRejected,
                        'status' => $status,
                    ]
                );
            }

            $newOrderStatus = $this->orderReturnStatus((int) $receipt['external_work_order_id']);
            $this->execute(
                'UPDATE external_work_receipts
                 SET status = "confirmed",
                     received_by = :user_id,
                     received_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $receiptId, 'user_id' => Auth::id()]
            );
            $this->execute(
                'UPDATE external_work_orders
                 SET status = :status,
                     returned_at = CASE WHEN :status = "returned" THEN CURRENT_TIMESTAMP ELSE returned_at END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id',
                ['id' => $receipt['external_work_order_id'], 'status' => $newOrderStatus]
            );
            $this->execute(
                'UPDATE production_orders
                 SET production_stage = :stage, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = "confirmed"',
                [
                    'id' => $order['production_order_id'],
                    'stage' => $newOrderStatus === 'returned' ? $this->stageFromNextStage((string) $receipt['next_stage']) : 'external_work_received',
                ]
            );
            $pdo->commit();
            $this->audit('confirm_external_work_receipt', (string) $order['status'], $newOrderStatus, $this->findWithDetails((int) $receipt['external_work_order_id']), (int) $receipt['external_work_order_id'], $receiptId);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function cancelDraft(int $id): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de trabajo externo no encontrada.');
        }
        if ((string) $order['status'] !== 'draft') {
            throw new InvalidArgumentException('No se puede cancelar libremente una orden ya enviada con cantidades fuera de la empresa.');
        }

        $this->execute(
            'UPDATE external_work_orders
             SET status = "cancelled",
                 cancelled_by = :user_id,
                 cancelled_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->execute(
            'UPDATE external_work_order_items SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE external_work_order_id = :id',
            ['id' => $id]
        );
        $this->audit('cancel_external_work_order', (string) $order['status'], 'cancelled', $this->findWithDetails($id), $id);
    }

    public function close(int $id): void
    {
        $order = $this->find($id);
        if (!$order) {
            throw new InvalidArgumentException('Orden de trabajo externo no encontrada.');
        }
        if ((string) $order['status'] !== 'returned') {
            throw new InvalidArgumentException('Solo se puede cerrar una orden externa retornada.');
        }

        $this->execute(
            'UPDATE external_work_orders
             SET status = "closed",
                 closed_by = :user_id,
                 closed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            ['id' => $id, 'user_id' => Auth::id()]
        );
        $this->audit('close_external_work_order', (string) $order['status'], 'closed', $this->findWithDetails($id), $id);
    }

    /**
     * @return array<string, float>
     */
    public function quantitiesForItem(int $externalWorkOrderItemId): array
    {
        $item = $this->fetchOne('SELECT * FROM external_work_order_items WHERE id = :id', ['id' => $externalWorkOrderItemId]);
        if (!$item) {
            throw new InvalidArgumentException('Ítem de trabajo externo no encontrado.');
        }

        return [
            'sent' => (float) $item['quantity_sent'],
            'returned' => (float) $item['quantity_returned'],
            'rejected' => (float) $item['quantity_rejected'],
            'pending_return' => max(0.0, (float) $item['quantity_sent'] - (float) $item['quantity_returned']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(int $cuttingOrderId, array $items, ?int $excludeExternalWorkOrderId = null): array
    {
        $sourceItems = $this->eligibleItemsFromCuttingOrder($cuttingOrderId, true);
        $sourceById = [];
        foreach ($sourceItems as $source) {
            $sourceById[(int) $source['id']] = $source;
        }

        if ($items === []) {
            foreach ($sourceItems as $source) {
                if (!empty($source['requires_external_work'])) {
                    $items[] = [
                        'cutting_order_item_id' => (int) $source['id'],
                        'quantity_sent' => (float) $source['quantity_available_to_send'],
                        'work_details' => '',
                        'notes' => '',
                    ];
                }
            }
        }
        if ($items === []) {
            throw new InvalidArgumentException('No hay ítems elegibles para serigrafía/bordado externo.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $cuttingItemId = (int) ($item['cutting_order_item_id'] ?? 0);
            if (!isset($sourceById[$cuttingItemId])) {
                throw new InvalidArgumentException('El ítem no pertenece al corte o no tiene cantidad cortada disponible.');
            }

            $source = $sourceById[$cuttingItemId];
            $quantity = (float) ($item['quantity_sent'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('La cantidad enviada debe ser mayor a cero.');
            }

            $sent = $this->sentQuantityForCuttingItem($cuttingItemId, $excludeExternalWorkOrderId);
            $available = max(0.0, (float) $source['quantity_cut'] - $sent);
            if ($quantity > $available + 0.0001) {
                throw new InvalidArgumentException('La cantidad enviada supera el corte disponible no enviado para ' . (string) $source['item_code'] . '.');
            }

            $manualOverride = !empty($item['manual_override']);
            $notes = $this->nullable($item['notes'] ?? null);
            if (empty($source['requires_external_work']) && !$manualOverride) {
                throw new InvalidArgumentException('Solo se incluyen ítems con bordado/serigrafía salvo override manual.');
            }
            if (empty($source['requires_external_work']) && $notes === null) {
                throw new InvalidArgumentException('El override manual debe registrar motivo en notas.');
            }

            $normalized[] = [
                'cutting_order_item_id' => $cuttingItemId,
                'production_order_item_id' => (int) $source['production_order_item_id'],
                'contract_item_spec_id' => $source['contract_item_spec_id'] ?: null,
                'item_code' => $source['item_code'],
                'product_type' => $source['product_type'],
                'description' => $source['description'],
                'size' => $source['size'],
                'color' => $source['color'],
                'quantity_sent' => $quantity,
                'work_details' => $this->nullable($item['work_details'] ?? null),
                'notes' => $notes,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeReceiptItems(int $externalWorkOrderId, array $items, string $defaultNextStage): array
    {
        $orderItems = $this->items($externalWorkOrderId);
        $byId = [];
        foreach ($orderItems as $orderItem) {
            $byId[(int) $orderItem['id']] = $orderItem;
        }

        if ($items === []) {
            foreach ($orderItems as $orderItem) {
                $pending = (float) $orderItem['quantity_sent'] - (float) $orderItem['quantity_returned'];
                if ($pending > 0.0001) {
                    $items[] = [
                        'external_work_order_item_id' => (int) $orderItem['id'],
                        'quantity_received' => $pending,
                        'quantity_accepted' => $pending,
                        'quantity_rejected' => 0,
                    ];
                }
            }
        }
        if ($items === []) {
            throw new InvalidArgumentException('No hay saldo pendiente de retorno.');
        }

        $normalized = [];
        foreach ($items as $item) {
            $orderItemId = (int) ($item['external_work_order_item_id'] ?? 0);
            if (!isset($byId[$orderItemId])) {
                throw new InvalidArgumentException('El ítem de recepción no pertenece a la orden externa.');
            }

            $received = (float) ($item['quantity_received'] ?? 0);
            $accepted = (float) ($item['quantity_accepted'] ?? 0);
            $rejected = (float) ($item['quantity_rejected'] ?? 0);
            if ($received <= 0) {
                throw new InvalidArgumentException('La cantidad recibida debe ser mayor a cero.');
            }
            if ($accepted < 0 || $rejected < 0 || $accepted + $rejected > $received + 0.0001) {
                throw new InvalidArgumentException('La cantidad aceptada más rechazada no debe superar la recibida.');
            }

            $pending = (float) $byId[$orderItemId]['quantity_sent'] - (float) $byId[$orderItemId]['quantity_returned'];
            if ($received > $pending + 0.0001) {
                throw new InvalidArgumentException('No se puede recibir más que la cantidad enviada pendiente de retorno.');
            }

            $nextStage = $this->normalizeNextStage($item['next_stage'] ?? $defaultNextStage);
            $status = $accepted > 0 && $rejected > 0 ? 'partial' : ($accepted > 0 ? 'accepted' : 'rejected');
            $normalized[] = [
                'external_work_order_item_id' => $orderItemId,
                'quantity_received' => $received,
                'quantity_accepted' => $accepted,
                'quantity_rejected' => $rejected,
                'quality_notes' => $this->nullable($item['quality_notes'] ?? null),
                'next_stage' => $nextStage,
                'status' => $status,
                'notes' => $this->nullable($item['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeHeader(array $data, int $productionOrderId, int $cuttingOrderId, ?int $excludeExternalWorkOrderId = null): array
    {
        $number = trim((string) ($data['external_work_number'] ?? ''));
        if ($number === '') {
            $number = $this->nextExternalWorkNumber($cuttingOrderId);
        }

        $workType = trim((string) ($data['work_type'] ?? 'both')) ?: 'both';
        if (!in_array($workType, ['embroidery', 'screen_printing', 'both', 'other'], true)) {
            throw new InvalidArgumentException('Tipo de trabajo externo inválido.');
        }

        $supplierId = trim((string) ($data['supplier_id'] ?? '')) === '' ? null : (int) $data['supplier_id'];
        if ($supplierId !== null && !(new SupplierRepository())->find($supplierId)) {
            throw new InvalidArgumentException('Proveedor no encontrado.');
        }

        return [
            'production_order_id' => $productionOrderId,
            'cutting_order_id' => $cuttingOrderId,
            'supplier_id' => $supplierId,
            'external_work_number' => $number,
            'work_type' => $workType,
            'send_note_number' => $this->nullable($data['send_note_number'] ?? null),
            'expected_return_date' => $this->nullable($data['expected_return_date'] ?? null),
            'next_stage' => $this->normalizeNextStage($data['next_stage'] ?? 'sewing'),
            'notes' => $this->nullable($data['notes'] ?? null),
        ];
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

    private function sentQuantityForCuttingItem(int $cuttingOrderItemId, ?int $excludeExternalWorkOrderId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(external_work_order_items.quantity_sent), 0) AS total
                FROM external_work_order_items
                INNER JOIN external_work_orders ON external_work_orders.id = external_work_order_items.external_work_order_id
                WHERE external_work_order_items.cutting_order_item_id = :id
                  AND external_work_orders.status != "cancelled"';
        $params = ['id' => $cuttingOrderItemId];
        if ($excludeExternalWorkOrderId !== null) {
            $sql .= ' AND external_work_orders.id != :exclude_id';
            $params['exclude_id'] = $excludeExternalWorkOrderId;
        }

        return (float) ($this->fetchOne($sql, $params)['total'] ?? 0);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function insertItem(int $externalWorkOrderId, array $item): void
    {
        $this->execute(
            'INSERT INTO external_work_order_items (
                external_work_order_id, cutting_order_item_id, production_order_item_id, contract_item_spec_id,
                item_code, product_type, description, size, color, quantity_sent, work_details,
                status, notes, updated_at
            ) VALUES (
                :external_work_order_id, :cutting_order_item_id, :production_order_item_id, :contract_item_spec_id,
                :item_code, :product_type, :description, :size, :color, :quantity_sent, :work_details,
                "pending", :notes, CURRENT_TIMESTAMP
            )',
            $item + ['external_work_order_id' => $externalWorkOrderId]
        );
    }

    private function orderReturnStatus(int $externalWorkOrderId): string
    {
        $row = $this->fetchOne(
            'SELECT
                SUM(CASE WHEN quantity_returned + 0.0001 >= quantity_sent THEN 0 ELSE 1 END) AS pending_items
             FROM external_work_order_items
             WHERE external_work_order_id = :id',
            ['id' => $externalWorkOrderId]
        );

        return (int) ($row['pending_items'] ?? 0) === 0 ? 'returned' : 'partially_returned';
    }

    private function normalizeNextStage(mixed $value): string
    {
        $stage = trim((string) $value) ?: 'sewing';
        if (!in_array($stage, ['sewing', 'quality_control'], true)) {
            throw new InvalidArgumentException('Próxima etapa inválida.');
        }

        return $stage;
    }

    private function stageFromNextStage(string $nextStage): string
    {
        return $nextStage === 'quality_control' ? 'quality_control' : 'in_sewing';
    }

    private function nextExternalWorkNumber(int $cuttingOrderId): string
    {
        $row = $this->fetchOne('SELECT cutting_number FROM cutting_orders WHERE id = :id', ['id' => $cuttingOrderId]);
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM external_work_orders WHERE cutting_order_id = :id',
            ['id' => $cuttingOrderId]
        )['total'] ?? 0);

        return sprintf('EXT-%s-%02d', (string) ($row['cutting_number'] ?? $cuttingOrderId), $count + 1);
    }

    private function nextSendNoteNumber(int $cuttingOrderId): string
    {
        $row = $this->fetchOne('SELECT cutting_number FROM cutting_orders WHERE id = :id', ['id' => $cuttingOrderId]);
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM external_work_orders WHERE cutting_order_id = :id AND send_note_number IS NOT NULL',
            ['id' => $cuttingOrderId]
        )['total'] ?? 0);

        return sprintf('NE-%s-%02d', (string) ($row['cutting_number'] ?? $cuttingOrderId), $count + 1);
    }

    private function nextReceiptNumber(int $externalWorkOrderId): string
    {
        $row = $this->fetchOne('SELECT external_work_number FROM external_work_orders WHERE id = :id', ['id' => $externalWorkOrderId]);
        $count = (int) ($this->fetchOne(
            'SELECT COUNT(*) AS total FROM external_work_receipts WHERE external_work_order_id = :id',
            ['id' => $externalWorkOrderId]
        )['total'] ?? 0);

        return sprintf('REC-%s-%02d', (string) ($row['external_work_number'] ?? $externalWorkOrderId), $count + 1);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed>|null $order
     */
    private function audit(string $action, ?string $previous, string $next, ?array $order, int $externalWorkOrderId, ?int $receiptId = null): void
    {
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $receipts = is_array($order['receipts'] ?? null) ? $order['receipts'] : [];
        $firstItem = $items[0] ?? [];
        $firstReceiptItem = [];
        foreach ($receipts as $receipt) {
            if (($receipt['id'] ?? null) === $receiptId && !empty($receipt['items'][0])) {
                $firstReceiptItem = $receipt['items'][0];
                break;
            }
        }

        (new OperationalAuditService())->logDocumentAction(
            'external_work_orders',
            $externalWorkOrderId,
            (string) ($order['external_work_number'] ?? $externalWorkOrderId),
            $action,
            $previous,
            $next,
            [
                'external_work_order_id' => $externalWorkOrderId,
                'external_work_number' => $order['external_work_number'] ?? null,
                'external_work_receipt_id' => $receiptId,
                'production_order_id' => $order['production_order_id'] ?? null,
                'cutting_order_id' => $order['cutting_order_id'] ?? null,
                'supplier_id' => $order['supplier_id'] ?? null,
                'work_type' => $order['work_type'] ?? null,
                'send_note_number' => $order['send_note_number'] ?? null,
                'item_code' => $firstItem['item_code'] ?? null,
                'quantity_sent' => $firstItem['quantity_sent'] ?? null,
                'quantity_received' => $firstReceiptItem['quantity_received'] ?? null,
                'quantity_accepted' => $firstReceiptItem['quantity_accepted'] ?? null,
                'quantity_rejected' => $firstReceiptItem['quantity_rejected'] ?? null,
                'next_stage' => $order['next_stage'] ?? ($firstReceiptItem['next_stage'] ?? null),
                'status_previous' => $previous,
                'status_new' => $next,
                'item_count' => count($items),
                'receipt_count' => count($receipts),
            ]
        );
    }
}
