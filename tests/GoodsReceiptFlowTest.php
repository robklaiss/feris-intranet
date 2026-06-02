<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\GoodsReceiptRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\PurchaseRequisitionRepository;
use App\Repositories\RawMaterialInventoryRepository;
use App\Repositories\StockCheckRepository;
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
foreach (['goods_receipts', 'goods_receipt_items'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 012 debe crear la tabla ' . $table . '.');
}
$sourceColumn = $pdo->query("PRAGMA table_info(raw_material_inventory)")->fetchAll() ?: [];
$assert(in_array('source_goods_receipt_item_id', array_column($sourceColumn, 'name'), true), 'La migracion 012 debe agregar source_goods_receipt_item_id.');

$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$customerOrderRepository = new CustomerPurchaseOrderRepository();
$productionRepository = new ProductionOrderRepository();
$inventoryRepository = new RawMaterialInventoryRepository();
$stockCheckRepository = new StockCheckRepository();
$requisitionRepository = new PurchaseRequisitionRepository();
$supplierRepository = new SupplierRepository();
$goodsReceiptRepository = new GoodsReceiptRepository();

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$contractId = null;
$customerOrderId = null;
$productionOrderId = null;
$shortMaterialId = null;
$supplierIds = [];
$stockCheckId = null;
$requisitionId = null;
$supplierQuoteIds = [];
$supplierPoIds = [];
$receiptIds = [];
$inventoryIds = [];

$createSupplierPo = static function (string $status, string $suffixPart) use (&$supplierIds, &$supplierQuoteIds, &$supplierPoIds, &$requisitionId, $pdo, $supplierRepository): int {
    $supplierId = $supplierRepository->create([
        'name' => 'Proveedor Recepcion ' . $suffixPart,
        'ruc' => '809' . random_int(100000, 999999) . '-1',
        'payment_terms' => 'Contado',
        'delivery_terms' => 'Entrega en planta',
        'status' => 'active',
    ]);
    $supplierIds[] = $supplierId;

    $requisitionItemId = (int) $pdo->query('SELECT id FROM purchase_requisition_items WHERE purchase_requisition_id = ' . (int) $requisitionId . ' LIMIT 1')->fetchColumn();
    $pdo->prepare(
        'INSERT INTO supplier_quotes (
            purchase_requisition_id, supplier_id, quote_number, quote_date, status, currency, subtotal, tax_amount, total_amount, delivery_days
        ) VALUES (
            :purchase_requisition_id, :supplier_id, :quote_number, "2026-06-01", "approved", "PYG", 40000, 0, 40000, 3
        )'
    )->execute([
        'purchase_requisition_id' => $requisitionId,
        'supplier_id' => $supplierId,
        'quote_number' => 'PRES-REC-' . $suffixPart,
    ]);
    $quoteId = (int) $pdo->lastInsertId();
    $supplierQuoteIds[] = $quoteId;

    $pdo->prepare(
        'INSERT INTO supplier_quote_items (
            supplier_quote_id, purchase_requisition_item_id, description, unit, quantity, unit_price, total_price
        ) VALUES (
            :supplier_quote_id, :purchase_requisition_item_id, "Tela recepción", "metro", 4, 10000, 40000
        )'
    )->execute(['supplier_quote_id' => $quoteId, 'purchase_requisition_item_id' => $requisitionItemId]);

    $pdo->prepare(
        'INSERT INTO supplier_purchase_orders (
            supplier_quote_id, purchase_requisition_id, supplier_id, supplier_po_number, status, order_date,
            currency, subtotal, tax_amount, total_amount, product_specifications, quality_requirements
        ) VALUES (
            :supplier_quote_id, :purchase_requisition_id, :supplier_id, :supplier_po_number, :status, "2026-06-01",
            "PYG", 40000, 0, 40000, "Tela recepción", "Controlar recepción"
        )'
    )->execute([
        'supplier_quote_id' => $quoteId,
        'purchase_requisition_id' => $requisitionId,
        'supplier_id' => $supplierId,
        'supplier_po_number' => 'OC-REC-' . $suffixPart,
        'status' => $status,
    ]);
    $supplierPoId = (int) $pdo->lastInsertId();
    $supplierPoIds[] = $supplierPoId;

    $pdo->prepare(
        'INSERT INTO supplier_purchase_order_items (
            supplier_purchase_order_id, purchase_requisition_item_id, description, unit, quantity, unit_price, total_price
        ) VALUES (
            :supplier_purchase_order_id, :purchase_requisition_item_id, "Tela recepción", "metro", 4, 10000, 40000
        )'
    )->execute(['supplier_purchase_order_id' => $supplierPoId, 'purchase_requisition_item_id' => $requisitionItemId]);

    return $supplierPoId;
};

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para recepciones.');

    $clientId = $clientRepository->create([
        'name' => 'Cliente Recepcion ' . $suffix,
        'tax_id' => '806' . random_int(100000, 999999) . '-1',
        'addresses' => 'Central',
        'contacts' => 'Produccion',
        'status' => 'active',
    ]);
    $contractId = $contractRepository->create([
        'client_id' => $clientId,
        'date' => '2026-06-01',
        'contract_number' => 'CT-REC-' . $suffix,
        'reference_number' => 'REF-REC-' . $suffix,
        'contract_type' => 'Licitacion',
        'tax_id' => '80412345-6',
        'status' => 'confirmed',
        'notes' => '',
        'total_amount' => 120000,
        'is_provisional' => 0,
        'provisional_data' => null,
    ], [[
        'product_name' => 'Remera recepción',
        'unit_measure' => 'unidad',
        'quantity' => 12,
        'unit_price' => 10000,
        'total_item' => 120000,
        'notes' => '',
    ]]);
    $specId = $specRepository->create($contractId, [
        'item_code' => 'REC-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera algodón recepción',
        'quantity' => 12,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($contractId, $specId);
    $customerOrderId = $customerOrderRepository->create([
        'contract_id' => $contractId,
        'po_number' => 'OC-CLI-REC-' . $suffix,
        'po_date' => '2026-06-01',
        'received_date' => '2026-06-01',
        'dependency_id' => '',
        'billing_contact_id' => '',
        'notes' => '',
        'attachment_path' => '',
    ], [['contract_item_spec_id' => $specId, 'quantity' => 6]]);
    $customerOrderRepository->confirm($customerOrderId);
    $customerItemId = (int) $pdo->query('SELECT id FROM customer_purchase_order_items WHERE customer_purchase_order_id = ' . $customerOrderId . ' LIMIT 1')->fetchColumn();
    $productionOrderId = $productionRepository->create([
        'customer_purchase_order_id' => $customerOrderId,
        'production_number' => 'OP-REC-' . $suffix,
        'planned_start_date' => '',
        'planned_end_date' => '',
        'notes' => '',
    ], [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => 6]]);
    $productionRepository->confirm($productionOrderId);
    $productionItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();
    $shortMaterialId = $inventoryRepository->create([
        'internal_code' => 'MAT-REC-SHORT-' . $suffix,
        'material_type' => 'Tela',
        'description' => 'Tela insuficiente recepción',
        'unit' => 'metro',
        'quantity_available' => 2,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'status' => 'active',
    ]);
    $stockCheckId = $stockCheckRepository->createForProductionOrder($productionOrderId, ['check_number' => 'CHK-REC-' . $suffix], [[
        'production_order_item_id' => $productionItemId,
        'raw_material_inventory_id' => $shortMaterialId,
    ]]);
    $requisitionId = $requisitionRepository->createFromStockCheck($stockCheckId, ['requisition_number' => 'REQ-REC-' . $suffix]);

    $confirmedPoId = $createSupplierPo('confirmed', $suffix . '-CONF');
    $sentPoId = $createSupplierPo('sent', $suffix . '-SENT');
    $invalidPoId = $createSupplierPo('draft', $suffix . '-BAD');

    $poItemId = (int) $pdo->query('SELECT id FROM supplier_purchase_order_items WHERE supplier_purchase_order_id = ' . $confirmedPoId . ' LIMIT 1')->fetchColumn();
    $validItem = static fn (string $code, float $received, float $accepted, float $rejected = 0.0): array => [[
        'supplier_purchase_order_item_id' => $poItemId,
        'received_quantity' => $received,
        'accepted_quantity' => $accepted,
        'rejected_quantity' => $rejected,
        'internal_code' => $code,
        'material_type' => 'Tela',
        'lot_number' => 'LOTE-' . $code,
        'location' => 'Depósito A',
        'cost' => 10000,
        'quality_status' => 'pending',
    ]];

    foreach (['draft', 'cancelled', 'closed'] as $status) {
        $pdo->exec("UPDATE supplier_purchase_orders SET status = '{$status}' WHERE id = " . $invalidPoId);
        $operationFailed = false;
        try {
            $goodsReceiptRepository->createDraftFromSupplierPurchaseOrder($invalidPoId, ['receipt_number' => 'REC-BAD-' . $status . '-' . $suffix], [[
                'supplier_purchase_order_item_id' => (int) $pdo->query('SELECT id FROM supplier_purchase_order_items WHERE supplier_purchase_order_id = ' . $invalidPoId . ' LIMIT 1')->fetchColumn(),
                'received_quantity' => 1,
                'accepted_quantity' => 1,
                'rejected_quantity' => 0,
                'internal_code' => 'REC-BAD-' . $status . '-' . $suffix,
                'material_type' => 'Tela',
            ]]);
        } catch (InvalidArgumentException) {
            $operationFailed = true;
        }
        $assert($operationFailed, 'No debe permitir recepción desde OC proveedor ' . $status . '.');
    }

    $sentPoItemId = (int) $pdo->query('SELECT id FROM supplier_purchase_order_items WHERE supplier_purchase_order_id = ' . $sentPoId . ' LIMIT 1')->fetchColumn();
    $sentReceiptId = $goodsReceiptRepository->createDraftFromSupplierPurchaseOrder($sentPoId, ['receipt_number' => 'REC-SENT-' . $suffix], [[
        'supplier_purchase_order_item_id' => $sentPoItemId,
        'received_quantity' => 1,
        'accepted_quantity' => 1,
        'rejected_quantity' => 0,
        'internal_code' => 'MAT-REC-SENT-' . $suffix,
        'material_type' => 'Tela',
    ]]);
    $receiptIds[] = $sentReceiptId;
    $goodsReceiptRepository->cancelDraft($sentReceiptId);
    $assert((string) $goodsReceiptRepository->find($sentReceiptId)['status'] === 'cancelled', 'Debe permitir crear y anular recepción draft desde OC sent.');

    foreach ([
        ['items' => $validItem('MAT-REC-OVER-' . $suffix, 5, 5), 'message' => 'No debe recibir más que pendiente.'],
        ['items' => $validItem('MAT-REC-SUM-' . $suffix, 2, 2, 1), 'message' => 'No debe aceptar + rechazar más que recibido.'],
        ['items' => $validItem('MAT-REC-NEG-' . $suffix, 1, -1), 'message' => 'No debe permitir accepted_quantity negativa.'],
        ['items' => $validItem('', 1, 1), 'message' => 'El código interno debe ser obligatorio para aceptados.'],
    ] as $case) {
        $operationFailed = false;
        try {
            $goodsReceiptRepository->createDraftFromSupplierPurchaseOrder($confirmedPoId, ['receipt_number' => 'REC-FAIL-' . bin2hex(random_bytes(2))], $case['items']);
        } catch (InvalidArgumentException) {
            $operationFailed = true;
        }
        $assert($operationFailed, $case['message']);
    }

    $duplicateMaterialId = $inventoryRepository->create([
        'internal_code' => 'MAT-REC-DUP-' . $suffix,
        'material_type' => 'Tela',
        'description' => 'Duplicado recepción',
        'unit' => 'metro',
        'quantity_available' => 1,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'status' => 'active',
    ]);
    $inventoryIds[] = $duplicateMaterialId;
    $duplicateReceiptId = $goodsReceiptRepository->createDraftFromSupplierPurchaseOrder($confirmedPoId, ['receipt_number' => 'REC-DUP-' . $suffix], $validItem('MAT-REC-DUP-' . $suffix, 1, 1));
    $receiptIds[] = $duplicateReceiptId;
    $duplicateFailed = false;
    try {
        $goodsReceiptRepository->confirm($duplicateReceiptId);
    } catch (InvalidArgumentException) {
        $duplicateFailed = true;
    }
    $assert($duplicateFailed, 'No debe confirmar recepción con código interno duplicado.');
    $goodsReceiptRepository->cancelDraft($duplicateReceiptId);

    $partialReceiptId = $goodsReceiptRepository->createDraftFromSupplierPurchaseOrder($confirmedPoId, [
        'receipt_number' => 'REC-PART-' . $suffix,
        'delivery_note_number' => 'REM-PROV-' . $suffix,
        'invoice_number' => 'FAC-PROV-' . $suffix,
    ], $validItem('MAT-REC-PART-' . $suffix, 3, 2, 1));
    $receiptIds[] = $partialReceiptId;
    $goodsReceiptRepository->confirm($partialReceiptId);
    $partialReceipt = $goodsReceiptRepository->findWithItems($partialReceiptId);
    $inventoryIds[] = (int) $partialReceipt['items'][0]['raw_material_inventory_id'];
    $createdMaterial = $inventoryRepository->find($inventoryIds[array_key_last($inventoryIds)]);
    $assert((string) ($partialReceipt['status'] ?? '') === 'confirmed', 'Debe confirmar recepción parcial.');
    $assert((float) ($createdMaterial['quantity_available'] ?? 0) === 2.0, 'La recepción confirmada debe crear inventario solo por aceptado.');
    $assert((float) ($createdMaterial['quantity_reserved'] ?? 1) === 0.0, 'La recepción no debe reservar stock automáticamente.');
    $assert((string) ($createdMaterial['source_receipt_number'] ?? '') === 'REC-PART-' . $suffix, 'El inventario debe referenciar la recepción origen.');
    $assert((string) $pdo->query('SELECT status FROM supplier_purchase_orders WHERE id = ' . $confirmedPoId)->fetchColumn() === 'partially_received', 'La OC proveedor debe quedar parcialmente recibida.');
    $assert(count($inventoryRepository->search(['q' => 'REC-PART-' . $suffix])) >= 1, 'Debe buscar insumos por recepción.');
    $assert(count($inventoryRepository->search(['q' => 'LOTE-MAT-REC-PART-' . $suffix])) >= 1, 'Debe buscar insumos por lote.');

    $cancelConfirmedFailed = false;
    try {
        $goodsReceiptRepository->cancelDraft($partialReceiptId);
    } catch (InvalidArgumentException) {
        $cancelConfirmedFailed = true;
    }
    $assert($cancelConfirmedFailed, 'No debe anular recepción confirmada sin reversa auditada.');

    $finalReceiptId = $goodsReceiptRepository->createDraftFromSupplierPurchaseOrder($confirmedPoId, ['receipt_number' => 'REC-FINAL-' . $suffix], $validItem('MAT-REC-FINAL-' . $suffix, 2, 2));
    $receiptIds[] = $finalReceiptId;
    $goodsReceiptRepository->confirm($finalReceiptId);
    $finalReceipt = $goodsReceiptRepository->findWithItems($finalReceiptId);
    $inventoryIds[] = (int) $finalReceipt['items'][0]['raw_material_inventory_id'];
    $assert((string) $pdo->query('SELECT status FROM supplier_purchase_orders WHERE id = ' . $confirmedPoId)->fetchColumn() === 'received', 'La OC proveedor debe quedar recibida si se completa todo.');
    $assert(count($goodsReceiptRepository->pendingItemsForSupplierPurchaseOrder($confirmedPoId)) === 0, 'Los insumos deben quedar disponibles para futuras verificaciones sin saldo pendiente de OC.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['POST', '/supplier-purchase-orders/' . $confirmedPoId . '/goods-receipts'],
        ['GET', '/supplier-purchase-orders/' . $confirmedPoId . '/goods-receipts/create'],
        ['POST', '/goods-receipts/' . $finalReceiptId . '/confirm'],
        ['POST', '/goods-receipts/' . $finalReceiptId . '/cancel'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/goods-receipts/' . $finalReceiptId)), 'Consulta debe poder ver recepciones.');

    foreach ([
        'create_goods_receipt',
        'confirm_goods_receipt',
        'cancel_goods_receipt',
        'create_raw_material_from_receipt',
        'update_supplier_purchase_order_receipt_status',
    ] as $action) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = '{$action}'")->fetchColumn();
        $assert($count > 0, 'audit_log debe registrar ' . $action . '.');
    }
} finally {
    Auth::logout();
    foreach (array_reverse($receiptIds) as $receiptId) {
        $pdo->exec('DELETE FROM goods_receipts WHERE id = ' . (int) $receiptId);
    }
    foreach (array_reverse($inventoryIds) as $inventoryId) {
        $pdo->exec('DELETE FROM raw_material_inventory WHERE id = ' . (int) $inventoryId);
    }
    foreach (array_reverse($supplierPoIds) as $supplierPoId) {
        $pdo->exec('DELETE FROM supplier_purchase_orders WHERE id = ' . (int) $supplierPoId);
    }
    foreach (array_reverse($supplierQuoteIds) as $quoteId) {
        $pdo->exec('DELETE FROM supplier_quotes WHERE id = ' . (int) $quoteId);
    }
    if (is_int($requisitionId)) {
        $pdo->exec('DELETE FROM purchase_requisitions WHERE id = ' . $requisitionId);
    }
    if (is_int($stockCheckId)) {
        $pdo->exec('DELETE FROM stock_checks WHERE id = ' . $stockCheckId);
    }
    if (is_int($productionOrderId)) {
        $pdo->exec('DELETE FROM production_orders WHERE id = ' . $productionOrderId);
    }
    if (is_int($customerOrderId)) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . $customerOrderId);
    }
    if (is_int($shortMaterialId)) {
        $pdo->exec('DELETE FROM raw_material_inventory WHERE id = ' . $shortMaterialId);
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
