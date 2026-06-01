<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\DeliveryNoteRepository;
use App\Repositories\RemissionRepository;
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

final class RemissionController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('remissions/index', [
            'remissions' => (new RemissionRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $contractId = (int) $request->input('contract_id', 0);
        $selectedContract = $contractId > 0 ? (new ContractRepository())->findWithItems($contractId) : null;
        $notes = (new DeliveryNoteRepository())->openForSelection();
        $selectedNoteIds = $this->parseIds($request->input('delivery_note_ids', ''));
        $context = $selectedNoteIds !== [] ? (new DocumentContextService())->remissionContext($selectedNoteIds) : ['document' => [], 'items' => []];

        return $this->render('remissions/form', [
            'remission' => [
                'remission_date' => date('Y-m-d'),
                'client_id' => $context['document']['client_id'] ?? $selectedContract['client_id'] ?? '',
                'contract_id' => $context['document']['contract_id'] ?? $contractId ?: '',
                'reference_number' => $context['document']['reference_number'] ?? $selectedContract['reference_number'] ?? '',
                'contract_type' => $context['document']['contract_type'] ?? $selectedContract['contract_type'] ?? '',
                'tax_id' => $context['document']['tax_id'] ?? $selectedContract['tax_id'] ?? '',
                'origin_address' => '',
                'destination_address' => $context['document']['destination_address'] ?? '',
                'transfer_start_date' => date('Y-m-d'),
                'transfer_end_date' => date('Y-m-d'),
                'vehicle_brand' => '',
                'vehicle_plate' => '',
                'carrier_name' => '',
                'carrier_tax_id' => '',
                'driver_name' => '',
                'driver_document' => '',
                'status' => 'draft',
                'notes' => '',
                'delivery_note_ids' => $selectedNoteIds,
            ],
            'clients' => (new ClientRepository())->search(['status' => 'active']),
            'contracts' => (new ContractRepository())->activeForSelection(),
            'selectedContract' => $selectedContract,
            'notesList' => $notes,
            'context' => $context,
            'balances' => $context['items'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'remission_date',
            'client_id',
            'contract_id',
            'reference_number',
            'contract_type',
            'tax_id',
            'origin_address',
            'destination_address',
            'transfer_start_date',
            'transfer_end_date',
            'vehicle_brand',
            'vehicle_plate',
            'carrier_name',
            'carrier_tax_id',
            'driver_name',
            'driver_document',
            'notes',
        ]);
        $data['status'] = 'draft';
        $noteIds = $this->parseIds($request->input('delivery_note_ids', ''));

        if (!empty($data['contract_id'])) {
            $selectedContract = (new ContractRepository())->findWithItems((int) $data['contract_id']);
            if ($selectedContract) {
                $data['client_id'] = $data['client_id'] ?: $selectedContract['client_id'];
                $data['reference_number'] = $data['reference_number'] ?: $selectedContract['reference_number'];
                $data['contract_type'] = $data['contract_type'] ?: $selectedContract['contract_type'];
                $data['tax_id'] = $data['tax_id'] ?: $selectedContract['tax_id'];
            }
        }

        $items = $this->collectLineItems($request->all(), [
            'delivery_note_id',
            'delivery_note_item_id',
            'product_name',
            'unit_measure',
            'quantity',
            'unit_price',
        ]);

        $errors = [];
        if ($noteIds === []) {
            $errors[] = 'Debe seleccionar al menos una nota interna.';
        }

        [$items, $validationErrors, $context] = (new DocumentFlowValidatorService())->prepareRemissionItems($noteIds, $items);
        $errors = array_merge($errors, $validationErrors);

        if ($items === []) {
            $errors[] = 'No hay items remisionables para las notas seleccionadas.';
        }

        $data['client_id'] = $context['document']['client_id'] ?? $data['client_id'];
        $data['contract_id'] = $context['document']['contract_id'] ?? $data['contract_id'];
        $data['reference_number'] = $context['document']['reference_number'] ?? $data['reference_number'];
        $data['contract_type'] = $context['document']['contract_type'] ?? $data['contract_type'];
        $data['tax_id'] = $context['document']['tax_id'] ?? $data['tax_id'];
        $data['total_amount'] = $this->sumItems($items);

        if ($errors !== []) {
            $query = http_build_query([
                'contract_id' => $data['contract_id'],
                'delivery_note_ids' => implode(',', $noteIds),
            ]);
            return $this->redirectWithMessage('/remissions/create' . ($query !== '' ? '?' . $query : ''), implode(' ', $errors), 'error');
        }

        try {
            $data['remission_number'] = (new NumberingService())->next('remissions');
            $id = (new RemissionRepository())->create($data, $items, $noteIds);
            (new OperationalAuditService())->logDocumentAction(
                'remissions',
                $id,
                (string) $data['remission_number'],
                'created',
                null,
                (string) $data['status'],
                ['remission' => $data, 'items' => $items, 'notes' => $noteIds]
            );

            return $this->redirectWithMessage('/remissions/' . $id, 'Remisión creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/remissions/create', 'No se pudo crear la remisión: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $remission = (new RemissionRepository())->findWithItems((int) $id);

        if (!$remission) {
            return $this->redirectWithMessage('/remissions', 'Remisión no encontrada.', 'error');
        }

        return $this->render('remissions/show', [
            'remission' => $remission,
            'balances' => (new BalanceService())->remissionItemBalances([(int) $id]),
            'traceability' => (new TraceabilityService())->remissionTrace((int) $id),
            'meta' => (new DocumentWorkflowService())->metadata('remissions', (int) $id),
        ]);
    }

    public function print(Request $request, string $id)
    {
        $remission = (new RemissionRepository())->findWithItems((int) $id);

        if (!$remission) {
            return $this->redirectWithMessage('/remissions', 'Remisión no encontrada.', 'error');
        }

        (new OperationalAuditService())->logDocumentAction(
            'remissions',
            (int) $id,
            (string) $remission['remission_number'],
            'printed',
            (string) $remission['status'],
            (string) $remission['status'],
            ['format' => 'html']
        );

        return Response::html(View::render('remissions/print', ['remission' => $remission], 'layouts/print'));
    }

    public function exportCsv(Request $request, string $id)
    {
        $remission = (new RemissionRepository())->findWithItems((int) $id);

        if (!$remission) {
            return $this->redirectWithMessage('/remissions', 'Remisión no encontrada.', 'error');
        }

        $rows = [];
        foreach ($remission['items'] as $item) {
            $rows[] = [
                $remission['remission_number'],
                $item['product_name'],
                $item['unit_measure'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_item'],
            ];
        }

        $csv = (new \App\Services\ExportService())->toCsv($rows, ['Remisión', 'Producto', 'Unidad', 'Cantidad', 'Precio', 'Total']);
        (new OperationalAuditService())->logDocumentAction(
            'remissions',
            (int) $id,
            (string) $remission['remission_number'],
            'exported',
            (string) $remission['status'],
            (string) $remission['status'],
            ['format' => 'csv']
        );

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="remision-' . $remission['remission_number'] . '.csv"',
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
