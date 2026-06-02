<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PurchaseRequisitionRepository;
use App\Repositories\StockCheckRepository;
use App\Repositories\SupplierRepository;
use App\Support\Request;
use Throwable;

final class PurchaseRequisitionController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('purchase_requisitions/index', [
            'requisitions' => (new PurchaseRequisitionRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function createFromStockCheck(Request $request, string $stockCheckId)
    {
        $check = (new StockCheckRepository())->findWithItems((int) $stockCheckId);
        if (!$check) {
            return $this->redirectWithMessage('/production-orders', 'Verificación de stock no encontrada.', 'error');
        }

        return $this->render('purchase_requisitions/form', [
            'check' => $check,
            'existing' => (new PurchaseRequisitionRepository())->findByStockCheck((int) $stockCheckId),
        ]);
    }

    public function storeFromStockCheck(Request $request, string $stockCheckId)
    {
        try {
            $id = (new PurchaseRequisitionRepository())->createFromStockCheck((int) $stockCheckId, $request->only([
                'requisition_number',
                'requested_quantity',
                'notes',
            ]));

            return $this->redirectWithMessage('/purchase-requisitions/' . $id, 'Pedido de presupuesto generado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/stock-checks/' . $stockCheckId, 'No se pudo generar el pedido: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $requisition = (new PurchaseRequisitionRepository())->findWithDetails((int) $id);
        if (!$requisition) {
            return $this->redirectWithMessage('/purchase-requisitions', 'Pedido de presupuesto no encontrado.', 'error');
        }

        return $this->render('purchase_requisitions/show', [
            'requisition' => $requisition,
            'suppliers' => (new SupplierRepository())->search(['status' => 'active']),
        ]);
    }

    public function addSuppliers(Request $request, string $id)
    {
        $supplierIds = is_array($request->input('supplier_id')) ? $request->input('supplier_id') : [];

        try {
            (new PurchaseRequisitionRepository())->addSupplierQuoteRequests((int) $id, $supplierIds, $request->only([
                'response_due_date',
                'notes',
            ]));

            return $this->redirectWithMessage('/purchase-requisitions/' . $id, 'Proveedores asociados al pedido.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/purchase-requisitions/' . $id, 'No se pudieron asociar proveedores: ' . $exception->getMessage(), 'error');
        }
    }
}
