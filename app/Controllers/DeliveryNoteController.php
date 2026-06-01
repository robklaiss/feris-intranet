<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ContractRepository;
use App\Repositories\DeliveryNoteRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Services\BalanceService;
use App\Services\DocumentContextService;
use App\Services\DocumentFlowValidatorService;
use App\Services\DocumentWorkflowService;
use App\Services\NumberingService;
use App\Services\OperationalAuditService;
use App\Services\TraceabilityService;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class DeliveryNoteController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('delivery_notes/index', [
            'notes' => (new DeliveryNoteRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $contractId = (int) $request->input('contract_id', 0);
        $purchaseOrderIds = $this->parseIds($request->input('purchase_order_ids', $request->input('purchase_order_id', '')));
        $selectedOrder = count($purchaseOrderIds) === 1 ? (new PurchaseOrderRepository())->findWithItems($purchaseOrderIds[0]) : null;
        $context = (new DocumentContextService())->deliveryNoteContext($contractId ?: null, $purchaseOrderIds);

        return $this->render('delivery_notes/form', [
            'note' => [
                'note_date' => date('Y-m-d'),
                'purchase_order_id' => $purchaseOrderIds[0] ?? '',
                'purchase_order_ids' => $context['document']['purchase_order_ids'] ?? $purchaseOrderIds,
                'contract_id' => $contractId ?: ($context['document']['contract_id'] ?? ''),
                'client_id' => $context['document']['client_id'] ?? '',
                'identifier_number' => $context['document']['identifier_number'] ?? '',
                'contract_type' => $context['document']['contract_type'] ?? '',
                'tax_id' => $context['document']['tax_id'] ?? '',
                'status' => 'draft',
                'delivery_address' => $selectedOrder['client_addresses'] ?? '',
                'receiver_name' => '',
                'receiver_signature' => '',
                'issuer_name' => '',
                'issuer_signature' => '',
                'notes' => '',
            ],
            'contracts' => (new ContractRepository())->activeForSelection(),
            'orders' => (new PurchaseOrderRepository())->openForSelection(),
            'selectedOrder' => $selectedOrder,
            'context' => $context,
            'balances' => $context['items'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'contract_id',
            'client_id',
            'identifier_number',
            'contract_type',
            'tax_id',
            'purchase_order_id',
            'note_date',
            'delivery_address',
            'receiver_name',
            'receiver_signature',
            'issuer_name',
            'issuer_signature',
            'notes',
        ]);
        $data['status'] = 'draft';

        $purchaseOrderIds = $this->parseIds($request->input('purchase_order_ids', $data['purchase_order_id']));

        $items = $this->collectLineItems($request->all(), [
            'purchase_order_id',
            'purchase_order_item_id',
            'product_name',
            'unit_measure',
            'quantity',
            'unit_price',
        ]);
        $data['total_amount'] = $this->sumItems($items);

        $errors = [];
        if ($purchaseOrderIds === [] && !empty($data['contract_id'])) {
            $context = (new DocumentContextService())->deliveryNoteContext((int) $data['contract_id'], []);
            $purchaseOrderIds = $context['document']['purchase_order_ids'] ?? [];
        }
        if ($purchaseOrderIds === []) {
            $errors[] = 'Debe seleccionar al menos una orden de compra o un contrato con órdenes abiertas.';
        }

        [$items, $validationErrors, $context] = (new DocumentFlowValidatorService())->prepareDeliveryNoteItems(
            !empty($data['contract_id']) ? (int) $data['contract_id'] : null,
            $purchaseOrderIds,
            $items
        );
        $errors = array_merge($errors, $validationErrors);

        if ($items === []) {
            $errors[] = 'Debe indicar cantidades para la nota de entrega.';
        }

        $data['purchase_order_id'] = $purchaseOrderIds[0] ?? null;
        $data['client_id'] = $context['document']['client_id'] ?? null;
        $data['contract_id'] = $context['document']['contract_id'] ?? null;
        $data['identifier_number'] = $context['document']['identifier_number'] ?? null;
        $data['contract_type'] = $context['document']['contract_type'] ?? null;
        $data['tax_id'] = $context['document']['tax_id'] ?? null;
        $data['total_amount'] = $this->sumItems($items);

        if ($errors !== []) {
            $query = http_build_query([
                'contract_id' => $data['contract_id'],
                'purchase_order_ids' => implode(',', $purchaseOrderIds),
            ]);
            return $this->redirectWithMessage('/delivery-notes/create' . ($query !== '' ? '?' . $query : ''), implode(' ', $errors), 'error');
        }

        try {
            $data['note_number'] = (new NumberingService())->next('delivery_notes');
            $id = (new DeliveryNoteRepository())->create($data, $items, $purchaseOrderIds);
            (new OperationalAuditService())->logDocumentAction(
                'delivery_notes',
                $id,
                (string) $data['note_number'],
                'created',
                null,
                (string) $data['status'],
                ['note' => $data, 'items' => $items, 'orders' => $purchaseOrderIds]
            );

            return $this->redirectWithMessage('/delivery-notes/' . $id, 'Nota de entrega creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/delivery-notes/create', 'No se pudo crear la nota: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $note = (new DeliveryNoteRepository())->findWithItems((int) $id);

        if (!$note) {
            return $this->redirectWithMessage('/delivery-notes', 'Nota no encontrada.', 'error');
        }

        return $this->render('delivery_notes/show', [
            'note' => $note,
            'balances' => (new BalanceService())->deliveryNoteItemBalancesForNotes([(int) $id]),
            'traceability' => (new TraceabilityService())->deliveryNoteTrace((int) $id),
            'meta' => (new DocumentWorkflowService())->metadata('delivery_notes', (int) $id),
        ]);
    }

    public function print(Request $request, string $id)
    {
        $note = (new DeliveryNoteRepository())->findWithItems((int) $id);

        if (!$note) {
            return $this->redirectWithMessage('/delivery-notes', 'Nota no encontrada.', 'error');
        }

        (new OperationalAuditService())->logDocumentAction(
            'delivery_notes',
            (int) $id,
            (string) $note['note_number'],
            'printed',
            (string) $note['status'],
            (string) $note['status'],
            ['format' => 'html']
        );

        return Response::html(View::render('delivery_notes/print', ['note' => $note], 'layouts/print'));
    }

    public function exportCsv(Request $request, string $id)
    {
        $note = (new DeliveryNoteRepository())->findWithItems((int) $id);

        if (!$note) {
            return $this->redirectWithMessage('/delivery-notes', 'Nota no encontrada.', 'error');
        }

        $rows = [];
        foreach ($note['items'] as $item) {
            $rows[] = [
                $note['note_number'],
                $item['product_name'],
                $item['unit_measure'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_item'],
            ];
        }

        $csv = (new \App\Services\ExportService())->toCsv($rows, ['Nota', 'Producto', 'Unidad', 'Cantidad', 'Precio', 'Total']);
        (new OperationalAuditService())->logDocumentAction(
            'delivery_notes',
            (int) $id,
            (string) $note['note_number'],
            'exported',
            (string) $note['status'],
            (string) $note['status'],
            ['format' => 'csv']
        );

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nota-' . $note['note_number'] . '.csv"',
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function parseIds(mixed $raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $parts = array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $parts), static fn (string $value): bool => $value !== '');
        return array_values(array_unique(array_map('intval', $parts)));
    }
}
