<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SupplierPurchaseOrderRepository;
use App\Repositories\SupplierQuoteRepository;
use App\Support\Request;
use Throwable;

final class SupplierPurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('supplier_purchase_orders/index', [
            'orders' => (new SupplierPurchaseOrderRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function createFromQuote(Request $request, string $quoteId)
    {
        $quote = (new SupplierQuoteRepository())->findWithItems((int) $quoteId);
        if (!$quote) {
            return $this->redirectWithMessage('/purchase-requisitions', 'Presupuesto no encontrado.', 'error');
        }

        return $this->render('supplier_purchase_orders/form', ['quote' => $quote]);
    }

    public function storeFromQuote(Request $request, string $quoteId)
    {
        try {
            $id = (new SupplierPurchaseOrderRepository())->createFromApprovedQuote((int) $quoteId, $request->only([
                'supplier_po_number',
                'iso_form_number',
                'order_date',
                'expected_delivery_date',
                'payment_terms',
                'delivery_terms',
                'purchase_reason',
                'supplier_comparison_summary',
                'product_specifications',
                'quality_requirements',
                'notes',
            ]));

            return $this->redirectWithMessage('/supplier-purchase-orders/' . $id, 'Orden de compra proveedor generada.');
        } catch (Throwable $exception) {
            $quote = (new SupplierQuoteRepository())->find((int) $quoteId);
            $fallback = $quote ? '/purchase-requisitions/' . (int) $quote['purchase_requisition_id'] : '/purchase-requisitions';
            return $this->redirectWithMessage($fallback, 'No se pudo generar la orden de compra: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $order = (new SupplierPurchaseOrderRepository())->findWithItems((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/supplier-purchase-orders', 'Orden de compra proveedor no encontrada.', 'error');
        }

        return $this->render('supplier_purchase_orders/show', ['order' => $order]);
    }

    public function confirm(Request $request, string $id)
    {
        return $this->transition((int) $id, 'confirm', 'confirmada');
    }

    public function cancel(Request $request, string $id)
    {
        return $this->transition((int) $id, 'cancel', 'anulada');
    }

    public function close(Request $request, string $id)
    {
        return $this->transition((int) $id, 'close', 'cerrada');
    }

    private function transition(int $id, string $action, string $label)
    {
        try {
            $repo = new SupplierPurchaseOrderRepository();
            match ($action) {
                'confirm' => $repo->confirm($id),
                'cancel' => $repo->cancel($id),
                'close' => $repo->close($id),
                default => null,
            };

            return $this->redirectWithMessage('/supplier-purchase-orders/' . $id, 'Orden de compra proveedor ' . $label . '.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/supplier-purchase-orders/' . $id, 'No se pudo cambiar estado: ' . $exception->getMessage(), 'error');
        }
    }
}
