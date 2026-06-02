<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\CuttingOrderRepository;
use App\Repositories\FinishedGoodsInventoryRepository;
use App\Repositories\PackagingOrderRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\QualityControlRepository;
use App\Repositories\RawMaterialInventoryRepository;
use App\Repositories\SeamsterRepository;
use App\Repositories\SewingOrderRepository;
use App\Repositories\StockCheckRepository;
use App\Support\AccessControl;
use App\Support\Auth;
use App\Support\Database;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = Database::connection();
foreach (['packaging_orders', 'packaging_order_items', 'finished_goods_inventory'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 017 debe crear la tabla ' . $table . '.');
}

$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$customerOrderRepository = new CustomerPurchaseOrderRepository();
$productionRepository = new ProductionOrderRepository();
$inventoryRepository = new RawMaterialInventoryRepository();
$stockCheckRepository = new StockCheckRepository();
$cuttingRepository = new CuttingOrderRepository();
$seamsterRepository = new SeamsterRepository();
$sewingRepository = new SewingOrderRepository();
$qualityRepository = new QualityControlRepository();
$packagingRepository = new PackagingOrderRepository();
$finishedRepository = new FinishedGoodsInventoryRepository();

$suffix = bin2hex(random_bytes(4));
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

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para empaquetado.');

    $quantity = 4;
    $clientId = $clientRepository->create([
        'name' => 'Cliente Empaque ' . $suffix,
        'tax_id' => '809' . random_int(100000, 999999) . '-1',
        'addresses' => 'Central',
        'contacts' => 'Produccion',
        'status' => 'active',
    ]);
    $clientIds[] = $clientId;

    $contractId = $contractRepository->create([
        'client_id' => $clientId,
        'date' => '2026-06-01',
        'contract_number' => 'CT-EMP-' . $suffix,
        'reference_number' => 'REF-EMP-' . $suffix,
        'contract_type' => 'Licitacion',
        'tax_id' => '80712345-6',
        'status' => 'confirmed',
        'notes' => '',
        'total_amount' => $quantity * 12000,
        'is_provisional' => 0,
        'provisional_data' => null,
    ], [[
        'product_name' => 'Remera empaque',
        'unit_measure' => 'unidad',
        'quantity' => $quantity,
        'unit_price' => 12000,
        'total_item' => $quantity * 12000,
        'notes' => '',
    ]]);
    $contractIds[] = $contractId;

    $specId = $specRepository->create($contractId, [
        'item_code' => 'EMP-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera para empaquetado',
        'size' => 'L',
        'color' => 'Verde',
        'fabric' => 'Algodón',
        'measurements' => 'Molde L',
        'has_embroidery' => 0,
        'has_screen_printing' => 0,
        'quantity' => $quantity,
        'unit' => 'unidad',
        'label' => 'Etiqueta Feris',
    ]);
    $specRepository->confirm($contractId, $specId);

    $customerOrderId = $customerOrderRepository->create([
        'contract_id' => $contractId,
        'po_number' => 'OC-EMP-' . $suffix,
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
        'production_number' => 'OP-EMP-' . $suffix,
        'planned_start_date' => '',
        'planned_end_date' => '',
        'notes' => '',
    ], [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => $quantity]]);
    $productionOrderIds[] = $productionOrderId;
    $productionItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();
    $productionRepository->confirm($productionOrderId);

    $materialId = $inventoryRepository->create([
        'internal_code' => 'MAT-EMP-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela para empaquetado',
        'unit' => 'unidad',
        'quantity_available' => $quantity,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_item_code' => 'EMP-' . $suffix,
        'status' => 'active',
    ]);
    $materialIds[] = $materialId;

    $stockCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-EMP-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $materialId]]
    );
    $stockCheckRepository->reserve($stockCheckId);

    $cuttingOrderId = $cuttingRepository->createFromProductionOrder(
        $productionOrderId,
        ['cutting_number' => 'CORTE-EMP-' . $suffix, 'planned_date' => '2026-06-03', 'cut_by' => 'Mesa 1', 'notes' => 'Corte para empaque'],
        [['production_order_item_id' => $productionItemId, 'quantity_to_cut' => $quantity, 'notes' => 'Corte completo']]
    );
    $cuttingOrderIds[] = $cuttingOrderId;
    $cuttingRepository->confirm($cuttingOrderId);
    $cut = $cuttingRepository->findWithDetails($cuttingOrderId);
    $cuttingRepository->complete($cuttingOrderId, [(int) $cut['items'][0]['id'] => (float) $quantity]);

    $seamsterId = $seamsterRepository->create([
        'name' => 'Costurera Empaque ' . $suffix,
        'document_number' => 'CI-EMP-' . $suffix,
        'phone' => '0981000000',
        'email' => 'emp-' . $suffix . '@example.test',
        'address' => 'Taller',
        'status' => 'active',
        'notes' => '',
    ]);
    $seamsterIds[] = $seamsterId;

    $availableSewingSource = $sewingRepository->availableItemsFromCuttingOrder($cuttingOrderId);
    $sewingOrderId = $sewingRepository->createFromCuttingOrder(
        $cuttingOrderId,
        ['seamster_id' => $seamsterId, 'sewing_number' => 'CONF-EMP-' . $suffix],
        [['cutting_order_item_id' => (int) $availableSewingSource[0]['id'], 'quantity_assigned' => $quantity]]
    );
    $sewingOrderIds[] = $sewingOrderId;
    $sewingRepository->confirm($sewingOrderId);
    $sewing = $sewingRepository->findWithDetails($sewingOrderId);
    $sewingRepository->registerProgress($sewingOrderId, [[
        'sewing_order_item_id' => (int) $sewing['items'][0]['id'],
        'quantity_completed' => $quantity,
        'quantity_rejected' => 0,
        'notes' => 'Listo para calidad',
    ]], '2026-06-05');

    $availableQuality = $qualityRepository->availableItemsFromSewingOrder($sewingOrderId);
    $qcId = $qualityRepository->createFromSewingOrder(
        $sewingOrderId,
        ['qc_number' => 'QC-EMP-' . $suffix, 'notes' => 'Control para empaque'],
        [['sewing_order_item_id' => (int) $availableQuality[0]['id'], 'quantity_received' => 3]]
    );
    $qualityCheckIds[] = $qcId;
    $qc = $qualityRepository->findWithDetails($qcId);
    $qcItemId = (int) $qc['items'][0]['id'];
    $qualityRepository->registerResults($qcId, [[
        'quality_control_check_item_id' => $qcItemId,
        'quantity_approved' => 2,
        'quantity_rejected' => 1,
        'quantity_rework' => 0,
        'model_ok' => 1,
        'size_ok' => 1,
        'quantity_ok' => 1,
        'sewing_ok' => 1,
        'finishing_ok' => 1,
        'notes' => 'Aprobación parcial',
    ]]);
    $qualityRepository->confirm($qcId);
    $assert((string) ($qualityRepository->find($qcId)['status'] ?? '') === 'partially_approved', 'Calidad parcial debe quedar disponible para empaque.');

    $availablePackaging = $packagingRepository->availableItemsFromQualityControl($qcId);
    $assert(count($availablePackaging) === 1 && (float) $availablePackaging[0]['quantity_available_to_pack'] === 2.0, 'Empaque solo debe tomar cantidades aprobadas.');

    $badRejectedPack = false;
    try {
        $packagingRepository->createFromQualityControl(
            $qcId,
            ['packaging_number' => 'EMP-OVER-' . $suffix],
            [['quality_control_check_item_id' => (int) $availablePackaging[0]['id'], 'quantity_to_pack' => 3]]
        );
    } catch (InvalidArgumentException) {
        $badRejectedPack = true;
    }
    $assert($badRejectedPack, 'No debe empaquetar cantidades rechazadas ni por encima del aprobado.');

    $draftPackagingId = $packagingRepository->createFromQualityControl(
        $qcId,
        ['packaging_number' => 'EMP-DRAFT-' . $suffix, 'notes' => 'Reserva de empaque'],
        [['quality_control_check_item_id' => (int) $availablePackaging[0]['id'], 'quantity_to_pack' => 2, 'label' => 'Etiqueta Feris']]
    );
    $packagingOrderIds[] = $draftPackagingId;
    $assert($packagingRepository->availableItemsFromQualityControl($qcId) === [], 'Un draft debe reservar el saldo aprobado pendiente.');

    $packagingRepository->cancelDraft($draftPackagingId);
    $assert(count($packagingRepository->availableItemsFromQualityControl($qcId)) === 1, 'Cancelar draft debe liberar el saldo aprobado.');

    $packagingId = $packagingRepository->createFromQualityControl(
        $qcId,
        ['packaging_number' => 'EMP-OK-' . $suffix],
        [['quality_control_check_item_id' => (int) $availablePackaging[0]['id'], 'quantity_to_pack' => 2, 'label' => 'Etiqueta Feris']]
    );
    $packagingOrderIds[] = $packagingId;
    $packaging = $packagingRepository->findWithDetails($packagingId);
    $packItemId = (int) $packaging['items'][0]['id'];

    $packOverFailed = false;
    try {
        $packagingRepository->pack($packagingId, [[
            'packaging_order_item_id' => $packItemId,
            'quantity_packed' => 2.5,
            'package_code' => 'PKG-OVER-' . $suffix,
        ]]);
    } catch (InvalidArgumentException) {
        $packOverFailed = true;
    }
    $assert($packOverFailed, 'quantity_packed no puede superar quantity_to_pack.');

    $packagingRepository->pack($packagingId, [[
        'packaging_order_item_id' => $packItemId,
        'quantity_packed' => 1.5,
        'package_code' => 'PKG-EMP-' . $suffix,
        'notes' => 'Empaque parcial auditado',
    ]], 'Depósito terminado');

    $packed = $packagingRepository->findWithDetails($packagingId);
    $assert((string) $packed['status'] === 'packed', 'Empaque debe pasar a packed.');
    $assert(count($packed['inventory']) === 1, 'Cada ítem empacado debe generar inventario terminado.');
    $inventory = $packed['inventory'][0];
    $assert((float) $inventory['quantity_available'] === 1.5, 'Inventario terminado debe usar quantity_packed como disponible.');
    $assert((float) $inventory['quantity_reserved'] === 0.0 && (float) $inventory['quantity_remitted'] === 0.0, 'Inventario terminado debe iniciar sin reservas ni remisiones.');
    $assert((string) $inventory['status'] === 'available', 'Inventario terminado debe iniciar disponible.');
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'packaging', 'Empaque debe mover producción a etapa packaging.');

    $cancelPackedFailed = false;
    try {
        $packagingRepository->cancelDraft($packagingId);
    } catch (InvalidArgumentException) {
        $cancelPackedFailed = true;
    }
    $assert($cancelPackedFailed, 'No debe cancelar empaquetado que ya generó inventario.');

    $foundInventory = $finishedRepository->search(['item_code' => 'EMP-' . $suffix, 'status' => 'available']);
    $assert(count($foundInventory) >= 1, 'Debe buscar inventario terminado por código de ítem y estado.');
    $assert($finishedRepository->find((int) $inventory['id']) !== null, 'Debe encontrar detalle de inventario terminado.');

    $packagingRepository->close($packagingId);
    $assert((string) ($packagingRepository->find($packagingId)['status'] ?? '') === 'closed', 'Debe cerrar packaging packed.');

    $auditCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_log WHERE document_type = "packaging_orders" AND document_id = ' . $packagingId)->fetchColumn();
    $assert($auditCount >= 3, 'audit_log debe registrar acciones principales de empaquetado.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['GET', '/quality-control/' . $qcId . '/packaging-orders/create'],
        ['POST', '/quality-control/' . $qcId . '/packaging-orders'],
        ['POST', '/packaging-orders/' . $packagingId . '/pack'],
        ['POST', '/packaging-orders/' . $packagingId . '/cancel'],
        ['POST', '/packaging-orders/' . $packagingId . '/close'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/packaging-orders/' . $packagingId)), 'Consulta debe poder ver empaquetado.');
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/finished-goods-inventory/' . (int) $inventory['id'])), 'Consulta debe poder ver inventario terminado.');
} finally {
    Auth::logout();
    if ($packagingOrderIds !== []) {
        $ids = implode(',', array_map('intval', $packagingOrderIds));
        $pdo->exec('DELETE FROM finished_goods_inventory WHERE packaging_order_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM packaging_orders WHERE id IN (' . $ids . ')');
    }
    foreach ($qualityCheckIds as $qualityCheckId) {
        $pdo->exec('DELETE FROM quality_control_checks WHERE id = ' . (int) $qualityCheckId);
    }
    foreach ($sewingOrderIds as $sewingOrderId) {
        $pdo->exec('DELETE FROM sewing_orders WHERE id = ' . (int) $sewingOrderId);
    }
    foreach ($cuttingOrderIds as $cuttingOrderId) {
        $pdo->exec('DELETE FROM cutting_orders WHERE id = ' . (int) $cuttingOrderId);
    }
    foreach ($productionOrderIds as $productionOrderId) {
        $pdo->exec('DELETE FROM production_orders WHERE id = ' . (int) $productionOrderId);
    }
    foreach ($customerOrderIds as $customerOrderId) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . (int) $customerOrderId);
    }
    foreach ($materialIds as $materialId) {
        $pdo->exec('DELETE FROM raw_material_inventory WHERE id = ' . (int) $materialId);
    }
    foreach ($seamsterIds as $seamsterId) {
        $pdo->exec('DELETE FROM seamsters WHERE id = ' . (int) $seamsterId);
    }
    foreach ($contractIds as $contractId) {
        $contractRepository->delete((int) $contractId);
    }
    foreach ($clientIds as $clientId) {
        $clientRepository->delete((int) $clientId);
    }
}

return true;
