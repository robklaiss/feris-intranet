<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Services\BalanceService;
use App\Services\DocumentContextService;
use App\Services\DocumentFlowValidatorService;
use App\Services\DocumentWorkflowService;
use App\Services\NumberingService;
use App\Services\OperationalAuditService;
use App\Services\ProvisionalContractSyncService;
use App\Services\TraceabilityService;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('purchase_orders/index', [
            'orders' => (new PurchaseOrderRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $contractId = (int) $request->input('contract_id', 0);
        $selectedContract = null;
        $context = ['document' => [], 'items' => []];

        if ($contractId > 0) {
            $selectedContract = (new ContractRepository())->findWithItems($contractId);
            $context = (new DocumentContextService())->purchaseOrderContext($contractId);
        }

        return $this->render('purchase_orders/form', [
            'order' => [
                'order_date' => date('Y-m-d'),
                'order_number' => '',
                'identifier_number' => $context['document']['identifier_number'] ?? '',
                'client_id' => $context['document']['client_id'] ?? '',
                'contract_id' => $contractId ?: '',
                'contract_type' => $context['document']['contract_type'] ?? '',
                'tax_id' => $context['document']['tax_id'] ?? '',
                'status' => 'draft',
                'notes' => '',
                'is_provisional' => $context['document']['is_provisional'] ?? 0,
                'provisional_data' => '',
            ],
            'clients' => (new ClientRepository())->search(),
            'contracts' => (new ContractRepository())->activeForSelection(),
            'selectedContract' => $selectedContract,
            'contractBalances' => $context['items'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'order_date',
            'order_number',
            'identifier_number',
            'client_id',
            'contract_id',
            'contract_type',
            'tax_id',
            'notes',
            'provisional_data',
        ]);
        $data['status'] = 'draft';
        $data['is_provisional'] = $request->input('is_provisional') ? 1 : 0;
        $data['is_manual'] = empty($data['contract_id']) ? 1 : 0;
        $data['order_number'] = trim((string) $data['order_number']);

        $syncService = new ProvisionalContractSyncService();
        $validator = new DocumentFlowValidatorService();
        $errors = [];

        if (!empty($data['contract_id'])) {
            $selectedContract = (new ContractRepository())->findWithItems((int) $data['contract_id']);
            if ($selectedContract && (string) $selectedContract['status'] !== 'confirmed') {
                $errors[] = 'Solo se pueden emitir órdenes desde contratos confirmados.';
            }
            if ($selectedContract) {
                $data['client_id'] = $data['client_id'] ?: $selectedContract['client_id'];
                $data['identifier_number'] = $data['identifier_number'] ?: $selectedContract['reference_number'];
                $data['contract_type'] = $data['contract_type'] ?: $selectedContract['contract_type'];
                $data['tax_id'] = $data['tax_id'] ?: $selectedContract['tax_id'];
            }
            $data = $syncService->enrichOrderData($data, $selectedContract);
        } else {
            $data = $syncService->enrichOrderData($data, null);
        }

        $items = $this->collectLineItems($request->all(), [
            'contract_item_id',
            'product_name',
            'unit_measure',
            'quantity',
            'unit_price',
        ]);
        $data['total_amount'] = $this->sumItems($items);

        if (trim((string) $data['order_date']) === '') {
            $errors[] = 'La fecha de la orden es obligatoria.';
        }
        if ($items === []) {
            $errors[] = 'Debe ingresar al menos un item en la orden.';
        }

        if (!empty($data['contract_id'])) {
            [$items, $validationErrors] = $validator->preparePurchaseOrderItems((int) $data['contract_id'], $items);
            $errors = array_merge($errors, $validationErrors);
        }

        $data['total_amount'] = $this->sumItems($items);

        if ($errors !== []) {
            return $this->redirectWithMessage('/purchase-orders/create' . (!empty($data['contract_id']) ? '?contract_id=' . $data['contract_id'] : ''), implode(' ', $errors), 'error');
        }

        try {
            if ($data['order_number'] === '') {
                $data['order_number'] = (new NumberingService())->next('purchase_orders');
            }
            $id = (new PurchaseOrderRepository())->create($data, $items);
            (new OperationalAuditService())->logDocumentAction(
                'purchase_orders',
                $id,
                (string) $data['order_number'],
                'created',
                null,
                (string) $data['status'],
                ['order' => $data, 'items' => $items]
            );

            return $this->redirectWithMessage('/purchase-orders/' . $id, 'Orden de compra creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/purchase-orders/create', 'No se pudo crear la orden: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $order = (new PurchaseOrderRepository())->findWithItems((int) $id);

        if (!$order) {
            return $this->redirectWithMessage('/purchase-orders', 'Orden no encontrada.', 'error');
        }

        return $this->render('purchase_orders/show', [
            'order' => $order,
            'balances' => (new BalanceService())->purchaseOrderItemBalances((int) $id),
            'traceability' => (new TraceabilityService())->purchaseOrderTrace((int) $id),
            'meta' => (new DocumentWorkflowService())->metadata('purchase_orders', (int) $id),
        ]);
    }

    public function print(Request $request, string $id)
    {
        $order = (new PurchaseOrderRepository())->findWithItems((int) $id);

        if (!$order) {
            return $this->redirectWithMessage('/purchase-orders', 'Orden no encontrada.', 'error');
        }

        (new OperationalAuditService())->logDocumentAction(
            'purchase_orders',
            (int) $id,
            (string) $order['order_number'],
            'printed',
            (string) $order['status'],
            (string) $order['status'],
            ['format' => 'html']
        );

        return Response::html(View::render('purchase_orders/print', ['order' => $order], 'layouts/print'));
    }

    public function exportCsv(Request $request, string $id)
    {
        $order = (new PurchaseOrderRepository())->findWithItems((int) $id);

        if (!$order) {
            return $this->redirectWithMessage('/purchase-orders', 'Orden no encontrada.', 'error');
        }

        $rows = [];
        foreach ($order['items'] as $item) {
            $rows[] = [
                $order['order_number'],
                $item['product_name'],
                $item['unit_measure'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_item'],
            ];
        }

        $csv = (new \App\Services\ExportService())->toCsv($rows, ['Orden', 'Producto', 'Unidad', 'Cantidad', 'Precio', 'Total']);
        (new OperationalAuditService())->logDocumentAction(
            'purchase_orders',
            (int) $id,
            (string) $order['order_number'],
            'exported',
            (string) $order['status'],
            (string) $order['status'],
            ['format' => 'csv']
        );

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orden-' . $order['order_number'] . '.csv"',
        ]);
    }
}
