<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\DeliveryNoteRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Repositories\RemissionRepository;
use App\Services\DocumentReportService;
use App\Services\ExportService;
use App\Services\PdfReportService;
use App\Support\Database;
use App\Support\Request;
use App\Support\Response;
use Throwable;

final class ReportController extends Controller
{
    public function index(Request $request)
    {
        $service = new DocumentReportService();
        $types = $service->availableTypes();
        $selectedType = (string) $request->input('type', 'contracts');
        $filters = [
            'client_id' => (string) $request->input('client_id', ''),
            'contract_number' => trim((string) $request->input('contract_number', '')),
            'identifier_number' => trim((string) $request->input('identifier_number', '')),
        ];
        $audit = Database::connection()->query('SELECT * FROM audit_log ORDER BY created_at DESC, id DESC LIMIT 20')->fetchAll() ?: [];

        return $this->render('reports/index', [
            'summary' => [
                'clients' => (new ClientRepository())->count(),
                'contracts' => (new ContractRepository())->count(),
                'orders' => (new PurchaseOrderRepository())->count(),
                'notes' => (new DeliveryNoteRepository())->count(),
                'remissions' => (new RemissionRepository())->count(),
                'invoices' => (new InvoiceRepository())->count(),
            ],
            'audit' => $audit,
            'types' => $types,
            'selectedType' => $selectedType,
            'filters' => $filters,
            'clients' => $service->clients(),
            'report' => $service->report($selectedType, $filters),
        ]);
    }

    public function export(Request $request): Response
    {
        $service = new DocumentReportService();
        $type = (string) $request->input('type', 'contracts');
        $format = (string) $request->input('format', 'csv');
        $report = $service->report($type, [
            'client_id' => (string) $request->input('client_id', ''),
            'contract_number' => trim((string) $request->input('contract_number', '')),
            'identifier_number' => trim((string) $request->input('identifier_number', '')),
        ]);

        $report['filters']['client_label'] = $this->clientLabel($service->clients(), (string) ($report['filters']['client_id'] ?? ''));

        if ($format === 'pdf') {
            try {
                $pdf = (new PdfReportService())->render($report);
                return Response::make($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $type . '-report.pdf"',
                ]);
            } catch (Throwable $exception) {
                return $this->redirectWithMessage('/reports?' . http_build_query(['type' => $type] + $report['filters']), 'No se pudo generar el PDF: ' . $exception->getMessage(), 'error');
            }
        }

        $rows = [];
        foreach ($report['rows'] as $row) {
            $record = [];
            foreach ($report['columns'] as $column) {
                $value = $row[$column['key']] ?? '';
                $record[] = !empty($column['money']) ? money($value) : $value;
            }
            $rows[] = $record;
        }

        $csv = (new ExportService())->toCsv($rows, array_map(static fn (array $column): string => (string) $column['label'], $report['columns']));
        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $type . '-report.csv"',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $clients
     */
    private function clientLabel(array $clients, string $clientId): string
    {
        foreach ($clients as $client) {
            if ((string) $client['id'] === $clientId) {
                return (string) $client['name'];
            }
        }

        return '';
    }
}
