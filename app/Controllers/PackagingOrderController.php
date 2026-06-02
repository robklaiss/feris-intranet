<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PackagingOrderRepository;
use App\Support\Request;
use Throwable;

final class PackagingOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        return $this->render('packaging_orders/index', [
            'orders' => (new PackagingOrderRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function createFromQualityControl(Request $request, string $qualityControlCheckId)
    {
        $repo = new PackagingOrderRepository();

        try {
            $source = $repo->buildDraftContextFromQualityControl((int) $qualityControlCheckId);
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-control/' . (int) $qualityControlCheckId, $exception->getMessage(), 'error');
        }

        return $this->render('packaging_orders/form', [
            'title' => 'Nuevo empaquetado',
            'action' => '/quality-control/' . (int) $qualityControlCheckId . '/packaging-orders',
            'source' => $source,
            'order' => ['packaging_number' => '', 'notes' => ''],
        ]);
    }

    public function storeFromQualityControl(Request $request, string $qualityControlCheckId)
    {
        $repo = new PackagingOrderRepository();

        try {
            $id = $repo->createFromQualityControl(
                (int) $qualityControlCheckId,
                $request->only(['packaging_number', 'notes']),
                $this->collectDraftItems($request->all())
            );

            return $this->redirectWithMessage('/packaging-orders/' . $id, 'Orden de empaquetado creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/quality-control/' . (int) $qualityControlCheckId . '/packaging-orders/create', 'No se pudo crear el empaquetado: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $order = (new PackagingOrderRepository())->findWithDetails((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/packaging-orders', 'Orden de empaquetado no encontrada.', 'error');
        }

        return $this->render('packaging_orders/show', ['order' => $order]);
    }

    public function pack(Request $request, string $id)
    {
        $repo = new PackagingOrderRepository();

        try {
            $repo->pack(
                (int) $id,
                $this->collectPackItems($request->all()),
                trim((string) $request->input('location', '')) ?: null
            );

            return $this->redirectWithMessage('/packaging-orders/' . (int) $id, 'Empaquetado confirmado e inventario terminado creado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/packaging-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function cancel(Request $request, string $id)
    {
        $repo = new PackagingOrderRepository();

        try {
            $repo->cancelDraft((int) $id);
            return $this->redirectWithMessage('/packaging-orders/' . (int) $id, 'Empaquetado anulado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/packaging-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function close(Request $request, string $id)
    {
        $repo = new PackagingOrderRepository();

        try {
            $repo->close((int) $id);
            return $this->redirectWithMessage('/packaging-orders/' . (int) $id, 'Empaquetado cerrado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/packaging-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectDraftItems(array $payload): array
    {
        $ids = is_array($payload['quality_control_check_item_id'] ?? null) ? $payload['quality_control_check_item_id'] : [];
        $quantities = is_array($payload['quantity_to_pack'] ?? null) ? $payload['quantity_to_pack'] : [];
        $labels = is_array($payload['label'] ?? null) ? $payload['label'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'quality_control_check_item_id' => (int) $rawId,
                'quantity_to_pack' => (float) ($quantities[$index] ?? 0),
                'label' => trim((string) ($labels[$index] ?? '')),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectPackItems(array $payload): array
    {
        $ids = is_array($payload['packaging_order_item_id'] ?? null) ? $payload['packaging_order_item_id'] : [];
        $quantities = is_array($payload['quantity_packed'] ?? null) ? $payload['quantity_packed'] : [];
        $packages = is_array($payload['package_code'] ?? null) ? $payload['package_code'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'packaging_order_item_id' => (int) $rawId,
                'quantity_packed' => (float) ($quantities[$index] ?? 0),
                'package_code' => trim((string) ($packages[$index] ?? '')),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }
}
