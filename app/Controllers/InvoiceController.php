<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\RemissionRepository;
use App\Services\DocumentContextService;
use App\Services\DocumentFlowValidatorService;
use App\Services\DocumentWorkflowService;
use App\Services\NumberingService;
use App\Services\OperationalAuditService;
use App\Services\TraceabilityService;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Integrations\BillingAdapter\LocalBillingAdapter;
use Throwable;

final class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('invoices/index', [
            'invoices' => (new InvoiceRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $contractId = (int) $request->input('contract_id', 0);
        $selectedContract = $contractId > 0 ? (new ContractRepository())->findWithItems($contractId) : null;
        $remissions = (new RemissionRepository())->openForSelection();
        $selectedRemissionIds = $this->parseIds($request->input('remission_ids', ''));
        $context = $selectedRemissionIds !== [] ? (new DocumentContextService())->invoiceContext($selectedRemissionIds) : ['document' => [], 'items' => []];

        return $this->render('invoices/form', [
            'invoice' => [
                'invoice_date' => date('Y-m-d'),
                'client_id' => $context['document']['client_id'] ?? $selectedContract['client_id'] ?? '',
                'contract_id' => $context['document']['contract_id'] ?? $contractId ?: '',
                'reference_number' => $context['document']['reference_number'] ?? $selectedContract['reference_number'] ?? '',
                'contract_type' => $context['document']['contract_type'] ?? $selectedContract['contract_type'] ?? '',
                'tax_id' => $context['document']['tax_id'] ?? $selectedContract['tax_id'] ?? '',
                'billing_address' => $context['document']['billing_address'] ?? '',
                'sale_condition' => 'contado',
                'status' => 'draft',
                'notes' => '',
                'remission_ids' => $selectedRemissionIds,
            ],
            'clients' => (new ClientRepository())->search(['status' => 'active']),
            'contracts' => (new ContractRepository())->activeForSelection(),
            'selectedContract' => $selectedContract,
            'remissions' => $remissions,
            'context' => $context,
            'balances' => $context['items'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'invoice_date',
            'client_id',
            'contract_id',
            'reference_number',
            'contract_type',
            'tax_id',
            'billing_address',
            'sale_condition',
            'notes',
        ]);
        $data['invoice_number'] = trim((string) $request->input('invoice_number'));
        $data['status'] = 'draft';
        $data['billing_status'] = 'pending';
        $data['billing_payload'] = null;
        $remissionIds = $this->parseIds($request->input('remission_ids', ''));

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
            'remission_id',
            'remission_item_id',
            'product_name',
            'unit_measure',
            'quantity',
            'unit_price',
        ]);

        $errors = [];
        if ($remissionIds === []) {
            $errors[] = 'Debe seleccionar al menos una remisión.';
        }

        [$items, $validationErrors, $context] = (new DocumentFlowValidatorService())->prepareInvoiceItems($remissionIds, $items);
        $errors = array_merge($errors, $validationErrors);

        if ($items === []) {
            $errors[] = 'No hay items facturables para las remisiones seleccionadas.';
        }

        $data['client_id'] = $context['document']['client_id'] ?? $data['client_id'];
        $data['contract_id'] = $context['document']['contract_id'] ?? $data['contract_id'];
        $data['reference_number'] = $context['document']['reference_number'] ?? $data['reference_number'];
        $data['contract_type'] = $context['document']['contract_type'] ?? $data['contract_type'];
        $data['tax_id'] = $context['document']['tax_id'] ?? $data['tax_id'];
        $data['billing_address'] = $data['billing_address'] ?: ($context['document']['billing_address'] ?? '');
        $data['total_amount'] = $this->sumItems($items);

        if ($errors !== []) {
            $query = http_build_query([
                'contract_id' => $data['contract_id'],
                'remission_ids' => implode(',', $remissionIds),
            ]);
            return $this->redirectWithMessage('/invoices/create' . ($query !== '' ? '?' . $query : ''), implode(' ', $errors), 'error');
        }

        try {
            if ($data['invoice_number'] === '') {
                $data['invoice_number'] = (new NumberingService())->next('invoices');
            }
            $id = (new InvoiceRepository())->create($data, $items, $remissionIds);
            (new OperationalAuditService())->logDocumentAction(
                'invoices',
                $id,
                (string) $data['invoice_number'],
                'created',
                null,
                (string) $data['status'],
                ['invoice' => $data, 'items' => $items, 'remissions' => $remissionIds]
            );

            return $this->redirectWithMessage('/invoices/' . $id, 'Factura creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/invoices/create', 'No se pudo crear la factura: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $invoice = (new InvoiceRepository())->findWithItems((int) $id);

        if (!$invoice) {
            return $this->redirectWithMessage('/invoices', 'Factura no encontrada.', 'error');
        }

        return $this->render('invoices/show', [
            'invoice' => $invoice,
            'traceability' => (new TraceabilityService())->invoiceTrace((int) $id),
            'meta' => (new DocumentWorkflowService())->metadata('invoices', (int) $id),
        ]);
    }

    public function send(Request $request, string $id)
    {
        $repository = new InvoiceRepository();
        $invoice = $repository->findWithItems((int) $id);

        if (!$invoice) {
            return $this->redirectWithMessage('/invoices', 'Factura no encontrada.', 'error');
        }

        if ((string) $invoice['status'] !== 'confirmed') {
            return $this->redirectWithMessage('/invoices/' . $id, 'Solo las facturas confirmadas pueden enviarse al placeholder SIFEN.', 'error');
        }

        $adapter = new LocalBillingAdapter();
        $payload = $adapter->sendInvoice((int) $id);
        $repository->updateBillingStatus((int) $id, (string) ($payload['status'] ?? 'simulated'), $payload);
        (new OperationalAuditService())->logDocumentAction(
            'invoices',
            (int) $id,
            (string) $invoice['invoice_number'],
            'send_simulated',
            (string) $invoice['status'],
            (string) $invoice['status'],
            $payload
        );

        return $this->redirectWithMessage('/invoices/' . $id, 'Integración local ejecutada en modo simulado.');
    }

    public function print(Request $request, string $id)
    {
        $invoice = (new InvoiceRepository())->findWithItems((int) $id);

        if (!$invoice) {
            return $this->redirectWithMessage('/invoices', 'Factura no encontrada.', 'error');
        }

        (new OperationalAuditService())->logDocumentAction(
            'invoices',
            (int) $id,
            (string) $invoice['invoice_number'],
            'printed',
            (string) $invoice['status'],
            (string) $invoice['status'],
            ['format' => 'html']
        );

        return Response::html(View::render('invoices/print', ['invoice' => $invoice], 'layouts/print'));
    }

    public function exportCsv(Request $request, string $id)
    {
        $invoice = (new InvoiceRepository())->findWithItems((int) $id);

        if (!$invoice) {
            return $this->redirectWithMessage('/invoices', 'Factura no encontrada.', 'error');
        }

        $rows = [];
        foreach ($invoice['items'] as $item) {
            $rows[] = [
                $invoice['invoice_number'],
                $item['product_name'],
                $item['unit_measure'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_item'],
            ];
        }

        $csv = (new \App\Services\ExportService())->toCsv($rows, ['Factura', 'Producto', 'Unidad', 'Cantidad', 'Precio', 'Total']);
        (new OperationalAuditService())->logDocumentAction(
            'invoices',
            (int) $id,
            (string) $invoice['invoice_number'],
            'exported',
            (string) $invoice['status'],
            (string) $invoice['status'],
            ['format' => 'csv']
        );

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="factura-' . $invoice['invoice_number'] . '.csv"',
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
