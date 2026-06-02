<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CuttingOrderRepository;
use App\Repositories\ExternalWorkOrderRepository;
use App\Services\OperationalAuditService;
use App\Support\Request;
use Throwable;

final class CuttingOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        return $this->render('cutting_orders/index', [
            'orders' => (new CuttingOrderRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, string $productionOrderId)
    {
        $repo = new CuttingOrderRepository();

        try {
            $productionOrder = $repo->buildDraftContext((int) $productionOrderId);
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/production-orders/' . (int) $productionOrderId, $exception->getMessage(), 'error');
        }

        return $this->render('cutting_orders/form', [
            'title' => 'Nueva orden de corte',
            'action' => '/production-orders/' . (int) $productionOrderId . '/cutting-orders',
            'order' => [
                'planned_date' => '',
                'cut_by' => '',
                'notes' => '',
            ],
            'productionOrder' => $productionOrder,
        ]);
    }

    public function store(Request $request, string $productionOrderId)
    {
        $repo = new CuttingOrderRepository();
        $data = $request->only(['cutting_number', 'planned_date', 'cut_by', 'notes']);
        $items = $this->collectItems($request->all());

        try {
            $id = $repo->createFromProductionOrder((int) $productionOrderId, $data, $items);
            $order = $repo->findWithDetails($id);
            $this->audit('create_cutting_order', null, 'draft', $order, $id);

            return $this->redirectWithMessage('/cutting-orders/' . $id, 'Orden de corte creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/production-orders/' . (int) $productionOrderId . '/cutting-orders/create', 'No se pudo crear la orden de corte: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $order = (new CuttingOrderRepository())->findWithDetails((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/cutting-orders', 'Orden de corte no encontrada.', 'error');
        }

        $externalRepo = new ExternalWorkOrderRepository();

        return $this->render('cutting_orders/show', [
            'order' => $order,
            'externalWorkOrders' => $externalRepo->byCuttingOrder((int) $id),
            'externalEligibleItems' => in_array((string) $order['status'], ['completed', 'closed'], true)
                ? $externalRepo->eligibleItemsFromCuttingOrder((int) $id)
                : [],
        ]);
    }

    public function confirm(Request $request, string $id)
    {
        return $this->transition((int) $id, 'confirm_cutting_order', 'confirm');
    }

    public function complete(Request $request, string $id)
    {
        $repo = new CuttingOrderRepository();
        $existing = $repo->find((int) $id);
        if (!$existing) {
            return $this->redirectWithMessage('/cutting-orders', 'Orden de corte no encontrada.', 'error');
        }

        try {
            $repo->complete((int) $id, $this->collectCutQuantities($request->all()));
            $order = $repo->findWithDetails((int) $id);
            $this->audit('complete_cutting_order', (string) $existing['status'], (string) ($order['status'] ?? ''), $order, (int) $id);

            return $this->redirectWithMessage('/cutting-orders/' . (int) $id, 'Orden de corte completada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/cutting-orders/' . (int) $id, $exception->getMessage(), 'error');
        }
    }

    public function cancel(Request $request, string $id)
    {
        return $this->transition((int) $id, 'cancel_cutting_order', 'cancelDraft');
    }

    public function close(Request $request, string $id)
    {
        return $this->transition((int) $id, 'close_cutting_order', 'close');
    }

    private function transition(int $id, string $action, string $method)
    {
        $repo = new CuttingOrderRepository();
        $existing = $repo->find($id);
        if (!$existing) {
            return $this->redirectWithMessage('/cutting-orders', 'Orden de corte no encontrada.', 'error');
        }

        try {
            $repo->{$method}($id);
            $order = $repo->findWithDetails($id);
            $this->audit($action, (string) $existing['status'], (string) ($order['status'] ?? ''), $order, $id);

            return $this->redirectWithMessage('/cutting-orders/' . $id, 'Estado actualizado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/cutting-orders/' . $id, $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectItems(array $payload): array
    {
        $ids = is_array($payload['production_order_item_id'] ?? null) ? $payload['production_order_item_id'] : [];
        $quantities = is_array($payload['quantity_to_cut'] ?? null) ? $payload['quantity_to_cut'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'production_order_item_id' => (int) $rawId,
                'quantity_to_cut' => (float) ($quantities[$index] ?? 0),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, float>
     */
    private function collectCutQuantities(array $payload): array
    {
        $ids = is_array($payload['cutting_order_item_id'] ?? null) ? $payload['cutting_order_item_id'] : [];
        $quantities = is_array($payload['quantity_cut'] ?? null) ? $payload['quantity_cut'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[(int) $rawId] = (float) ($quantities[$index] ?? 0);
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $order
     */
    private function audit(string $action, ?string $previous, string $next, ?array $order, int $cuttingOrderId): void
    {
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];
        $materials = is_array($order['materials'] ?? null) ? $order['materials'] : [];

        (new OperationalAuditService())->logDocumentAction(
            'cutting_orders',
            $cuttingOrderId,
            (string) ($order['cutting_number'] ?? ''),
            $action,
            $previous,
            $next,
            [
                'client_id' => $order['client_id'] ?? null,
                'contract_id' => $order['contract_id'] ?? null,
                'customer_purchase_order_id' => $order['customer_purchase_order_id'] ?? null,
                'production_order_id' => $order['production_order_id'] ?? null,
                'cutting_order_id' => $cuttingOrderId,
                'cutting_number' => $order['cutting_number'] ?? null,
                'production_number' => $order['production_number'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
                'item_count' => count($items),
                'material_count' => count($materials),
            ]
        );
    }
}
