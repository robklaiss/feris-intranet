<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ClientBillingContactRepository;
use App\Repositories\ClientDependencyRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\ContractDncpDataRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Services\BalanceService;
use App\Services\DocumentWorkflowService;
use App\Services\ExportService;
use App\Services\OperationalAuditService;
use App\Services\ProvisionalContractSyncService;
use App\Services\TraceabilityService;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class ContractController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('contracts/index', [
            'contracts' => (new ContractRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $id)
    {
        $repository = new ContractRepository();
        $contract = $repository->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        return $this->render('contracts/show', [
            'contract' => $contract,
            'balances' => (new BalanceService())->contractItemBalances((int) $id),
            'traceability' => (new TraceabilityService())->contractTrace((int) $id),
            'meta' => (new DocumentWorkflowService())->metadata('contracts', (int) $id),
            'customerPurchaseOrders' => (new CustomerPurchaseOrderRepository())->byContract((int) $id),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('contracts/form', [
            'contract' => $this->emptyContract(),
            'action' => '/contracts',
            'title' => 'Nuevo contrato',
            'clients' => (new ClientRepository())->search(),
            'billingContacts' => (new ClientBillingContactRepository())->allForSelection(),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $items, $dncpData, $errors] = $this->extractContractPayload($request);

        if ($errors !== []) {
            return $this->redirectWithMessage('/contracts/create', implode(' ', $errors), 'error');
        }

        try {
            $repository = new ContractRepository();
            $id = $repository->create($data, $items);
            $this->saveDncpData($id, $dncpData);
            (new OperationalAuditService())->logDocumentAction(
                'contracts',
                $id,
                (string) $data['contract_number'],
                'created',
                null,
                (string) $data['status'],
                ['contract' => $data, 'items' => $items]
            );

            return $this->redirectWithMessage('/contracts/' . $id, 'Contrato creado correctamente.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/contracts/create', 'No se pudo crear el contrato: ' . $exception->getMessage(), 'error');
        }
    }

    public function edit(Request $request, string $id)
    {
        $contract = (new ContractRepository())->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $editableError = (new DocumentWorkflowService())->assertEditable('contracts', (int) $id);
        if ($editableError !== null) {
            return $this->redirectWithMessage('/contracts/' . $id, $editableError, 'error');
        }

            return $this->render('contracts/form', [
            'contract' => $contract,
            'action' => '/contracts/' . $id . '/update',
            'title' => 'Editar contrato',
            'clients' => (new ClientRepository())->search(),
            'billingContacts' => (new ClientBillingContactRepository())->allForSelection(),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $repository = new ContractRepository();
        $existing = $repository->findWithItems((int) $id);
        if (!$existing) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $editableError = (new DocumentWorkflowService())->assertEditable('contracts', (int) $id);
        if ($editableError !== null) {
            return $this->redirectWithMessage('/contracts/' . $id, $editableError, 'error');
        }

        [$data, $items, $dncpData, $errors] = $this->extractContractPayload($request);

        if ($errors !== []) {
            return $this->redirectWithMessage('/contracts/' . $id . '/edit', implode(' ', $errors), 'error');
        }

        try {
            $repository->update((int) $id, $data, $items);
            $this->saveDncpData((int) $id, $dncpData);
            $current = $repository->findWithItems((int) $id);
            if ($current) {
                (new ProvisionalContractSyncService())->syncOrdersForContract((int) $id, $existing, $current);
            }
            (new OperationalAuditService())->logDocumentAction(
                'contracts',
                (int) $id,
                (string) $data['contract_number'],
                'updated',
                (string) $existing['status'],
                (string) $data['status'],
                ['contract' => $data, 'items' => $items]
            );

            return $this->redirectWithMessage('/contracts/' . $id, 'Contrato actualizado correctamente.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/contracts/' . $id . '/edit', 'No se pudo actualizar el contrato: ' . $exception->getMessage(), 'error');
        }
    }

    public function delete(Request $request, string $id)
    {
        $repository = new ContractRepository();
        $contract = $repository->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $workflow = new DocumentWorkflowService();
        $editableError = $workflow->assertEditable('contracts', (int) $id);
        if ($editableError !== null) {
            return $this->redirectWithMessage('/contracts/' . $id, $editableError, 'error');
        }

        $lock = $workflow->destructiveLock('contracts', (int) $id);
        if ($lock['locked']) {
            return $this->redirectWithMessage('/contracts/' . $id, (string) $lock['reason'], 'error');
        }

        $repository->delete((int) $id);
        (new OperationalAuditService())->logDocumentAction(
            'contracts',
            (int) $id,
            (string) $contract['contract_number'],
            'deleted',
            (string) $contract['status'],
            null,
            $contract
        );

        return $this->redirectWithMessage('/contracts', 'Contrato eliminado.');
    }

    public function print(Request $request, string $id)
    {
        $contract = (new ContractRepository())->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        (new OperationalAuditService())->logDocumentAction(
            'contracts',
            (int) $id,
            (string) $contract['contract_number'],
            'printed',
            (string) $contract['status'],
            (string) $contract['status'],
            ['format' => 'html']
        );

        return Response::html(View::render('contracts/print', ['contract' => $contract], 'layouts/print'));
    }

    public function exportCsv(Request $request, string $id)
    {
        $contract = (new ContractRepository())->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $rows = [];

        foreach ($contract['items'] as $item) {
            $rows[] = [
                $contract['contract_number'],
                $item['product_name'],
                $item['unit_measure'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_item'],
            ];
        }

        $csv = (new ExportService())->toCsv($rows, ['Contrato', 'Producto', 'Unidad', 'Cantidad', 'Precio', 'Total']);

        (new OperationalAuditService())->logDocumentAction(
            'contracts',
            (int) $id,
            (string) $contract['contract_number'],
            'exported',
            (string) $contract['status'],
            (string) $contract['status'],
            ['format' => 'csv']
        );

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contrato-' . $contract['contract_number'] . '.csv"',
        ]);
    }

    public function createItemSpec(Request $request, string $id)
    {
        $contract = (new ContractRepository())->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $editableError = (new DocumentWorkflowService())->assertEditable('contracts', (int) $id);
        if ($editableError !== null) {
            return $this->redirectWithMessage('/contracts/' . $id, $editableError, 'error');
        }

        return $this->render('contracts/item_spec_form', [
            'contract' => $contract,
            'spec' => $this->emptyItemSpec(),
            'dependencies' => $this->contractDependencies($contract),
            'action' => '/contracts/' . $id . '/item-specs',
            'title' => 'Nuevo item tecnico',
        ]);
    }

    public function storeItemSpec(Request $request, string $id)
    {
        $contract = (new ContractRepository())->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $editableError = (new DocumentWorkflowService())->assertEditable('contracts', (int) $id);
        if ($editableError !== null) {
            return $this->redirectWithMessage('/contracts/' . $id, $editableError, 'error');
        }

        $data = $this->extractItemSpecPayload($request);

        try {
            $repository = new ContractItemSpecRepository();
            $specId = $repository->create((int) $id, $data);
            $spec = $repository->findForContract((int) $id, $specId) ?? $data;
            $this->auditItemSpec((int) $id, $contract, 'create_contract_item_spec', null, (string) ($spec['status'] ?? 'draft'), $spec);

            return $this->redirectWithMessage('/contracts/' . $id, 'Item tecnico creado correctamente.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/contracts/' . $id . '/item-specs/create', 'No se pudo crear el item tecnico: ' . $exception->getMessage(), 'error');
        }
    }

    public function editItemSpec(Request $request, string $id, string $specId)
    {
        $contract = (new ContractRepository())->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $editableError = (new DocumentWorkflowService())->assertEditable('contracts', (int) $id);
        if ($editableError !== null) {
            return $this->redirectWithMessage('/contracts/' . $id, $editableError, 'error');
        }

        $spec = (new ContractItemSpecRepository())->findForContract((int) $id, (int) $specId);
        if (!$spec) {
            return $this->redirectWithMessage('/contracts/' . $id, 'Item tecnico no encontrado para este contrato.', 'error');
        }

        if (($spec['status'] ?? '') === 'confirmed') {
            return $this->redirectWithMessage('/contracts/' . $id, 'No se puede editar libremente un item tecnico confirmado.', 'error');
        }

        return $this->render('contracts/item_spec_form', [
            'contract' => $contract,
            'spec' => $spec,
            'dependencies' => $this->contractDependencies($contract),
            'action' => '/contracts/' . $id . '/item-specs/' . $specId . '/update',
            'title' => 'Editar item tecnico',
        ]);
    }

    public function updateItemSpec(Request $request, string $id, string $specId)
    {
        $contract = (new ContractRepository())->findWithItems((int) $id);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $editableError = (new DocumentWorkflowService())->assertEditable('contracts', (int) $id);
        if ($editableError !== null) {
            return $this->redirectWithMessage('/contracts/' . $id, $editableError, 'error');
        }

        $repository = new ContractItemSpecRepository();
        $existing = $repository->findForContract((int) $id, (int) $specId);
        if (!$existing) {
            return $this->redirectWithMessage('/contracts/' . $id, 'Item tecnico no encontrado para este contrato.', 'error');
        }

        $data = $this->extractItemSpecPayload($request);

        try {
            $repository->update((int) $id, (int) $specId, $data);
            $spec = $repository->findForContract((int) $id, (int) $specId) ?? $data;
            $this->auditItemSpec((int) $id, $contract, 'update_contract_item_spec', (string) ($existing['status'] ?? 'draft'), (string) ($spec['status'] ?? 'draft'), $spec);

            return $this->redirectWithMessage('/contracts/' . $id, 'Item tecnico actualizado correctamente.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/contracts/' . $id . '/item-specs/' . $specId . '/edit', 'No se pudo actualizar el item tecnico: ' . $exception->getMessage(), 'error');
        }
    }

    public function confirmItemSpec(Request $request, string $id, string $specId)
    {
        return $this->transitionItemSpec((int) $id, (int) $specId, 'confirmed', 'confirm_contract_item_spec', 'Item tecnico confirmado.');
    }

    public function cancelItemSpec(Request $request, string $id, string $specId)
    {
        return $this->transitionItemSpec((int) $id, (int) $specId, 'cancelled', 'cancel_contract_item_spec', 'Item tecnico anulado.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: array<string, mixed>, 3: array<int, string>}
     */
    private function extractContractPayload(Request $request): array
    {
        $data = $request->only([
            'client_id',
            'date',
            'contract_number',
            'reference_number',
            'contract_type',
            'tax_id',
            'notes',
            'provisional_data',
        ]);
        $dncpData = $request->only([
            'dncp_tender_id',
            'dncp_contract_number',
            'dncp_customer_purchase_order_number',
            'dncp_public_entity',
            'dncp_requesting_dependency',
            'dncp_procurement_modality',
            'dncp_procurement_code',
            'dncp_contract_date',
            'dncp_valid_from',
            'dncp_valid_until',
            'dncp_currency',
            'dncp_fiscal_business_name',
            'dncp_fiscal_ruc',
            'dncp_billing_contact_id',
            'dncp_notes',
        ]);
        $data['status'] = 'draft';
        $data['is_provisional'] = $request->input('is_provisional') ? 1 : 0;

        $items = $this->collectLineItems($request->all(), [
            'product_name',
            'unit_measure',
            'quantity',
            'unit_price',
            'notes',
        ]);
        $data['total_amount'] = $this->sumItems($items);

        $errors = [];
        if (trim((string) $data['date']) === '') {
            $errors[] = 'La fecha del contrato es obligatoria.';
        }
        if (trim((string) $data['contract_number']) === '') {
            $errors[] = 'El número de contrato es obligatorio.';
        }
        if ($items === []) {
            $errors[] = 'Debe ingresar al menos un item.';
        }

        return [$data, $items, $dncpData, $errors];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContract(): array
    {
        return [
            'client_id' => '',
            'date' => date('Y-m-d'),
            'contract_number' => '',
            'reference_number' => '',
            'contract_type' => '',
            'tax_id' => '',
            'status' => 'draft',
            'notes' => '',
            'is_provisional' => 0,
            'provisional_data' => '',
            'dncp_data' => [
                'tender_id' => '',
                'contract_number' => '',
                'customer_purchase_order_number' => '',
                'public_entity' => '',
                'requesting_dependency' => '',
                'procurement_modality' => '',
                'procurement_code' => '',
                'contract_date' => '',
                'valid_from' => '',
                'valid_until' => '',
                'currency' => 'PYG',
                'fiscal_business_name' => '',
                'fiscal_ruc' => '',
                'billing_contact_id' => '',
                'notes' => '',
            ],
            'items' => [
                ['product_name' => '', 'unit_measure' => '', 'quantity' => '', 'unit_price' => '', 'total_item' => '', 'notes' => ''],
            ],
            'item_specs' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyItemSpec(): array
    {
        return [
            'item_code' => '',
            'product_category' => 'textil',
            'product_type' => '',
            'description' => '',
            'is_textile' => 1,
            'size' => '',
            'color' => '',
            'fabric' => '',
            'grammage' => '',
            'measurements' => '',
            'finishing' => '',
            'has_embroidery' => 0,
            'embroidery_details' => '',
            'has_screen_printing' => 0,
            'screen_printing_details' => '',
            'logo_position' => '',
            'quantity' => '',
            'unit' => 'unidad',
            'label' => '',
            'destination_dependency_id' => '',
            'technical_notes' => '',
            'attachment_path' => '',
            'status' => 'draft',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractItemSpecPayload(Request $request): array
    {
        $data = $request->only([
            'item_code',
            'product_category',
            'product_type',
            'description',
            'size',
            'color',
            'fabric',
            'grammage',
            'measurements',
            'finishing',
            'embroidery_details',
            'screen_printing_details',
            'logo_position',
            'quantity',
            'unit',
            'label',
            'destination_dependency_id',
            'technical_notes',
            'attachment_path',
        ]);
        $data['has_embroidery'] = $request->input('has_embroidery') ? 1 : 0;
        $data['has_screen_printing'] = $request->input('has_screen_printing') ? 1 : 0;

        return $data;
    }

    /**
     * @param array<string, mixed> $contract
     * @return array<int, array<string, mixed>>
     */
    private function contractDependencies(array $contract): array
    {
        return !empty($contract['client_id'])
            ? (new ClientDependencyRepository())->byClientId((int) $contract['client_id'])
            : [];
    }

    private function transitionItemSpec(int $contractId, int $specId, string $status, string $action, string $message)
    {
        $contract = (new ContractRepository())->findWithItems($contractId);

        if (!$contract) {
            return $this->redirectWithMessage('/contracts', 'Contrato no encontrado.', 'error');
        }

        $editableError = (new DocumentWorkflowService())->assertEditable('contracts', $contractId);
        if ($editableError !== null) {
            return $this->redirectWithMessage('/contracts/' . $contractId, $editableError, 'error');
        }

        $repository = new ContractItemSpecRepository();
        $existing = $repository->findForContract($contractId, $specId);
        if (!$existing) {
            return $this->redirectWithMessage('/contracts/' . $contractId, 'Item tecnico no encontrado para este contrato.', 'error');
        }

        try {
            if ($status === 'confirmed') {
                $repository->confirm($contractId, $specId);
            } else {
                $repository->cancel($contractId, $specId);
            }
            $spec = $repository->findForContract($contractId, $specId) ?? $existing;
            $this->auditItemSpec($contractId, $contract, $action, (string) ($existing['status'] ?? 'draft'), $status, $spec);

            return $this->redirectWithMessage('/contracts/' . $contractId, $message);
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/contracts/' . $contractId, 'No se pudo actualizar el estado del item tecnico: ' . $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $contract
     * @param array<string, mixed> $spec
     */
    private function auditItemSpec(int $contractId, array $contract, string $action, ?string $previousState, ?string $newState, array $spec): void
    {
        $summary = $this->itemSpecAuditSummary($contractId, $spec);
        (new \App\Repositories\AuditLogRepository())->log(
            'contract_item_specs',
            (int) ($spec['id'] ?? $contractId),
            $action,
            $summary,
            [
                'document_type' => 'contracts',
                'document_id' => $contractId,
                'document_number' => (string) ($contract['contract_number'] ?? ''),
                'previous_state' => $previousState,
                'new_state' => $newState,
                'payload_summary' => $summary,
            ]
        );
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    private function itemSpecAuditSummary(int $contractId, array $spec): array
    {
        return [
            'contract_id' => $contractId,
            'item_code' => (string) ($spec['item_code'] ?? ''),
            'product_type' => (string) ($spec['product_type'] ?? ''),
            'quantity' => (float) ($spec['quantity'] ?? 0),
            'unit' => (string) ($spec['unit'] ?? ''),
            'is_textile' => (int) ($spec['is_textile'] ?? 0),
            'destination_dependency_id' => $spec['destination_dependency_id'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $dncpData
     */
    private function saveDncpData(int $contractId, array $dncpData): void
    {
        $repository = new ContractDncpDataRepository();
        if (!$repository->hasPayload($dncpData)) {
            return;
        }

        $repository->upsert($contractId, $dncpData);
        (new \App\Repositories\AuditLogRepository())->log('contracts', $contractId, 'update_contract_dncp_data', $dncpData, [
            'document_type' => 'contracts',
            'document_id' => $contractId,
        ]);
    }
}
