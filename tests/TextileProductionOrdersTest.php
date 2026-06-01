<?php

declare(strict_types=1);

use App\Controllers\CustomerPurchaseOrderController;
use App\Controllers\ProductionOrderController;
use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\ProductionOrderRepository;
use App\Support\AccessControl;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Request;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = Database::connection();
foreach (['customer_purchase_orders', 'customer_purchase_order_items', 'production_orders', 'production_order_items'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 009 debe crear la tabla ' . $table . '.');
}

$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$customerOrderRepository = new CustomerPurchaseOrderRepository();
$productionRepository = new ProductionOrderRepository();

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$confirmedContractId = null;
$draftContractId = null;
$customerOrderId = null;
$draftCustomerOrderId = null;
$productionOrderId = null;

try {
    $clientId = $clientRepository->create([
        'name' => 'Cliente Produccion ' . $suffix,
        'tax_id' => '803' . random_int(100000, 999999) . '-1',
        'addresses' => 'Casa central',
        'contacts' => 'Compras',
        'status' => 'active',
    ]);

    $confirmedContractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-PROD-' . $suffix,
            'reference_number' => 'REF-PROD-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80312345-6',
            'status' => 'confirmed',
            'notes' => 'Contrato prueba produccion',
            'total_amount' => 100000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [
            [
                'product_name' => 'Camisa institucional',
                'unit_measure' => 'unidad',
                'quantity' => 10,
                'unit_price' => 10000,
                'total_item' => 100000,
                'notes' => '',
            ],
        ]
    );

    $draftContractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-DRAFT-PROD-' . $suffix,
            'reference_number' => 'REF-DRAFT-PROD-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80312345-6',
            'status' => 'draft',
            'notes' => '',
            'total_amount' => 10000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [
            [
                'product_name' => 'Borrador',
                'unit_measure' => 'unidad',
                'quantity' => 1,
                'unit_price' => 10000,
                'total_item' => 10000,
                'notes' => '',
            ],
        ]
    );

    $confirmedSpecId = $specRepository->create($confirmedContractId, [
        'item_code' => 'TP-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Camisa',
        'description' => 'Camisa manga larga',
        'size' => 'L',
        'color' => 'Blanco',
        'has_embroidery' => 1,
        'has_screen_printing' => 0,
        'quantity' => 10,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($confirmedContractId, $confirmedSpecId);

    $draftSpecId = $specRepository->create($confirmedContractId, [
        'item_code' => 'TP-DRAFT-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Camisa',
        'quantity' => 2,
        'unit' => 'unidad',
    ]);

    $cancelledSpecId = $specRepository->create($confirmedContractId, [
        'item_code' => 'TP-CANCEL-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Camisa',
        'quantity' => 2,
        'unit' => 'unidad',
    ]);
    $specRepository->cancel($confirmedContractId, $cancelledSpecId);

    $fromDraftFailed = false;
    try {
        $customerOrderRepository->create(
            [
                'contract_id' => $draftContractId,
                'po_number' => 'OC-DRAFT-' . $suffix,
                'po_date' => '2026-06-01',
                'received_date' => '2026-06-01',
                'dependency_id' => '',
                'billing_contact_id' => '',
                'notes' => '',
                'attachment_path' => '',
            ],
            [['contract_item_spec_id' => $confirmedSpecId, 'quantity' => 1]]
        );
    } catch (InvalidArgumentException) {
        $fromDraftFailed = true;
    }
    $assert($fromDraftFailed, 'No debe permitir crear OC cliente desde contrato draft.');

    foreach ([$draftSpecId, $cancelledSpecId] as $invalidSpecId) {
        $invalidSpecFailed = false;
        try {
            $customerOrderRepository->create(
                [
                    'contract_id' => $confirmedContractId,
                    'po_number' => 'OC-BAD-' . $invalidSpecId . '-' . $suffix,
                    'po_date' => '2026-06-01',
                    'received_date' => '2026-06-01',
                    'dependency_id' => '',
                    'billing_contact_id' => '',
                    'notes' => '',
                    'attachment_path' => '',
                ],
                [['contract_item_spec_id' => $invalidSpecId, 'quantity' => 1]]
            );
        } catch (InvalidArgumentException) {
            $invalidSpecFailed = true;
        }
        $assert($invalidSpecFailed, 'No debe permitir incluir items tecnicos draft/cancelled.');
    }

    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para auditar.');

    $customerController = new CustomerPurchaseOrderController();
    $poNumber = 'OC-PROD-' . $suffix;
    $customerController->store(new Request([], [
        'contract_id' => (string) $confirmedContractId,
        'po_number' => $poNumber,
        'po_date' => '2026-06-01',
        'received_date' => '2026-06-01',
        'dependency_id' => '',
        'billing_contact_id' => '',
        'notes' => 'OC test',
        'attachment_path' => '',
        'contract_item_spec_id' => [(string) $confirmedSpecId],
        'quantity' => ['6'],
        'notes_item' => ['Lote parcial'],
    ], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/customer-purchase-orders']));

    $customerOrderId = (int) ($pdo->query("SELECT id FROM customer_purchase_orders WHERE po_number = '{$poNumber}'")->fetchColumn() ?: 0);
    $assert($customerOrderId > 0, 'Debe crear OC cliente desde contrato confirmed con item tecnico confirmed.');

    $tooMuchFailed = false;
    try {
        $customerOrderRepository->create(
            [
                'contract_id' => $confirmedContractId,
                'po_number' => 'OC-OVER-' . $suffix,
                'po_date' => '2026-06-01',
                'received_date' => '2026-06-01',
                'dependency_id' => '',
                'billing_contact_id' => '',
                'notes' => '',
                'attachment_path' => '',
            ],
            [['contract_item_spec_id' => $confirmedSpecId, 'quantity' => 5]]
        );
    } catch (InvalidArgumentException) {
        $tooMuchFailed = true;
    }
    $assert($tooMuchFailed, 'No debe permitir cantidad mayor al saldo contratado.');

    $customerController->confirm(new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/customer-purchase-orders/' . $customerOrderId . '/confirm']), (string) $customerOrderId);
    $confirmedCustomerOrder = $customerOrderRepository->find($customerOrderId);
    $assert((string) ($confirmedCustomerOrder['status'] ?? '') === 'confirmed', 'Debe confirmar OC cliente correctamente.');

    $draftCustomerOrderId = $customerOrderRepository->create(
        [
            'contract_id' => $confirmedContractId,
            'po_number' => 'OC-DRAFT2-' . $suffix,
            'po_date' => '2026-06-01',
            'received_date' => '2026-06-01',
            'dependency_id' => '',
            'billing_contact_id' => '',
            'notes' => '',
            'attachment_path' => '',
        ],
        [['contract_item_spec_id' => $confirmedSpecId, 'quantity' => 4]]
    );

    $productionFromDraftFailed = false;
    try {
        $productionRepository->create(
            [
                'customer_purchase_order_id' => $draftCustomerOrderId,
                'production_number' => 'OP-DRAFT-' . $suffix,
                'planned_start_date' => '',
                'planned_end_date' => '',
                'notes' => '',
            ],
            [['customer_purchase_order_item_id' => (int) $pdo->query("SELECT id FROM customer_purchase_order_items WHERE customer_purchase_order_id = {$draftCustomerOrderId} LIMIT 1")->fetchColumn(), 'quantity' => 1]]
        );
    } catch (InvalidArgumentException) {
        $productionFromDraftFailed = true;
    }
    $assert($productionFromDraftFailed, 'No debe permitir crear produccion desde OC cliente draft.');

    $customerItemId = (int) $pdo->query("SELECT id FROM customer_purchase_order_items WHERE customer_purchase_order_id = {$customerOrderId} LIMIT 1")->fetchColumn();
    $productionController = new ProductionOrderController();
    $productionNumber = 'OP-PROD-' . $suffix;
    $productionController->store(new Request([], [
        'customer_purchase_order_id' => (string) $customerOrderId,
        'production_number' => $productionNumber,
        'planned_start_date' => '2026-06-02',
        'planned_end_date' => '2026-06-05',
        'notes' => 'Produccion test',
        'customer_purchase_order_item_id' => [(string) $customerItemId],
        'quantity' => ['4'],
        'notes_item' => ['Primera tanda'],
    ], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/production-orders']));

    $productionOrderId = (int) ($pdo->query("SELECT id FROM production_orders WHERE production_number = '{$productionNumber}'")->fetchColumn() ?: 0);
    $assert($productionOrderId > 0, 'Debe crear produccion desde OC cliente confirmed.');

    $duplicateFailed = false;
    try {
        $productionRepository->create(
            [
                'customer_purchase_order_id' => $customerOrderId,
                'production_number' => 'OP-OVER-' . $suffix,
                'planned_start_date' => '',
                'planned_end_date' => '',
                'notes' => '',
            ],
            [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => 3]]
        );
    } catch (InvalidArgumentException) {
        $duplicateFailed = true;
    }
    $assert($duplicateFailed, 'No debe permitir duplicar produccion por encima del saldo de la OC.');

    $productionController->confirm(new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/production-orders/' . $productionOrderId . '/confirm']), (string) $productionOrderId);
    $confirmedProductionOrder = $productionRepository->find($productionOrderId);
    $assert((string) ($confirmedProductionOrder['status'] ?? '') === 'confirmed', 'Debe confirmar orden de produccion correctamente.');
    $assert((string) ($confirmedProductionOrder['production_stage'] ?? '') === 'ready_for_stock_check', 'La OP confirmada queda lista para futura verificacion de stock.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['POST', '/customer-purchase-orders'],
        ['GET', '/customer-purchase-orders/create'],
        ['POST', '/customer-purchase-orders/' . $customerOrderId . '/confirm'],
        ['POST', '/production-orders'],
        ['GET', '/production-orders/create'],
        ['POST', '/production-orders/' . $productionOrderId . '/cancel'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe poder operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/customer-purchase-orders/' . $customerOrderId)), 'Usuario consulta debe poder ver OC cliente.');
    Auth::logout();

    $auditCount = $pdo->query(
        "SELECT COUNT(*) FROM audit_log
         WHERE action IN (
            'create_customer_purchase_order',
            'confirm_customer_purchase_order',
            'create_production_order',
            'confirm_production_order'
         )"
    )->fetchColumn();
    $assert((int) $auditCount >= 4, 'audit_log debe registrar acciones principales de OC cliente y produccion.');
} finally {
    if (is_int($productionOrderId)) {
        $pdo->exec('DELETE FROM production_orders WHERE id = ' . $productionOrderId);
    }
    if (is_int($draftCustomerOrderId)) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . $draftCustomerOrderId);
    }
    if (is_int($customerOrderId)) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . $customerOrderId);
    }
    if (is_int($confirmedContractId)) {
        $contractRepository->delete($confirmedContractId);
    }
    if (is_int($draftContractId)) {
        $contractRepository->delete($draftContractId);
    }
    if (is_int($clientId)) {
        $clientRepository->delete($clientId);
    }
    Auth::logout();
}

return true;
