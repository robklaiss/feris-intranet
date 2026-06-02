<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\PurchaseRequisitionRepository;
use App\Repositories\RawMaterialInventoryRepository;
use App\Repositories\StockCheckRepository;
use App\Repositories\SupplierPurchaseOrderRepository;
use App\Repositories\SupplierQuoteRepository;
use App\Repositories\SupplierRepository;
use App\Support\AccessControl;
use App\Support\Auth;
use App\Support\Database;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = Database::connection();
foreach ([
    'suppliers',
    'purchase_requisitions',
    'purchase_requisition_items',
    'supplier_quote_requests',
    'supplier_quotes',
    'supplier_quote_items',
    'supplier_purchase_orders',
    'supplier_purchase_order_items',
] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 011 debe crear la tabla ' . $table . '.');
}

$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$customerOrderRepository = new CustomerPurchaseOrderRepository();
$productionRepository = new ProductionOrderRepository();
$inventoryRepository = new RawMaterialInventoryRepository();
$stockCheckRepository = new StockCheckRepository();
$supplierRepository = new SupplierRepository();
$requisitionRepository = new PurchaseRequisitionRepository();
$quoteRepository = new SupplierQuoteRepository();
$supplierPoRepository = new SupplierPurchaseOrderRepository();

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$contractId = null;
$customerOrderId = null;
$productionOrderId = null;
$shortMaterialId = null;
$enoughMaterialId = null;
$supplierIds = [];
$requisitionId = null;
$quoteIds = [];
$supplierPoId = null;

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para compras.');

    $supplierIds[] = $supplierRepository->create([
        'name' => 'Proveedor A ' . $suffix,
        'ruc' => '',
        'contact_name' => 'Compras A',
        'phone' => '0981',
        'email' => 'a@example.test',
        'payment_terms' => 'Contado',
        'delivery_terms' => 'Entrega en planta',
        'status' => 'active',
    ]);
    $supplierIds[] = $supplierRepository->create([
        'name' => 'Proveedor B ' . $suffix,
        'ruc' => '800' . random_int(100000, 999999) . '-1',
        'payment_terms' => '15 dias',
        'delivery_terms' => 'Retiro',
        'status' => 'active',
    ]);
    $supplierIds[] = $supplierRepository->create([
        'name' => 'Proveedor C ' . $suffix,
        'ruc' => '801' . random_int(100000, 999999) . '-2',
        'payment_terms' => '30 dias',
        'delivery_terms' => 'Entrega parcial',
        'status' => 'active',
    ]);
    $assert($supplierRepository->find($supplierIds[0]) !== null, 'Se debe crear proveedor.');
    $assert($supplierRepository->search(['q' => 'Proveedor A ' . $suffix]) !== [], 'Se debe buscar proveedor por nombre.');

    $clientId = $clientRepository->create([
        'name' => 'Cliente Compras ' . $suffix,
        'tax_id' => '805' . random_int(100000, 999999) . '-1',
        'addresses' => 'Central',
        'contacts' => 'Produccion',
        'status' => 'active',
    ]);

    $contractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-COMP-' . $suffix,
            'reference_number' => 'REF-COMP-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80412345-6',
            'status' => 'confirmed',
            'notes' => '',
            'total_amount' => 120000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [
            [
                'product_name' => 'Remera compras',
                'unit_measure' => 'unidad',
                'quantity' => 12,
                'unit_price' => 10000,
                'total_item' => 120000,
                'notes' => '',
            ],
        ]
    );

    $specId = $specRepository->create($contractId, [
        'item_code' => 'CMP-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera algodón compras',
        'quantity' => 12,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($contractId, $specId);

    $customerOrderId = $customerOrderRepository->create(
        [
            'contract_id' => $contractId,
            'po_number' => 'OC-COMP-' . $suffix,
            'po_date' => '2026-06-01',
            'received_date' => '2026-06-01',
            'dependency_id' => '',
            'billing_contact_id' => '',
            'notes' => '',
            'attachment_path' => '',
        ],
        [['contract_item_spec_id' => $specId, 'quantity' => 6]]
    );
    $customerOrderRepository->confirm($customerOrderId);

    $customerItemId = (int) $pdo->query('SELECT id FROM customer_purchase_order_items WHERE customer_purchase_order_id = ' . $customerOrderId . ' LIMIT 1')->fetchColumn();
    $productionOrderId = $productionRepository->create(
        [
            'customer_purchase_order_id' => $customerOrderId,
            'production_number' => 'OP-COMP-' . $suffix,
            'planned_start_date' => '',
            'planned_end_date' => '',
            'notes' => '',
        ],
        [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => 6]]
    );
    $productionRepository->confirm($productionOrderId);
    $productionItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();

    $shortMaterialId = $inventoryRepository->create([
        'internal_code' => 'MAT-COMP-SHORT-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela compras insuficiente',
        'unit' => 'unidad',
        'quantity_available' => 2,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_item_code' => 'CMP-' . $suffix,
        'status' => 'active',
    ]);
    $shortCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-COMP-SHORT-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $shortMaterialId]]
    );

    $requisitionId = $requisitionRepository->createFromStockCheck($shortCheckId, ['requisition_number' => 'REQ-COMP-' . $suffix]);
    $requisition = $requisitionRepository->findWithDetails($requisitionId);
    $assert((string) ($requisition['status'] ?? '') === 'requested', 'Debe crear requisición solicitada desde faltantes.');
    $assert(count($requisition['items']) === 1, 'La requisición debe incluir solo ítems con faltante.');
    $assert((float) $requisition['items'][0]['missing_quantity'] === 4.0, 'La requisición debe copiar el faltante calculado.');
    $assert((float) $requisition['items'][0]['requested_quantity'] === 4.0, 'La cantidad solicitada debe ser positiva y copiar el faltante.');

    $invalidQuantityFailed = false;
    $secondShortCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-COMP-BAD-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $shortMaterialId]]
    );
    try {
        $requisitionRepository->createFromStockCheck($secondShortCheckId, [
            'requested_quantity' => [$pdo->query('SELECT id FROM stock_check_items WHERE stock_check_id = ' . $secondShortCheckId . ' LIMIT 1')->fetchColumn() => 0],
        ]);
    } catch (InvalidArgumentException) {
        $invalidQuantityFailed = true;
    }
    $assert($invalidQuantityFailed, 'No debe permitir requested_quantity <= 0.');

    $enoughMaterialId = $inventoryRepository->create([
        'internal_code' => 'MAT-COMP-OK-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela compras suficiente',
        'unit' => 'unidad',
        'quantity_available' => 8,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_product_type' => 'Remera',
        'status' => 'active',
    ]);
    $okCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-COMP-OK-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $enoughMaterialId]]
    );
    $sufficientFailed = false;
    try {
        $requisitionRepository->createFromStockCheck($okCheckId);
    } catch (InvalidArgumentException) {
        $sufficientFailed = true;
    }
    $assert($sufficientFailed, 'No debe crear requisición desde stock_check sin faltantes.');

    $requisitionRepository->addSupplierQuoteRequests($requisitionId, [$supplierIds[0], $supplierIds[1]]);
    $assert($requisitionRepository->quoteRequestCount($requisitionId) === 2, 'Debe asociar proveedores a la requisición.');

    $itemId = (int) $requisition['items'][0]['id'];
    $quoteIds[] = $quoteRepository->registerReceivedQuote(
        $requisitionId,
        $supplierIds[0],
        [
            'quote_number' => 'PRES-A-' . $suffix,
            'quote_date' => '2026-06-01',
            'currency' => 'PYG',
            'tax_amount' => 0,
            'delivery_days' => 5,
            'payment_terms' => 'Contado',
        ],
        [[
            'purchase_requisition_item_id' => $itemId,
            'description' => 'Tela cotizada A',
            'unit' => 'unidad',
            'quantity' => 4,
            'unit_price' => 10000,
        ]]
    );
    $receivedQuote = $quoteRepository->findWithItems($quoteIds[0]);
    $assert((string) ($receivedQuote['status'] ?? '') === 'received', 'Se debe registrar presupuesto recibido.');
    $assert(count($receivedQuote['items']) === 1, 'El presupuesto recibido debe tener ítems.');

    $approveWithTwoFailed = false;
    try {
        $quoteRepository->approve($quoteIds[0]);
    } catch (InvalidArgumentException) {
        $approveWithTwoFailed = true;
    }
    $assert($approveWithTwoFailed, 'No debe aprobar con menos de 3 proveedores solicitados sin override.');

    $requisitionRepository->addSupplierQuoteRequests($requisitionId, [$supplierIds[2]]);
    $assert($requisitionRepository->quoteRequestCount($requisitionId) === 3, 'Debe permitir asociar 3 proveedores.');

    $emptyQuoteId = null;
    $pdo->prepare(
        'INSERT INTO supplier_quotes (
            purchase_requisition_id, supplier_id, quote_number, quote_date, status, currency, subtotal, tax_amount, total_amount
        ) VALUES (
            :purchase_requisition_id, :supplier_id, :quote_number, "2026-06-01", "received", "PYG", 0, 0, 0
        )'
    )->execute([
        'purchase_requisition_id' => $requisitionId,
        'supplier_id' => $supplierIds[2],
        'quote_number' => 'PRES-EMPTY-' . $suffix,
    ]);
    $emptyQuoteId = (int) $pdo->lastInsertId();
    $emptyApproveFailed = false;
    try {
        $quoteRepository->approve($emptyQuoteId);
    } catch (InvalidArgumentException) {
        $emptyApproveFailed = true;
    }
    $assert($emptyApproveFailed, 'No debe aprobar presupuesto sin ítems.');

    $quoteIds[] = $quoteRepository->registerReceivedQuote(
        $requisitionId,
        $supplierIds[1],
        [
            'quote_number' => 'PRES-B-' . $suffix,
            'quote_date' => '2026-06-01',
            'currency' => 'PYG',
            'tax_amount' => 0,
            'delivery_days' => 7,
            'payment_terms' => '15 dias',
        ],
        [[
            'purchase_requisition_item_id' => $itemId,
            'description' => 'Tela cotizada B',
            'unit' => 'unidad',
            'quantity' => 4,
            'unit_price' => 12000,
        ]]
    );

    $nonApprovedPoFailed = false;
    try {
        $supplierPoRepository->createFromApprovedQuote($quoteIds[1]);
    } catch (InvalidArgumentException) {
        $nonApprovedPoFailed = true;
    }
    $assert($nonApprovedPoFailed, 'No debe generar OC proveedor desde presupuesto no aprobado.');

    $quoteRepository->approve($quoteIds[0]);
    $approvedQuote = $quoteRepository->find($quoteIds[0]);
    $rejectedQuote = $quoteRepository->find($quoteIds[1]);
    $assert((string) ($approvedQuote['status'] ?? '') === 'approved', 'Debe aprobar el presupuesto seleccionado.');
    $assert((string) ($rejectedQuote['status'] ?? '') === 'rejected', 'Solo un presupuesto debe quedar aprobado por requisición.');

    $supplierPoId = $supplierPoRepository->createFromApprovedQuote($quoteIds[0], [
        'supplier_po_number' => 'OC-PROV-' . $suffix,
        'iso_form_number' => 'ISO-COM-' . $suffix,
        'purchase_reason' => 'Compra por faltante de stock.',
        'quality_requirements' => 'Verificar calidad al recibir en Fase 7.',
    ]);
    $supplierPo = $supplierPoRepository->findWithItems($supplierPoId);
    $assert((string) ($supplierPo['status'] ?? '') === 'draft', 'La OC proveedor debe crearse en borrador.');
    $assert((float) ($supplierPo['total_amount'] ?? 0) === (float) ($approvedQuote['total_amount'] ?? 0), 'La OC proveedor debe copiar totales.');
    $assert(count($supplierPo['items']) === count($receivedQuote['items']), 'La OC proveedor debe copiar ítems.');

    $supplierPoRepository->confirm($supplierPoId);
    $assert((string) ($supplierPoRepository->find($supplierPoId)['status'] ?? '') === 'confirmed', 'Debe confirmar OC proveedor.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['POST', '/suppliers'],
        ['GET', '/suppliers/' . $supplierIds[0] . '/edit'],
        ['POST', '/stock-checks/' . $shortCheckId . '/purchase-requisitions'],
        ['POST', '/purchase-requisitions/' . $requisitionId . '/suppliers'],
        ['POST', '/purchase-requisitions/' . $requisitionId . '/suppliers/' . $supplierIds[2] . '/quotes'],
        ['POST', '/supplier-quotes/' . $quoteIds[0] . '/approve'],
        ['POST', '/supplier-purchase-orders/from-quote/' . $quoteIds[0]],
        ['POST', '/supplier-purchase-orders/' . $supplierPoId . '/confirm'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/purchase-requisitions/' . $requisitionId)), 'Consulta debe poder ver pedidos de presupuesto.');

    foreach ([
        'create_supplier',
        'create_purchase_requisition',
        'add_supplier_quote_request',
        'register_supplier_quote',
        'approve_supplier_quote',
        'create_supplier_purchase_order',
        'confirm_supplier_purchase_order',
    ] as $action) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = '{$action}'")->fetchColumn();
        $assert($count > 0, 'audit_log debe registrar ' . $action . '.');
    }
} finally {
    Auth::logout();
    if (is_int($supplierPoId)) {
        $pdo->exec('DELETE FROM supplier_purchase_orders WHERE id = ' . $supplierPoId);
    }
    if (is_int($requisitionId)) {
        $pdo->exec('DELETE FROM purchase_requisitions WHERE id = ' . $requisitionId);
    }
    if (isset($secondShortCheckId)) {
        $pdo->exec('DELETE FROM purchase_requisitions WHERE stock_check_id = ' . (int) $secondShortCheckId);
    }
    if (is_int($productionOrderId)) {
        $pdo->exec('DELETE FROM production_orders WHERE id = ' . $productionOrderId);
    }
    if (is_int($customerOrderId)) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . $customerOrderId);
    }
    foreach ([$shortMaterialId, $enoughMaterialId] as $materialId) {
        if (is_int($materialId)) {
            $pdo->exec('DELETE FROM raw_material_inventory WHERE id = ' . $materialId);
        }
    }
    foreach ($supplierIds as $supplierId) {
        $pdo->exec('DELETE FROM suppliers WHERE id = ' . (int) $supplierId);
    }
    if (is_int($contractId)) {
        $contractRepository->delete($contractId);
    }
    if (is_int($clientId)) {
        $clientRepository->delete($clientId);
    }
}

return true;
