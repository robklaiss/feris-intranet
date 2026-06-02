<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PurchaseRequisitionRepository;
use App\Repositories\SupplierQuoteRepository;
use App\Support\Request;
use Throwable;

final class SupplierQuoteController extends Controller
{
    public function create(Request $request, string $requisitionId, string $supplierId)
    {
        $requisition = (new PurchaseRequisitionRepository())->findWithDetails((int) $requisitionId);
        if (!$requisition) {
            return $this->redirectWithMessage('/purchase-requisitions', 'Pedido de presupuesto no encontrado.', 'error');
        }

        return $this->render('supplier_quotes/form', [
            'requisition' => $requisition,
            'supplierId' => (int) $supplierId,
        ]);
    }

    public function store(Request $request, string $requisitionId, string $supplierId)
    {
        try {
            $quoteId = (new SupplierQuoteRepository())->registerReceivedQuote(
                (int) $requisitionId,
                (int) $supplierId,
                $request->only([
                    'quote_number',
                    'quote_date',
                    'currency',
                    'tax_amount',
                    'total_amount',
                    'delivery_days',
                    'payment_terms',
                    'attachment_path',
                    'notes',
                ]),
                $this->collectQuoteItems($request->all())
            );

            return $this->redirectWithMessage('/purchase-requisitions/' . $requisitionId . '#quote-' . $quoteId, 'Presupuesto registrado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/purchase-requisitions/' . $requisitionId, 'No se pudo registrar el presupuesto: ' . $exception->getMessage(), 'error');
        }
    }

    public function approve(Request $request, string $id)
    {
        $quote = (new SupplierQuoteRepository())->find((int) $id);
        if (!$quote) {
            return $this->redirectWithMessage('/purchase-requisitions', 'Presupuesto no encontrado.', 'error');
        }

        try {
            (new SupplierQuoteRepository())->approve((int) $id, trim((string) $request->input('override_reason', '')));
            return $this->redirectWithMessage('/purchase-requisitions/' . (int) $quote['purchase_requisition_id'], 'Presupuesto aprobado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/purchase-requisitions/' . (int) $quote['purchase_requisition_id'], 'No se pudo aprobar el presupuesto: ' . $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectQuoteItems(array $payload): array
    {
        $itemIds = is_array($payload['purchase_requisition_item_id'] ?? null) ? $payload['purchase_requisition_item_id'] : [];
        $items = [];

        foreach ($itemIds as $index => $itemId) {
            $quantity = (float) ($payload['quantity'][$index] ?? 0);
            $unitPrice = (float) ($payload['unit_price'][$index] ?? 0);
            $description = trim((string) ($payload['description'][$index] ?? ''));

            if ((int) $itemId <= 0 || ($description === '' && $quantity <= 0)) {
                continue;
            }

            $items[] = [
                'purchase_requisition_item_id' => (int) $itemId,
                'description' => $description,
                'unit' => trim((string) ($payload['unit'][$index] ?? '')),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => trim((string) ($payload['total_price'][$index] ?? '')) === '' ? $quantity * $unitPrice : (float) $payload['total_price'][$index],
                'notes' => trim((string) ($payload['item_notes'][$index] ?? '')),
            ];
        }

        return $items;
    }
}
