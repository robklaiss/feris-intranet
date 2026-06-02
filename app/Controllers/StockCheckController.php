<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductionOrderRepository;
use App\Repositories\PurchaseRequisitionRepository;
use App\Repositories\RawMaterialInventoryRepository;
use App\Repositories\StockCheckRepository;
use App\Services\OperationalAuditService;
use App\Support\Request;
use Throwable;

final class StockCheckController extends Controller
{
    public function create(Request $request, string $productionOrderId)
    {
        $order = (new ProductionOrderRepository())->findWithItems((int) $productionOrderId);
        if (!$order) {
            return $this->redirectWithMessage('/production-orders', 'Orden de producción no encontrada.', 'error');
        }
        if ((string) $order['status'] !== 'confirmed') {
            return $this->redirectWithMessage('/production-orders/' . $productionOrderId, 'Solo se puede verificar stock desde una orden confirmada.', 'error');
        }

        return $this->render('stock_checks/form', [
            'order' => $order,
            'materials' => (new RawMaterialInventoryRepository())->search(['status' => 'active']),
        ]);
    }

    public function store(Request $request, string $productionOrderId)
    {
        $data = $request->only(['check_number', 'notes']);
        $items = $this->collectSelections($request->all());

        try {
            $repo = new StockCheckRepository();
            $id = $repo->createForProductionOrder((int) $productionOrderId, $data, $items);
            $check = $repo->find($id);
            $this->audit('create_stock_check', null, (string) ($check['status'] ?? ''), $check, $id);

            return $this->redirectWithMessage('/stock-checks/' . $id, 'Verificación de stock creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/production-orders/' . $productionOrderId, 'No se pudo crear la verificación: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $check = (new StockCheckRepository())->findWithItems((int) $id);
        if (!$check) {
            return $this->redirectWithMessage('/production-orders', 'Verificación de stock no encontrada.', 'error');
        }

        return $this->render('stock_checks/show', [
            'check' => $check,
            'purchaseRequisition' => (new PurchaseRequisitionRepository())->findByStockCheck((int) $id),
        ]);
    }

    public function reserve(Request $request, string $id)
    {
        $repo = new StockCheckRepository();
        $existing = $repo->find((int) $id);
        if (!$existing) {
            return $this->redirectWithMessage('/production-orders', 'Verificación de stock no encontrada.', 'error');
        }

        try {
            $repo->reserve((int) $id);
            $check = $repo->find((int) $id);
            $this->audit('reserve_stock_check', (string) $existing['status'], (string) ($check['status'] ?? ''), $check, (int) $id);

            return $this->redirectWithMessage('/stock-checks/' . $id, 'Stock reservado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/stock-checks/' . $id, 'No se pudo reservar stock: ' . $exception->getMessage(), 'error');
        }
    }

    public function cancel(Request $request, string $id)
    {
        $repo = new StockCheckRepository();
        $existing = $repo->find((int) $id);
        if (!$existing) {
            return $this->redirectWithMessage('/production-orders', 'Verificación de stock no encontrada.', 'error');
        }

        try {
            $repo->cancel((int) $id);
            $check = $repo->find((int) $id);
            $this->audit('cancel_stock_check', (string) $existing['status'], (string) ($check['status'] ?? ''), $check, (int) $id);

            return $this->redirectWithMessage('/production-orders/' . (int) $existing['production_order_id'], 'Verificación anulada y reservas liberadas.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/stock-checks/' . $id, 'No se pudo anular la verificación: ' . $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectSelections(array $payload): array
    {
        $itemIds = is_array($payload['production_order_item_id'] ?? null) ? $payload['production_order_item_id'] : [];
        $materialIds = is_array($payload['raw_material_inventory_id'] ?? null) ? $payload['raw_material_inventory_id'] : [];
        $items = [];

        foreach ($itemIds as $index => $itemId) {
            $items[] = [
                'production_order_item_id' => (int) $itemId,
                'raw_material_inventory_id' => (int) ($materialIds[$index] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $check
     */
    private function audit(string $action, ?string $previous, string $next, ?array $check, int $stockCheckId): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'stock_checks',
            $stockCheckId,
            (string) ($check['check_number'] ?? ''),
            $action,
            $previous,
            $next,
            [
                'production_order_id' => $check['production_order_id'] ?? null,
                'production_number' => $check['production_number'] ?? null,
                'stock_check_id' => $stockCheckId,
                'check_number' => $check['check_number'] ?? null,
                'status_previous' => $previous,
                'status_new' => $next,
            ]
        );
    }
}
