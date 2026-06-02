<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ExternalWorkOrderRepository;
use App\Repositories\QualityControlRepository;
use App\Repositories\SewingOrderRepository;
use App\Repositories\SupplierRepository;
use App\Support\Request;
use Throwable;

final class ExternalWorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
            'work_type' => trim((string) $request->input('work_type', '')),
        ];

        return $this->render('external_work_orders/index', [
            'orders' => (new ExternalWorkOrderRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function createFromCuttingOrder(Request $request, string $cuttingOrderId)
    {
        $repo = new ExternalWorkOrderRepository();

        try {
            $cuttingOrder = $repo->buildDraftContext((int) $cuttingOrderId);
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/cutting-orders/' . (int) $cuttingOrderId, $exception->getMessage(), 'error');
        }

        return $this->render('external_work_orders/form', [
            'title' => 'Nuevo trabajo externo',
            'action' => '/cutting-orders/' . (int) $cuttingOrderId . '/external-work-orders',
            'order' => [
                'supplier_id' => '',
                'external_work_number' => '',
                'work_type' => 'both',
                'send_note_number' => '',
                'expected_return_date' => '',
                'next_stage' => 'sewing',
                'notes' => '',
            ],
            'cuttingOrder' => $cuttingOrder,
            'suppliers' => (new SupplierRepository())->search(['status' => 'active']),
        ]);
    }

    public function storeFromCuttingOrder(Request $request, string $cuttingOrderId)
    {
        $repo = new ExternalWorkOrderRepository();

        try {
            $id = $repo->createFromCuttingOrder(
                (int) $cuttingOrderId,
                $request->only(['supplier_id', 'external_work_number', 'work_type', 'send_note_number', 'expected_return_date', 'next_stage', 'notes']),
                $this->collectItems($request->all())
            );

            return $this->redirectWithMessage('/external-work-orders/' . $id, 'Trabajo externo creado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/cutting-orders/' . (int) $cuttingOrderId . '/external-work-orders/create', 'No se pudo crear el trabajo externo: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $order = (new ExternalWorkOrderRepository())->findWithDetails((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/external-work-orders', 'Trabajo externo no encontrado.', 'error');
        }

        $sewingRepo = new SewingOrderRepository();
        $qualityRepo = new QualityControlRepository();
        $sewingAvailableByReceipt = [];
        $qualityAvailableByReceipt = [];
        foreach (($order['receipts'] ?? []) as $receipt) {
            $sewingAvailableByReceipt[(int) $receipt['id']] = count($sewingRepo->availableItemsFromExternalReceipt((int) $receipt['id']));
            $qualityAvailableByReceipt[(int) $receipt['id']] = count($qualityRepo->availableItemsFromExternalReceipt((int) $receipt['id']));
        }

        return $this->render('external_work_orders/show', [
            'order' => $order,
            'sewingOrders' => $sewingRepo->byExternalWorkOrder((int) $id),
            'qualityChecks' => $qualityRepo->search(['external_work_order_id' => (int) $id]),
            'sewingAvailableByReceipt' => $sewingAvailableByReceipt,
            'qualityAvailableByReceipt' => $qualityAvailableByReceipt,
        ]);
    }

    public function send(Request $request, string $id)
    {
        $repo = new ExternalWorkOrderRepository();

        try {
            $repo->send((int) $id, trim((string) $request->input('send_note_number', '')) ?: null);
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, 'Trabajo externo enviado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function createReceipt(Request $request, string $id)
    {
        $order = (new ExternalWorkOrderRepository())->findWithDetails((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/external-work-orders', 'Trabajo externo no encontrado.', 'error');
        }
        if (!in_array((string) $order['status'], ['sent', 'partially_returned'], true)) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, 'El trabajo externo no está disponible para recepción.', 'error');
        }

        return $this->render('external_work_orders/receipt_form', [
            'order' => $order,
            'action' => '/external-work-orders/' . (int) $id . '/receipts',
        ]);
    }

    public function storeReceipt(Request $request, string $id)
    {
        $repo = new ExternalWorkOrderRepository();

        try {
            $receiptId = $repo->createReceipt(
                (int) $id,
                $request->only(['receipt_number', 'next_stage', 'notes']),
                $this->collectReceiptItems($request->all())
            );

            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, 'Recepción externa creada. Confírmela para actualizar saldos.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id . '/receipts/create', 'No se pudo registrar la recepción: ' . $exception->getMessage(), 'error');
        }
    }

    public function confirmReceipt(Request $request, string $id, string $receiptId)
    {
        $repo = new ExternalWorkOrderRepository();

        try {
            $repo->confirmReceipt((int) $receiptId);
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, 'Recepción externa confirmada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function cancel(Request $request, string $id)
    {
        $repo = new ExternalWorkOrderRepository();

        try {
            $repo->cancelDraft((int) $id);
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, 'Trabajo externo anulado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function close(Request $request, string $id)
    {
        $repo = new ExternalWorkOrderRepository();

        try {
            $repo->close((int) $id);
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, 'Trabajo externo cerrado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectItems(array $payload): array
    {
        $ids = is_array($payload['cutting_order_item_id'] ?? null) ? $payload['cutting_order_item_id'] : [];
        $quantities = is_array($payload['quantity_sent'] ?? null) ? $payload['quantity_sent'] : [];
        $details = is_array($payload['work_details'] ?? null) ? $payload['work_details'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $overrides = is_array($payload['manual_override'] ?? null) ? $payload['manual_override'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }

            $items[] = [
                'cutting_order_item_id' => (int) $rawId,
                'quantity_sent' => (float) ($quantities[$index] ?? 0),
                'work_details' => trim((string) ($details[$index] ?? '')),
                'notes' => trim((string) ($notes[$index] ?? '')),
                'manual_override' => !empty($overrides[$index]),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectReceiptItems(array $payload): array
    {
        $ids = is_array($payload['external_work_order_item_id'] ?? null) ? $payload['external_work_order_item_id'] : [];
        $received = is_array($payload['quantity_received'] ?? null) ? $payload['quantity_received'] : [];
        $accepted = is_array($payload['quantity_accepted'] ?? null) ? $payload['quantity_accepted'] : [];
        $rejected = is_array($payload['quantity_rejected'] ?? null) ? $payload['quantity_rejected'] : [];
        $qualityNotes = is_array($payload['quality_notes'] ?? null) ? $payload['quality_notes'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $nextStages = is_array($payload['next_stage_item'] ?? null) ? $payload['next_stage_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }

            $items[] = [
                'external_work_order_item_id' => (int) $rawId,
                'quantity_received' => (float) ($received[$index] ?? 0),
                'quantity_accepted' => (float) ($accepted[$index] ?? 0),
                'quantity_rejected' => (float) ($rejected[$index] ?? 0),
                'quality_notes' => trim((string) ($qualityNotes[$index] ?? '')),
                'next_stage' => trim((string) ($nextStages[$index] ?? '')),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }
}
