<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\CuttingOrderRepository;
use App\Repositories\FinishedGoodsInventoryRepository;
use App\Repositories\GoodsReceiptRepository;
use App\Repositories\PackagingOrderRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\PurchaseRequisitionRepository;
use App\Repositories\QualityControlRepository;
use App\Repositories\RawMaterialInventoryRepository;
use App\Repositories\RemissionRepository;
use App\Repositories\SeamsterRepository;
use App\Repositories\SewingOrderRepository;
use App\Repositories\StockCheckRepository;
use App\Repositories\SupplierPurchaseOrderRepository;
use App\Repositories\SupplierQuoteRepository;
use App\Repositories\SupplierRepository;
use App\Services\DocumentReportService;
use App\Services\DocumentWorkflowService;
use App\Services\NumberingService;
use App\Support\Auth;
use App\Support\Database;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = Database::connection();
$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$customerOrderRepository = new CustomerPurchaseOrderRepository();
$productionRepository = new ProductionOrderRepository();
$rawMaterialRepository = new RawMaterialInventoryRepository();
$stockCheckRepository = new StockCheckRepository();
$cuttingRepository = new CuttingOrderRepository();
$seamsterRepository = new SeamsterRepository();
$sewingRepository = new SewingOrderRepository();
$qualityRepository = new QualityControlRepository();
$packagingRepository = new PackagingOrderRepository();
$finishedRepository = new FinishedGoodsInventoryRepository();
$supplierRepository = new SupplierRepository();
$requisitionRepository = new PurchaseRequisitionRepository();
$quoteRepository = new SupplierQuoteRepository();
$supplierPoRepository = new SupplierPurchaseOrderRepository();
$goodsReceiptRepository = new GoodsReceiptRepository();
$remissionRepository = new RemissionRepository();

$suffix = 'E2E-' . bin2hex(random_bytes(4));
$clientIds = [];
$contractIds = [];
$customerOrderIds = [];
$productionOrderIds = [];
$materialIds = [];
$cuttingOrderIds = [];
$seamsterIds = [];
$sewingOrderIds = [];
$qualityCheckIds = [];
$packagingOrderIds = [];
$supplierIds = [];
$requisitionIds = [];
$quoteIds = [];
$supplierPoIds = [];
$goodsReceiptIds = [];
$remissionIds = [];

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para el E2E textil.');

    $quantity = 5.0;
    $clientId = $clientRepository->create([
        'name' => 'Cliente Textil ' . $suffix,
        'tax_id' => '802' . random_int(100000, 999999) . '-1',
        'addresses' => 'Casa central',
        'contacts' => 'Operaciones',
        'status' => 'active',
    ]);
    $clientIds[] = $clientId;

    $contractId = $contractRepository->create([
        'client_id' => $clientId,
        'date' => '2026-06-01',
        'contract_number' => 'CT-' . $suffix,
        'reference_number' => 'LIC-' . $suffix,
        'contract_type' => 'Licitacion',
        'tax_id' => '80212345-6',
        'status' => 'confirmed',
        'notes' => '',
        'total_amount' => $quantity * 15000,
        'is_provisional' => 0,
        'provisional_data' => null,
    ], [[
        'product_name' => 'Remera E2E',
        'unit_measure' => 'unidad',
        'quantity' => $quantity,
        'unit_price' => 15000,
        'total_item' => $quantity * 15000,
        'notes' => '',
    ]]);
    $contractIds[] = $contractId;

    $specId = $specRepository->create($contractId, [
        'item_code' => 'IT-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera flujo completo',
        'size' => 'M',
        'color' => 'Azul',
        'fabric' => 'Algodon',
        'measurements' => 'Molde M',
        'has_embroidery' => 0,
        'has_screen_printing' => 0,
        'quantity' => $quantity,
        'unit' => 'unidad',
        'label' => 'Etiqueta ' . $suffix,
    ]);
    $specRepository->confirm($contractId, $specId);

    $customerOrderId = $customerOrderRepository->create([
        'contract_id' => $contractId,
        'po_number' => 'OC-' . $suffix,
        'po_date' => '2026-06-01',
        'received_date' => '2026-06-01',
        'dependency_id' => '',
        'billing_contact_id' => '',
        'notes' => '',
        'attachment_path' => '',
    ], [['contract_item_spec_id' => $specId, 'quantity' => $quantity]]);
    $customerOrderIds[] = $customerOrderId;
    $customerOrderRepository->confirm($customerOrderId);

    $customerItemId = (int) $pdo->query('SELECT id FROM customer_purchase_order_items WHERE customer_purchase_order_id = ' . $customerOrderId . ' LIMIT 1')->fetchColumn();
    $productionOrderId = $productionRepository->create([
        'customer_purchase_order_id' => $customerOrderId,
        'production_number' => 'OP-' . $suffix,
        'planned_start_date' => '2026-06-02',
        'planned_end_date' => '2026-06-10',
        'notes' => 'Flujo E2E stock suficiente',
    ], [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => $quantity]]);
    $productionOrderIds[] = $productionOrderId;
    $productionRepository->confirm($productionOrderId);
    $productionItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();

    $materialId = $rawMaterialRepository->create([
        'internal_code' => 'MAT-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela flujo completo',
        'unit' => 'unidad',
        'quantity_available' => $quantity,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_item_code' => 'IT-' . $suffix,
        'status' => 'active',
    ]);
    $materialIds[] = $materialId;

    $stockCheckId = $stockCheckRepository->createForProductionOrder($productionOrderId, ['check_number' => 'CHK-OK-' . $suffix], [[
        'production_order_item_id' => $productionItemId,
        'raw_material_inventory_id' => $materialId,
    ]]);
    $assert((string) ($stockCheckRepository->find($stockCheckId)['status'] ?? '') === 'sufficient', 'El stock check suficiente debe quedar sufficient.');
    $stockCheckRepository->reserve($stockCheckId);

    $cuttingOrderId = $cuttingRepository->createFromProductionOrder($productionOrderId, [
        'cutting_number' => 'CORTE-' . $suffix,
        'planned_date' => '2026-06-03',
        'cut_by' => 'Mesa 1',
        'notes' => '',
    ], [['production_order_item_id' => $productionItemId, 'quantity_to_cut' => $quantity]]);
    $cuttingOrderIds[] = $cuttingOrderId;
    $cuttingRepository->confirm($cuttingOrderId);
    $cutting = $cuttingRepository->findWithDetails($cuttingOrderId);
    $cuttingRepository->complete($cuttingOrderId, [(int) $cutting['items'][0]['id'] => $quantity]);

    $consumedMaterial = $rawMaterialRepository->find($materialId);
    $assert((float) ($consumedMaterial['quantity_available'] ?? 0) === 0.0, 'El corte debe consumir el insumo reservado.');
    $assert((float) ($consumedMaterial['quantity_reserved'] ?? 0) === 0.0, 'El corte debe liberar la reserva consumida.');

    $seamsterId = $seamsterRepository->create([
        'name' => 'Costurero ' . $suffix,
        'document_number' => 'CI-' . $suffix,
        'phone' => '0981000000',
        'email' => strtolower($suffix) . '@example.test',
        'address' => 'Taller',
        'status' => 'active',
        'notes' => '',
    ]);
    $seamsterIds[] = $seamsterId;

    $availableSewing = $sewingRepository->availableItemsFromCuttingOrder($cuttingOrderId);
    $sewingOrderId = $sewingRepository->createFromCuttingOrder($cuttingOrderId, [
        'seamster_id' => $seamsterId,
        'sewing_number' => 'CONF-' . $suffix,
    ], [['cutting_order_item_id' => (int) $availableSewing[0]['id'], 'quantity_assigned' => $quantity]]);
    $sewingOrderIds[] = $sewingOrderId;
    $sewingRepository->confirm($sewingOrderId);
    $sewing = $sewingRepository->findWithDetails($sewingOrderId);
    $sewingRepository->registerProgress($sewingOrderId, [[
        'sewing_order_item_id' => (int) $sewing['items'][0]['id'],
        'quantity_completed' => $quantity,
        'quantity_rejected' => 0,
    ]], '2026-06-04');

    $availableQuality = $qualityRepository->availableItemsFromSewingOrder($sewingOrderId);
    $qualityCheckId = $qualityRepository->createFromSewingOrder($sewingOrderId, ['qc_number' => 'QC-' . $suffix], [[
        'sewing_order_item_id' => (int) $availableQuality[0]['id'],
        'quantity_received' => $quantity,
    ]]);
    $qualityCheckIds[] = $qualityCheckId;
    $quality = $qualityRepository->findWithDetails($qualityCheckId);
    $qualityRepository->registerResults($qualityCheckId, [[
        'quality_control_check_item_id' => (int) $quality['items'][0]['id'],
        'quantity_approved' => $quantity,
        'quantity_rejected' => 0,
        'quantity_rework' => 0,
        'model_ok' => 1,
        'size_ok' => 1,
        'quantity_ok' => 1,
        'sewing_ok' => 1,
        'finishing_ok' => 1,
    ]]);
    $qualityRepository->confirm($qualityCheckId);

    $availablePackaging = $packagingRepository->availableItemsFromQualityControl($qualityCheckId);
    $packagingOrderId = $packagingRepository->createFromQualityControl($qualityCheckId, ['packaging_number' => 'EMP-' . $suffix], [[
        'quality_control_check_item_id' => (int) $availablePackaging[0]['id'],
        'quantity_to_pack' => $quantity,
        'label' => 'Etiqueta ' . $suffix,
    ]]);
    $packagingOrderIds[] = $packagingOrderId;
    $packaging = $packagingRepository->findWithDetails($packagingOrderId);
    $packagingRepository->pack($packagingOrderId, [[
        'packaging_order_item_id' => (int) $packaging['items'][0]['id'],
        'quantity_packed' => $quantity,
        'package_code' => 'PKG-' . $suffix,
    ]], 'Deposito terminado');

    $finished = $packagingRepository->findWithDetails($packagingOrderId)['inventory'][0] ?? null;
    $assert($finished !== null, 'El empaquetado debe generar inventario terminado.');
    $finishedInventoryId = (int) $finished['id'];
    $assert((float) $finished['quantity_available'] === $quantity, 'El inventario terminado debe iniciar con cantidad disponible.');

    [$remissionItems, $remissionErrors] = $finishedRepository->prepareRemissionItems([$finishedInventoryId], [$finishedInventoryId => 3.0]);
    $assert($remissionErrors === [] && count($remissionItems) === 1, 'Debe preparar remisión desde inventario terminado.');
    $remissionId = $remissionRepository->create([
        'remission_number' => (new NumberingService())->next('remissions'),
        'remission_date' => '2026-06-05',
        'status' => 'draft',
        'notes' => 'Remisión E2E desde inventario terminado',
        'total_amount' => 3 * 15000,
        'client_id' => $clientId,
        'contract_id' => $contractId,
        'reference_number' => 'LIC-' . $suffix,
        'contract_type' => 'Licitacion',
        'tax_id' => '80212345-6',
        'origin_address' => 'Deposito terminado',
        'destination_address' => 'Cliente',
        'transfer_start_date' => '2026-06-05',
        'transfer_end_date' => '2026-06-05',
        'vehicle_brand' => '',
        'vehicle_plate' => '',
        'carrier_name' => '',
        'carrier_tax_id' => '',
        'driver_name' => '',
        'driver_document' => '',
    ], $remissionItems, []);
    $remissionIds[] = $remissionId;
    (new DocumentWorkflowService())->transition('remissions', $remissionId, 'confirm');
    $afterRemission = $finishedRepository->find($finishedInventoryId);
    $assert((float) ($afterRemission['quantity_available'] ?? 0) === 2.0, 'La remisión debe descontar inventario terminado.');
    $assert((float) ($afterRemission['quantity_remitted'] ?? 0) === 3.0, 'La remisión debe registrar cantidad remitida.');

    $clientShortId = $clientRepository->create([
        'name' => 'Cliente Faltante ' . $suffix,
        'tax_id' => '803' . random_int(100000, 999999) . '-1',
        'addresses' => 'Sucursal',
        'contacts' => 'Compras',
        'status' => 'active',
    ]);
    $clientIds[] = $clientShortId;
    $contractShortId = $contractRepository->create([
        'client_id' => $clientShortId,
        'date' => '2026-06-01',
        'contract_number' => 'CT-FALT-' . $suffix,
        'reference_number' => 'LIC-FALT-' . $suffix,
        'contract_type' => 'Licitacion',
        'tax_id' => '80312345-6',
        'status' => 'confirmed',
        'notes' => '',
        'total_amount' => 6 * 10000,
        'is_provisional' => 0,
        'provisional_data' => null,
    ], [[
        'product_name' => 'Pantalon faltante',
        'unit_measure' => 'unidad',
        'quantity' => 6,
        'unit_price' => 10000,
        'total_item' => 6 * 10000,
        'notes' => '',
    ]]);
    $contractIds[] = $contractShortId;
    $specShortId = $specRepository->create($contractShortId, [
        'item_code' => 'FALT-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Pantalon',
        'description' => 'Pantalon con faltante',
        'quantity' => 6,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($contractShortId, $specShortId);
    $customerShortId = $customerOrderRepository->create([
        'contract_id' => $contractShortId,
        'po_number' => 'OC-FALT-' . $suffix,
        'po_date' => '2026-06-01',
        'received_date' => '2026-06-01',
        'dependency_id' => '',
        'billing_contact_id' => '',
        'notes' => '',
        'attachment_path' => '',
    ], [['contract_item_spec_id' => $specShortId, 'quantity' => 6]]);
    $customerOrderIds[] = $customerShortId;
    $customerOrderRepository->confirm($customerShortId);
    $customerShortItemId = (int) $pdo->query('SELECT id FROM customer_purchase_order_items WHERE customer_purchase_order_id = ' . $customerShortId . ' LIMIT 1')->fetchColumn();
    $productionShortId = $productionRepository->create([
        'customer_purchase_order_id' => $customerShortId,
        'production_number' => 'OP-FALT-' . $suffix,
        'planned_start_date' => '',
        'planned_end_date' => '',
        'notes' => '',
    ], [['customer_purchase_order_item_id' => $customerShortItemId, 'quantity' => 6]]);
    $productionOrderIds[] = $productionShortId;
    $productionRepository->confirm($productionShortId);
    $productionShortItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionShortId . ' LIMIT 1')->fetchColumn();
    $shortMaterialId = $rawMaterialRepository->create([
        'internal_code' => 'MAT-FALT-SHORT-' . $suffix,
        'material_type' => 'Pantalon',
        'description' => 'Insumo insuficiente',
        'unit' => 'unidad',
        'quantity_available' => 2,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_item_code' => 'FALT-' . $suffix,
        'status' => 'active',
    ]);
    $materialIds[] = $shortMaterialId;
    $shortCheckId = $stockCheckRepository->createForProductionOrder($productionShortId, ['check_number' => 'CHK-FALT-' . $suffix], [[
        'production_order_item_id' => $productionShortItemId,
        'raw_material_inventory_id' => $shortMaterialId,
    ]]);
    $assert((string) ($stockCheckRepository->find($shortCheckId)['status'] ?? '') === 'insufficient', 'El stock check con faltante debe quedar insufficient.');

    $requisitionId = $requisitionRepository->createFromStockCheck($shortCheckId, ['requisition_number' => 'REQ-' . $suffix]);
    $requisitionIds[] = $requisitionId;
    foreach (['A', 'B', 'C'] as $letter) {
        $supplierIds[] = $supplierRepository->create([
            'name' => 'Proveedor ' . $letter . ' ' . $suffix,
            'ruc' => '80' . random_int(1000000, 9999999) . '-' . random_int(0, 9),
            'payment_terms' => 'Contado',
            'delivery_terms' => 'Planta',
            'status' => 'active',
        ]);
    }
    $requisitionRepository->addSupplierQuoteRequests($requisitionId, $supplierIds);
    $assert($requisitionRepository->quoteRequestCount($requisitionId) === 3, 'La requisición debe solicitar 3 proveedores.');
    $requisition = $requisitionRepository->findWithDetails($requisitionId);
    $requisitionItemId = (int) $requisition['items'][0]['id'];
    $quoteId = $quoteRepository->registerReceivedQuote($requisitionId, $supplierIds[0], [
        'quote_number' => 'PRES-' . $suffix,
        'quote_date' => '2026-06-02',
        'currency' => 'PYG',
        'tax_amount' => 0,
        'delivery_days' => 3,
        'payment_terms' => 'Contado',
    ], [[
        'purchase_requisition_item_id' => $requisitionItemId,
        'description' => 'Insumo faltante comprado',
        'unit' => 'unidad',
        'quantity' => 6,
        'unit_price' => 7000,
    ]]);
    $quoteIds[] = $quoteId;
    $quoteRepository->approve($quoteId);
    $supplierPoId = $supplierPoRepository->createFromApprovedQuote($quoteId, ['supplier_po_number' => 'OCP-' . $suffix]);
    $supplierPoIds[] = $supplierPoId;
    $supplierPoRepository->confirm($supplierPoId);
    $supplierPoItemId = (int) $pdo->query('SELECT id FROM supplier_purchase_order_items WHERE supplier_purchase_order_id = ' . $supplierPoId . ' LIMIT 1')->fetchColumn();
    $receiptId = $goodsReceiptRepository->createDraftFromSupplierPurchaseOrder($supplierPoId, [
        'receipt_number' => 'REC-' . $suffix,
        'received_at' => '2026-06-03 10:00:00',
        'delivery_note_number' => 'DN-' . $suffix,
    ], [[
        'supplier_purchase_order_item_id' => $supplierPoItemId,
        'description' => 'Insumo faltante comprado',
        'unit' => 'unidad',
        'received_quantity' => 6,
        'accepted_quantity' => 6,
        'rejected_quantity' => 0,
        'internal_code' => 'MAT-FALT-OK-' . $suffix,
        'material_type' => 'Pantalon',
        'location' => 'Deposito insumos',
        'cost' => 7000,
    ]]);
    $goodsReceiptIds[] = $receiptId;
    $goodsReceiptRepository->confirm($receiptId);
    $receivedMaterialId = (int) $pdo->query('SELECT raw_material_inventory_id FROM goods_receipt_items WHERE goods_receipt_id = ' . $receiptId . ' LIMIT 1')->fetchColumn();
    $materialIds[] = $receivedMaterialId;
    $receivedMaterial = $rawMaterialRepository->find($receivedMaterialId);
    $assert((float) ($receivedMaterial['quantity_available'] ?? 0) === 6.0, 'La recepción debe crear inventario de insumos.');

    $newCheckId = $stockCheckRepository->createForProductionOrder($productionShortId, ['check_number' => 'CHK-FALT-OK-' . $suffix], [[
        'production_order_item_id' => $productionShortItemId,
        'raw_material_inventory_id' => $receivedMaterialId,
    ]]);
    $assert((string) ($stockCheckRepository->find($newCheckId)['status'] ?? '') === 'sufficient', 'Luego de ingresar insumos debe existir disponibilidad suficiente para reservar y cortar.');

    $reports = new DocumentReportService();
    $assert(isset($reports->availableTypes()['textile_finished_goods_available']), 'Reportes textiles deben estar registrados.');
    $shortageReport = $reports->report('textile_raw_material_shortages', ['q' => 'CHK-FALT-' . $suffix]);
    $assert(count($shortageReport['rows']) >= 1, 'El reporte de faltantes debe encontrar el stock check insuficiente.');
    $remissionReport = $reports->report('textile_remissions_from_finished_goods', ['q' => 'LIC-' . $suffix]);
    $assert(count($remissionReport['rows']) >= 1, 'El reporte de remisiones desde inventario terminado debe encontrar la remisión E2E.');

    return true;
} finally {
    Auth::logout();
    foreach ($remissionIds as $id) {
        $pdo->exec('DELETE FROM invoice_items WHERE remission_item_id IN (SELECT id FROM remission_items WHERE remission_id = ' . (int) $id . ')');
        $pdo->exec('DELETE FROM remission_items WHERE remission_id = ' . (int) $id);
        $pdo->exec('DELETE FROM remissions WHERE id = ' . (int) $id);
    }
    foreach ($packagingOrderIds as $id) {
        $pdo->exec('DELETE FROM finished_goods_inventory WHERE packaging_order_id = ' . (int) $id);
        $pdo->exec('DELETE FROM packaging_orders WHERE id = ' . (int) $id);
    }
    foreach ($qualityCheckIds as $id) {
        $pdo->exec('DELETE FROM quality_rework_orders WHERE quality_control_check_id = ' . (int) $id);
        $pdo->exec('DELETE FROM quality_control_checks WHERE id = ' . (int) $id);
    }
    foreach ($sewingOrderIds as $id) {
        $pdo->exec('DELETE FROM sewing_progress_entries WHERE sewing_order_id = ' . (int) $id);
        $pdo->exec('DELETE FROM sewing_orders WHERE id = ' . (int) $id);
    }
    foreach ($cuttingOrderIds as $id) {
        $pdo->exec('DELETE FROM cutting_orders WHERE id = ' . (int) $id);
    }
    foreach ($goodsReceiptIds as $id) {
        $pdo->exec('DELETE FROM goods_receipts WHERE id = ' . (int) $id);
    }
    foreach ($supplierPoIds as $id) {
        $pdo->exec('DELETE FROM supplier_purchase_orders WHERE id = ' . (int) $id);
    }
    foreach ($quoteIds as $id) {
        $pdo->exec('DELETE FROM supplier_quotes WHERE id = ' . (int) $id);
    }
    foreach ($requisitionIds as $id) {
        $pdo->exec('DELETE FROM purchase_requisitions WHERE id = ' . (int) $id);
    }
    foreach ($productionOrderIds as $id) {
        $pdo->exec('DELETE FROM production_orders WHERE id = ' . (int) $id);
    }
    foreach ($materialIds as $id) {
        if ($id > 0) {
            $pdo->exec('DELETE FROM raw_material_inventory WHERE id = ' . (int) $id);
        }
    }
    foreach ($customerOrderIds as $id) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . (int) $id);
    }
    foreach ($contractIds as $id) {
        $pdo->exec('DELETE FROM contracts WHERE id = ' . (int) $id);
    }
    foreach ($clientIds as $id) {
        $pdo->exec('DELETE FROM clients WHERE id = ' . (int) $id);
    }
    foreach ($seamsterIds as $id) {
        $pdo->exec('DELETE FROM seamsters WHERE id = ' . (int) $id);
    }
    foreach ($supplierIds as $id) {
        $pdo->exec('DELETE FROM suppliers WHERE id = ' . (int) $id);
    }
}
