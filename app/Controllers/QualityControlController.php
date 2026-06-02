<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\QualityControlRepository;
use App\Repositories\QualityReworkRepository;
use App\Repositories\PackagingOrderRepository;
use App\Support\Request;
use Throwable;

final class QualityControlController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        return $this->render('quality_control/index', [
            'checks' => (new QualityControlRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function createFromSewingOrder(Request $request, string $sewingOrderId)
    {
        $repo = new QualityControlRepository();

        try {
            $source = $repo->buildDraftContextFromSewingOrder((int) $sewingOrderId);
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/sewing-orders/' . (int) $sewingOrderId, $exception->getMessage(), 'error');
        }

        return $this->render('quality_control/form', [
            'title' => 'Nuevo control de calidad',
            'action' => '/sewing-orders/' . (int) $sewingOrderId . '/quality-control',
            'sourceType' => 'sewing',
            'source' => $source,
            'check' => ['qc_number' => '', 'notes' => ''],
        ]);
    }

    public function storeFromSewingOrder(Request $request, string $sewingOrderId)
    {
        $repo = new QualityControlRepository();

        try {
            $id = $repo->createFromSewingOrder(
                (int) $sewingOrderId,
                $request->only(['qc_number', 'notes']),
                $this->collectSewingItems($request->all())
            );

            return $this->redirectWithMessage('/quality-control/' . $id, 'Control de calidad creado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/sewing-orders/' . (int) $sewingOrderId . '/quality-control/create', 'No se pudo crear el control de calidad: ' . $exception->getMessage(), 'error');
        }
    }

    public function createFromExternalReceipt(Request $request, string $externalWorkOrderId, string $receiptId)
    {
        $repo = new QualityControlRepository();

        try {
            $source = $repo->buildDraftContextFromExternalReceipt((int) $receiptId);
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $externalWorkOrderId, $exception->getMessage(), 'error');
        }

        return $this->render('quality_control/form', [
            'title' => 'Nuevo control de calidad',
            'action' => '/external-work-orders/' . (int) $externalWorkOrderId . '/receipts/' . (int) $receiptId . '/quality-control',
            'sourceType' => 'external_receipt',
            'source' => $source,
            'check' => ['qc_number' => '', 'notes' => ''],
        ]);
    }

    public function storeFromExternalReceipt(Request $request, string $externalWorkOrderId, string $receiptId)
    {
        $repo = new QualityControlRepository();

        try {
            $id = $repo->createFromExternalReceipt(
                (int) $receiptId,
                $request->only(['qc_number', 'notes']),
                $this->collectExternalReceiptItems($request->all())
            );

            return $this->redirectWithMessage('/quality-control/' . $id, 'Control de calidad creado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/external-work-orders/' . (int) $externalWorkOrderId . '/receipts/' . (int) $receiptId . '/quality-control/create', 'No se pudo crear el control de calidad: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $check = (new QualityControlRepository())->findWithDetails((int) $id);
        if (!$check) {
            return $this->redirectWithMessage('/quality-control', 'Control de calidad no encontrado.', 'error');
        }

        return $this->render('quality_control/show', [
            'check' => $check,
            'packagingOrders' => (new PackagingOrderRepository())->byQualityControl((int) $id),
            'availablePackagingItems' => (new PackagingOrderRepository())->availableItemsFromQualityControl((int) $id),
        ]);
    }

    public function results(Request $request, string $id)
    {
        $repo = new QualityControlRepository();

        try {
            $repo->registerResults(
                (int) $id,
                $this->collectResultItems($request->all()),
                trim((string) $request->input('notes', '')) ?: null
            );

            return $this->redirectWithMessage('/quality-control/' . (int) $id, 'Resultados de calidad registrados.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-control/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function confirm(Request $request, string $id)
    {
        $repo = new QualityControlRepository();

        try {
            $repo->confirm((int) $id);
            return $this->redirectWithMessage('/quality-control/' . (int) $id, 'Control de calidad confirmado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-control/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function cancel(Request $request, string $id)
    {
        $repo = new QualityControlRepository();

        try {
            $repo->cancelDraft((int) $id);
            return $this->redirectWithMessage('/quality-control/' . (int) $id, 'Control de calidad anulado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-control/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function close(Request $request, string $id)
    {
        $repo = new QualityControlRepository();

        try {
            $repo->close((int) $id);
            return $this->redirectWithMessage('/quality-control/' . (int) $id, 'Control de calidad cerrado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-control/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function reworks(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        return $this->render('quality_reworks/index', [
            'orders' => (new QualityReworkRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function reworkShow(Request $request, string $id)
    {
        $order = (new QualityReworkRepository())->find((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/quality-reworks', 'Orden de reproceso no encontrada.', 'error');
        }

        return $this->render('quality_reworks/show', ['order' => $order]);
    }

    public function completeRework(Request $request, string $id)
    {
        $repo = new QualityReworkRepository();

        try {
            $repo->complete((int) $id, trim((string) $request->input('notes', '')) ?: null);
            return $this->redirectWithMessage('/quality-reworks/' . (int) $id, 'Reproceso completado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-reworks/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function closeRework(Request $request, string $id)
    {
        $repo = new QualityReworkRepository();

        try {
            $repo->close((int) $id);
            return $this->redirectWithMessage('/quality-reworks/' . (int) $id, 'Reproceso cerrado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-reworks/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function cancelRework(Request $request, string $id)
    {
        $repo = new QualityReworkRepository();

        try {
            $repo->cancel((int) $id);
            return $this->redirectWithMessage('/quality-reworks/' . (int) $id, 'Reproceso anulado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-reworks/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectSewingItems(array $payload): array
    {
        $ids = is_array($payload['sewing_order_item_id'] ?? null) ? $payload['sewing_order_item_id'] : [];
        $quantities = is_array($payload['quantity_received'] ?? null) ? $payload['quantity_received'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'sewing_order_item_id' => (int) $rawId,
                'quantity_received' => (float) ($quantities[$index] ?? 0),
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
        $quantities = is_array($payload['quantity_received'] ?? null) ? $payload['quantity_received'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'external_work_receipt_item_id' => (int) $rawId,
                'quantity_received' => (float) ($quantities[$index] ?? 0),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectResultItems(array $payload): array
    {
        $ids = is_array($payload['quality_control_check_item_id'] ?? null) ? $payload['quality_control_check_item_id'] : [];
        $approved = is_array($payload['quantity_approved'] ?? null) ? $payload['quantity_approved'] : [];
        $rejected = is_array($payload['quantity_rejected'] ?? null) ? $payload['quantity_rejected'] : [];
        $rework = is_array($payload['quantity_rework'] ?? null) ? $payload['quantity_rework'] : [];
        $modelOk = is_array($payload['model_ok'] ?? null) ? $payload['model_ok'] : [];
        $sizeOk = is_array($payload['size_ok'] ?? null) ? $payload['size_ok'] : [];
        $quantityOk = is_array($payload['quantity_ok'] ?? null) ? $payload['quantity_ok'] : [];
        $sewingOk = is_array($payload['sewing_ok'] ?? null) ? $payload['sewing_ok'] : [];
        $finishingOk = is_array($payload['finishing_ok'] ?? null) ? $payload['finishing_ok'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'quality_control_check_item_id' => (int) $rawId,
                'quantity_approved' => (float) ($approved[$index] ?? 0),
                'quantity_rejected' => (float) ($rejected[$index] ?? 0),
                'quantity_rework' => (float) ($rework[$index] ?? 0),
                'model_ok' => (int) ($modelOk[$index] ?? 0),
                'size_ok' => (int) ($sizeOk[$index] ?? 0),
                'quantity_ok' => (int) ($quantityOk[$index] ?? 0),
                'sewing_ok' => (int) ($sewingOk[$index] ?? 0),
                'finishing_ok' => (int) ($finishingOk[$index] ?? 0),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }
}
