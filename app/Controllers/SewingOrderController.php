<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SeamsterRepository;
use App\Repositories\SewingOrderRepository;
use App\Support\Request;
use Throwable;

final class SewingOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
            'seamster_id' => trim((string) $request->input('seamster_id', '')),
        ];

        return $this->render('sewing_orders/index', [
            'orders' => (new SewingOrderRepository())->search($filters),
            'seamsters' => (new SeamsterRepository())->search(['status' => 'active']),
            'filters' => $filters,
        ]);
    }

    public function createFromCuttingOrder(Request $request, string $cuttingOrderId)
    {
        $repo = new SewingOrderRepository();

        try {
            $source = $repo->buildDraftContextFromCuttingOrder((int) $cuttingOrderId);
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/cutting-orders/' . (int) $cuttingOrderId, $exception->getMessage(), 'error');
        }

        return $this->render('sewing_orders/form', [
            'title' => 'Nueva orden de confección',
            'action' => '/cutting-orders/' . (int) $cuttingOrderId . '/sewing-orders',
            'sourceType' => 'cutting',
            'source' => $source,
            'order' => [
                'seamster_id' => '',
                'sewing_number' => '',
                'assigned_at' => '',
                'expected_completion_date' => '',
                'notes' => '',
            ],
            'seamsters' => (new SeamsterRepository())->search(['status' => 'active']),
        ]);
    }

    public function storeFromCuttingOrder(Request $request, string $cuttingOrderId)
    {
        $repo = new SewingOrderRepository();

        try {
            $id = $repo->createFromCuttingOrder(
                (int) $cuttingOrderId,
                $request->only(['seamster_id', 'sewing_number', 'assigned_at', 'expected_completion_date', 'notes']),
                $this->collectCuttingItems($request->all())
            );

            return $this->redirectWithMessage('/sewing-orders/' . $id, 'Orden de confección creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/cutting-orders/' . (int) $cuttingOrderId . '/sewing-orders/create', 'No se pudo crear la orden de confección: ' . $exception->getMessage(), 'error');
        }
    }

    public function createFromExternalReceipt(Request $request, string $externalWorkOrderId, string $receiptId)
    {
        $repo = new SewingOrderRepository();

        try {
            $source = $repo->buildDraftContextFromExternalReceipt((int) $receiptId);
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $externalWorkOrderId, $exception->getMessage(), 'error');
        }

        return $this->render('sewing_orders/form', [
            'title' => 'Nueva orden de confección',
            'action' => '/external-work-orders/' . (int) $externalWorkOrderId . '/receipts/' . (int) $receiptId . '/sewing-orders',
            'sourceType' => 'external_receipt',
            'source' => $source,
            'order' => [
                'seamster_id' => '',
                'sewing_number' => '',
                'assigned_at' => '',
                'expected_completion_date' => '',
                'notes' => '',
            ],
            'seamsters' => (new SeamsterRepository())->search(['status' => 'active']),
        ]);
    }

    public function storeFromExternalReceipt(Request $request, string $externalWorkOrderId, string $receiptId)
    {
        $repo = new SewingOrderRepository();

        try {
            $id = $repo->createFromExternalReceipt(
                (int) $receiptId,
                $request->only(['seamster_id', 'sewing_number', 'assigned_at', 'expected_completion_date', 'notes']),
                $this->collectExternalReceiptItems($request->all())
            );

            return $this->redirectWithMessage('/sewing-orders/' . $id, 'Orden de confección creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $externalWorkOrderId . '/receipts/' . (int) $receiptId . '/sewing-orders/create', 'No se pudo crear la orden de confección: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $order = (new SewingOrderRepository())->findWithDetails((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/sewing-orders', 'Orden de confección no encontrada.', 'error');
        }

        return $this->render('sewing_orders/show', ['order' => $order]);
    }

    public function confirm(Request $request, string $id)
    {
        $repo = new SewingOrderRepository();

        try {
            $repo->confirm((int) $id);
            return $this->redirectWithMessage('/sewing-orders/' . (int) $id, 'Orden de confección confirmada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/sewing-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function progress(Request $request, string $id)
    {
        $repo = new SewingOrderRepository();

        try {
            $repo->registerProgress(
                (int) $id,
                $this->collectProgressEntries($request->all()),
                trim((string) $request->input('progress_date', '')) ?: null,
                trim((string) $request->input('notes', '')) ?: null
            );

            return $this->redirectWithMessage('/sewing-orders/' . (int) $id, 'Avance de confección registrado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/sewing-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function cancel(Request $request, string $id)
    {
        $repo = new SewingOrderRepository();

        try {
            $repo->cancelDraft((int) $id);
            return $this->redirectWithMessage('/sewing-orders/' . (int) $id, 'Orden de confección cancelada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/sewing-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function close(Request $request, string $id)
    {
        $repo = new SewingOrderRepository();

        try {
            $repo->close((int) $id);
            return $this->redirectWithMessage('/sewing-orders/' . (int) $id, 'Orden de confección cerrada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/sewing-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectCuttingItems(array $payload): array
    {
        $ids = is_array($payload['cutting_order_item_id'] ?? null) ? $payload['cutting_order_item_id'] : [];
        $quantities = is_array($payload['quantity_assigned'] ?? null) ? $payload['quantity_assigned'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'cutting_order_item_id' => (int) $rawId,
                'quantity_assigned' => (float) ($quantities[$index] ?? 0),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectExternalReceiptItems(array $payload): array
    {
        $ids = is_array($payload['external_work_receipt_item_id'] ?? null) ? $payload['external_work_receipt_item_id'] : [];
        $quantities = is_array($payload['quantity_assigned'] ?? null) ? $payload['quantity_assigned'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'external_work_receipt_item_id' => (int) $rawId,
                'quantity_assigned' => (float) ($quantities[$index] ?? 0),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectProgressEntries(array $payload): array
    {
        $ids = is_array($payload['sewing_order_item_id'] ?? null) ? $payload['sewing_order_item_id'] : [];
        $completed = is_array($payload['quantity_completed'] ?? null) ? $payload['quantity_completed'] : [];
        $rejected = is_array($payload['quantity_rejected'] ?? null) ? $payload['quantity_rejected'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'sewing_order_item_id' => (int) $rawId,
                'quantity_completed' => (float) ($completed[$index] ?? 0),
                'quantity_rejected' => (float) ($rejected[$index] ?? 0),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }
}
