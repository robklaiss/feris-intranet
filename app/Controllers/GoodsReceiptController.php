<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GoodsReceiptRepository;
use App\Repositories\SupplierPurchaseOrderRepository;
use App\Support\Request;
use Throwable;

final class GoodsReceiptController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('goods_receipts/index', [
            'receipts' => (new GoodsReceiptRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function createFromSupplierPurchaseOrder(Request $request, string $id)
    {
        $order = (new SupplierPurchaseOrderRepository())->findWithItems((int) $id);
        if (!$order) {
            return $this->redirectWithMessage('/supplier-purchase-orders', 'Orden de compra proveedor no encontrada.', 'error');
        }

        $repo = new GoodsReceiptRepository();

        return $this->render('goods_receipts/form', [
            'order' => $order,
            'pendingItems' => $repo->pendingItemsForSupplierPurchaseOrder((int) $id),
            'receipt' => $this->emptyReceipt(),
        ]);
    }

    public function storeFromSupplierPurchaseOrder(Request $request, string $id)
    {
        $data = $request->only([
            'receipt_number',
            'received_at',
            'delivery_note_number',
            'invoice_number',
            'notes',
        ]);

        try {
            $receiptId = (new GoodsReceiptRepository())->createDraftFromSupplierPurchaseOrder((int) $id, $data, $this->itemsFromRequest($request));
            return $this->redirectWithMessage('/goods-receipts/' . $receiptId, 'Recepción guardada en borrador.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/supplier-purchase-orders/' . $id . '/goods-receipts/create', 'No se pudo guardar la recepción: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $receipt = (new GoodsReceiptRepository())->findWithItems((int) $id);
        if (!$receipt) {
            return $this->redirectWithMessage('/goods-receipts', 'Recepción no encontrada.', 'error');
        }

        return $this->render('goods_receipts/show', ['receipt' => $receipt]);
    }

    public function confirm(Request $request, string $id)
    {
        try {
            (new GoodsReceiptRepository())->confirm((int) $id);
            return $this->redirectWithMessage('/goods-receipts/' . $id, 'Recepción confirmada e ingresada a inventario.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/goods-receipts/' . $id, 'No se pudo confirmar la recepción: ' . $exception->getMessage(), 'error');
        }
    }

    public function cancel(Request $request, string $id)
    {
        try {
            (new GoodsReceiptRepository())->cancelDraft((int) $id);
            return $this->redirectWithMessage('/goods-receipts/' . $id, 'Recepción anulada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/goods-receipts/' . $id, 'No se pudo anular la recepción: ' . $exception->getMessage(), 'error');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsFromRequest(Request $request): array
    {
        $ids = is_array($request->input('supplier_purchase_order_item_id')) ? $request->input('supplier_purchase_order_item_id') : [];
        $received = is_array($request->input('received_quantity')) ? $request->input('received_quantity') : [];
        $accepted = is_array($request->input('accepted_quantity')) ? $request->input('accepted_quantity') : [];
        $rejected = is_array($request->input('rejected_quantity')) ? $request->input('rejected_quantity') : [];
        $internalCodes = is_array($request->input('internal_code')) ? $request->input('internal_code') : [];
        $materialTypes = is_array($request->input('material_type')) ? $request->input('material_type') : [];
        $lotNumbers = is_array($request->input('lot_number')) ? $request->input('lot_number') : [];
        $locations = is_array($request->input('location')) ? $request->input('location') : [];
        $costs = is_array($request->input('cost')) ? $request->input('cost') : [];
        $qualityStatuses = is_array($request->input('quality_status')) ? $request->input('quality_status') : [];
        $notes = is_array($request->input('item_notes')) ? $request->input('item_notes') : [];

        $items = [];
        foreach (array_values($ids) as $index => $rawId) {
            $items[] = [
                'supplier_purchase_order_item_id' => (int) $rawId,
                'received_quantity' => $received[$index] ?? 0,
                'accepted_quantity' => $accepted[$index] ?? 0,
                'rejected_quantity' => $rejected[$index] ?? 0,
                'internal_code' => $internalCodes[$index] ?? '',
                'material_type' => $materialTypes[$index] ?? '',
                'lot_number' => $lotNumbers[$index] ?? '',
                'location' => $locations[$index] ?? '',
                'cost' => $costs[$index] ?? '',
                'quality_status' => $qualityStatuses[$index] ?? 'pending',
                'notes' => $notes[$index] ?? '',
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyReceipt(): array
    {
        return [
            'receipt_number' => '',
            'received_at' => date('Y-m-d H:i:s'),
            'delivery_note_number' => '',
            'invoice_number' => '',
            'notes' => '',
        ];
    }
}
