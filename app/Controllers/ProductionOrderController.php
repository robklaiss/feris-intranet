<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\CuttingOrderRepository;
use App\Repositories\ExternalWorkOrderRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\StockCheckRepository;
use App\Services\NumberingService;
use App\Services\OperationalAuditService;
use App\Support\Request;
use Throwable;

final class ProductionOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
            'production_stage' => (string) $request->input('production_stage', ''),
        ];

        return $this->render('production_orders/index', [
            'orders' => (new ProductionOrderRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $customerOrderId = (int) $request->input('customer_purchase_order_id', 0);
        $customerOrder = (new CustomerPurchaseOrderRepository())->findWithItems($customerOrderId);

        if (!$customerOrder || (string) $customerOrder['status'] !== 'confirmed') {
            return $this->redirectWithMessage('/customer-purchase-orders', 'Solo se puede crear producción desde una OC cliente confirmada.', 'error');
        }

        $pendingItems = (new ProductionOrderRepository())->pendingItemsForCustomerPurchaseOrder($customerOrderId);
        if ($pendingItems === []) {
            return $this->redirectWithMessage('/customer-purchase-orders/' . $customerOrderId, 'La OC cliente no tiene saldo pendiente de producción.', 'error');
        }

        return $this->render('production_orders/form', [
            'title' => 'Nueva orden de producción',
            'action' => '/production-orders',
            'order' => [
                'customer_purchase_order_id' => $customerOrderId,
                'production_number' => (new NumberingService())->next('production_orders'),
                'planned_start_date' => '',
                'planned_end_date' => '',
                'notes' => '',
            ],
            'customerOrder' => $customerOrder,
            'pendingItems' => $pendingItems,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only(['customer_purchase_order_id', 'production_number', 'planned_start_date', 'planned_end_date', 'notes']);
        $items = $this->collectItems($request->all());

        try {
            $repo = new ProductionOrderRepository();
            $id = $repo->create($data, $items);
            $order = $repo->find($id);
            $this->audit('create_production_order', null, 'draft', $order, $id, $repo->totalQuantity($id), count($items));

            return $this->redirectWithMessage('/production-orders/' . $id, 'Orden de producción creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/production-orders/create?customer_purchase_order_id=' . (int) ($data['customer_purchase_order_id'] ?? 0), 'No se pudo crear la orden de producción: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $order = (new ProductionOrderRepository())->findWithItems((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/production-orders', 'Orden de producción no encontrada.', 'error');
        }

        $stockRepo = new StockCheckRepository();

        return $this->render('production_orders/show', [
            'order' => $order,
            'stockChecks' => $stockRepo->byProductionOrder((int) $id),
            'activeReservations' => $stockRepo->activeReservations((int) $id),
            'cuttingOrders' => (new CuttingOrderRepository())->byProductionOrder((int) $id),
            'externalWorkOrders' => (new ExternalWorkOrderRepository())->search(['production_order_id' => (int) $id]),
        ]);
    }

    public function confirm(Request $request, string $id)
    {
        return $this->transition((int) $id, 'confirm_production_order', 'confirm');
    }

    public function cancel(Request $request, string $id)
    {
        return $this->transition((int) $id, 'cancel_production_order', 'cancel');
    }

    public function close(Request $request, string $id)
    {
        return $this->transition((int) $id, 'close_production_order', 'close');
    }

    private function transition(int $id, string $action, string $method)
    {
        $repo = new ProductionOrderRepository();
        $existing = $repo->find($id);
        if (!$existing) {
            return $this->redirectWithMessage('/production-orders', 'Orden de producción no encontrada.', 'error');
        }

        try {
            $repo->{$method}($id);
            $order = $repo->find($id);
            $this->audit($action, (string) $existing['status'], (string) ($order['status'] ?? ''), $order, $id, $repo->totalQuantity($id), count($repo->items($id)));

            return $this->redirectWithMessage('/production-orders/' . $id, 'Estado actualizado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/production-orders/' . $id, $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectItems(array $payload): array
    {
        $ids = is_array($payload['customer_purchase_order_item_id'] ?? null) ? $payload['customer_purchase_order_item_id'] : [];
        $quantities = is_array($payload['quantity'] ?? null) ? $payload['quantity'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $items[] = [
                'customer_purchase_order_item_id' => (int) $rawId,
                'quantity' => (float) ($quantities[$index] ?? 0),
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $order
     */
    private function audit(string $action, ?string $previous, string $next, ?array $order, int $productionOrderId, float $totalQuantity, int $itemCount): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'production_orders',
            $productionOrderId,
            (string) ($order['production_number'] ?? ''),
            $action,
            $previous,
            $next,
            [
                'client_id' => $order['client_id'] ?? null,
                'dependency_id' => $order['dependency_id'] ?? null,
                'contract_id' => $order['contract_id'] ?? null,
                'customer_purchase_order_id' => $order['customer_purchase_order_id'] ?? null,
                'production_order_id' => $productionOrderId,
                'po_number' => $order['po_number'] ?? null,
                'production_number' => $order['production_number'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
                'total_quantity' => $totalQuantity,
                'item_count' => $itemCount,
            ]
        );
    }
}
