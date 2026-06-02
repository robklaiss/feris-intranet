<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\CuttingOrderRepository;
use App\Repositories\ExternalWorkOrderRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\QualityControlRepository;
use App\Repositories\QualityReworkRepository;
use App\Repositories\RawMaterialInventoryRepository;
use App\Repositories\SeamsterRepository;
use App\Repositories\SewingOrderRepository;
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
foreach (['quality_control_checks', 'quality_control_check_items', 'quality_rework_orders'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 016 debe crear la tabla ' . $table . '.');
}

$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$customerOrderRepository = new CustomerPurchaseOrderRepository();
$productionRepository = new ProductionOrderRepository();
$inventoryRepository = new RawMaterialInventoryRepository();
$stockCheckRepository = new StockCheckRepository();
$cuttingRepository = new CuttingOrderRepository();
$externalRepository = new ExternalWorkOrderRepository();
$supplierRepository = new SupplierRepository();
$seamsterRepository = new SeamsterRepository();
$sewingRepository = new SewingOrderRepository();
$qualityRepository = new QualityControlRepository();
$reworkRepository = new QualityReworkRepository();

$suffix = bin2hex(random_bytes(4));
$clientIds = [];
$contractIds = [];
$customerOrderIds = [];
$productionOrderIds = [];
$materialIds = [];
$cuttingOrderIds = [];
$externalWorkOrderIds = [];
$seamsterIds = [];
$qualityCheckIds = [];
$sewingOrderIds = [];
$supplierId = null;

$makeFlow = static function (string $tag, bool $requiresExternal, int $quantity) use (
    $suffix,
    $clientRepository,
    $contractRepository,
    $specRepository,
    $customerOrderRepository,
    $productionRepository,
    $inventoryRepository,
    $stockCheckRepository,
    $cuttingRepository,
    $pdo,
    &$clientIds,
    &$contractIds,
    &$customerOrderIds,
    &$productionOrderIds,
    &$materialIds,
    &$cuttingOrderIds
): array {
    $clientId = $clientRepository->create([
        'name' => 'Cliente Calidad ' . $tag . ' ' . $suffix,
        'tax_id' => '808' . random_int(100000, 999999) . '-1',
        'addresses' => 'Central',
        'contacts' => 'Produccion',
        'status' => 'active',
    ]);
    $clientIds[] = $clientId;

    $contractId = $contractRepository->create([
        'client_id' => $clientId,
        'date' => '2026-06-01',
        'contract_number' => 'CT-QC-' . $tag . '-' . $suffix,
        'reference_number' => 'REF-QC-' . $tag . '-' . $suffix,
        'contract_type' => 'Licitacion',
        'tax_id' => '80712345-6',
        'status' => 'confirmed',
        'notes' => '',
        'total_amount' => $quantity * 10000,
        'is_provisional' => 0,
        'provisional_data' => null,
    ], [[
        'product_name' => 'Remera calidad ' . $tag,
        'unit_measure' => 'unidad',
        'quantity' => $quantity,
        'unit_price' => 10000,
        'total_item' => $quantity * 10000,
        'notes' => '',
    ]]);
    $contractIds[] = $contractId;

    $specId = $specRepository->create($contractId, [
        'item_code' => 'QC-' . strtoupper($tag) . '-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera para calidad ' . $tag,
        'size' => 'M',
        'color' => $requiresExternal ? 'Azul' : 'Blanco',
        'fabric' => 'Algodón',
        'measurements' => 'Molde M',
        'has_embroidery' => $requiresExternal ? 1 : 0,
        'has_screen_printing' => 0,
        'quantity' => $quantity,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($contractId, $specId);

    $customerOrderId = $customerOrderRepository->create([
        'contract_id' => $contractId,
        'po_number' => 'OC-QC-' . $tag . '-' . $suffix,
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
        'production_number' => 'OP-QC-' . $tag . '-' . $suffix,
        'planned_start_date' => '',
        'planned_end_date' => '',
        'notes' => '',
    ], [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => $quantity]]);
    $productionOrderIds[] = $productionOrderId;

    $productionItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();
    $productionRepository->confirm($productionOrderId);

    $materialId = $inventoryRepository->create([
        'internal_code' => 'MAT-QC-' . strtoupper($tag) . '-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela para calidad ' . $tag,
        'unit' => 'unidad',
        'quantity_available' => $quantity,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_item_code' => 'QC-' . strtoupper($tag) . '-' . $suffix,
        'status' => 'active',
    ]);
    $materialIds[] = $materialId;

    $stockCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-QC-' . $tag . '-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $materialId]]
    );
    $stockCheckRepository->reserve($stockCheckId);

    $cuttingOrderId = $cuttingRepository->createFromProductionOrder(
        $productionOrderId,
        ['cutting_number' => 'CORTE-QC-' . $tag . '-' . $suffix, 'planned_date' => '2026-06-03', 'cut_by' => 'Mesa 1', 'notes' => 'Corte para calidad'],
        [['production_order_item_id' => $productionItemId, 'quantity_to_cut' => $quantity, 'notes' => 'Corte completo']]
    );
    $cuttingOrderIds[] = $cuttingOrderId;
    $cuttingRepository->confirm($cuttingOrderId);
    $cut = $cuttingRepository->findWithDetails($cuttingOrderId);
    $cuttingRepository->complete($cuttingOrderId, [(int) $cut['items'][0]['id'] => (float) $quantity]);

    return [
        'production_order_id' => $productionOrderId,
        'production_item_id' => $productionItemId,
        'cutting_order_id' => $cuttingOrderId,
    ];
};

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para calidad.');

    $seamsterId = $seamsterRepository->create([
        'name' => 'Costurera Calidad ' . $suffix,
        'document_number' => 'CI-QC-' . $suffix,
        'phone' => '0981000000',
        'email' => 'qc-' . $suffix . '@example.test',
        'address' => 'Taller',
        'status' => 'active',
        'notes' => '',
    ]);
    $seamsterIds[] = $seamsterId;

    $plainFlow = $makeFlow('plain', false, 4);
    $availableSewingSource = $sewingRepository->availableItemsFromCuttingOrder($plainFlow['cutting_order_id']);
    $sewingOrderId = $sewingRepository->createFromCuttingOrder(
        $plainFlow['cutting_order_id'],
        ['seamster_id' => $seamsterId, 'sewing_number' => 'CONF-QC-' . $suffix],
        [['cutting_order_item_id' => (int) $availableSewingSource[0]['id'], 'quantity_assigned' => 4]]
    );
    $sewingOrderIds[] = $sewingOrderId;
    $sewingRepository->confirm($sewingOrderId);
    $sewing = $sewingRepository->findWithDetails($sewingOrderId);
    $sewingItemId = (int) $sewing['items'][0]['id'];
    $sewingRepository->registerProgress($sewingOrderId, [[
        'sewing_order_item_id' => $sewingItemId,
        'quantity_completed' => 3,
        'quantity_rejected' => 1,
        'notes' => 'Cierre para calidad',
    ]], '2026-06-05');

    $availableQuality = $qualityRepository->availableItemsFromSewingOrder($sewingOrderId);
    $assert(count($availableQuality) === 1 && (float) $availableQuality[0]['quantity_available_to_quality'] === 3.0, 'Calidad solo debe tomar cantidades confeccionadas, no rechazadas.');

    $draftSewingFailed = false;
    $draftFlow = $makeFlow('draft', false, 1);
    try {
        $qualityRepository->createFromSewingOrder(999999, [], []);
    } catch (InvalidArgumentException) {
        $draftSewingFailed = true;
    }
    $assert($draftSewingFailed, 'Debe rechazar origen de confección inexistente/no completado.');

    $qcApprovedId = $qualityRepository->createFromSewingOrder(
        $sewingOrderId,
        ['qc_number' => 'QC-APP-' . $suffix, 'notes' => 'Control parcial aprobado'],
        [['sewing_order_item_id' => (int) $availableQuality[0]['id'], 'quantity_received' => 2]]
    );
    $qualityCheckIds[] = $qcApprovedId;

    $duplicateOverFailed = false;
    try {
        $qualityRepository->createFromSewingOrder(
            $sewingOrderId,
            ['qc_number' => 'QC-OVER-' . $suffix],
            [['sewing_order_item_id' => (int) $availableQuality[0]['id'], 'quantity_received' => 2]]
        );
    } catch (InvalidArgumentException) {
        $duplicateOverFailed = true;
    }
    $assert($duplicateOverFailed, 'No debe duplicar controles por encima del saldo pendiente de calidad.');

    $qcApproved = $qualityRepository->findWithDetails($qcApprovedId);
    $qcApprovedItemId = (int) $qcApproved['items'][0]['id'];
    $overResultFailed = false;
    try {
        $qualityRepository->registerResults($qcApprovedId, [[
            'quality_control_check_item_id' => $qcApprovedItemId,
            'quantity_approved' => 3,
            'quantity_rejected' => 0,
            'quantity_rework' => 0,
        ]]);
    } catch (InvalidArgumentException) {
        $overResultFailed = true;
    }
    $assert($overResultFailed, 'Los resultados no pueden superar quantity_received.');

    $qualityRepository->registerResults($qcApprovedId, [[
        'quality_control_check_item_id' => $qcApprovedItemId,
        'quantity_approved' => 2,
        'quantity_rejected' => 0,
        'quantity_rework' => 0,
        'model_ok' => 1,
        'size_ok' => 1,
        'quantity_ok' => 1,
        'sewing_ok' => 1,
        'finishing_ok' => 1,
        'notes' => 'OK',
    ]]);
    $qualityRepository->confirm($qcApprovedId);
    $assert((string) ($qualityRepository->find($qcApprovedId)['status'] ?? '') === 'approved', 'Debe aprobar control con todo aprobado.');
    $qualityRepository->close($qcApprovedId);
    $assert((string) ($qualityRepository->find($qcApprovedId)['status'] ?? '') === 'closed', 'Debe cerrar control aprobado.');

    $remaining = $qualityRepository->availableItemsFromSewingOrder($sewingOrderId);
    $assert(count($remaining) === 1 && (float) $remaining[0]['quantity_available_to_quality'] === 1.0, 'Debe conservar saldo pendiente para otro control.');
    $qcReworkId = $qualityRepository->createFromSewingOrder(
        $sewingOrderId,
        ['qc_number' => 'QC-REP-' . $suffix],
        [['sewing_order_item_id' => (int) $remaining[0]['id'], 'quantity_received' => 1]]
    );
    $qualityCheckIds[] = $qcReworkId;
    $qcRework = $qualityRepository->findWithDetails($qcReworkId);
    $qualityRepository->registerResults($qcReworkId, [[
        'quality_control_check_item_id' => (int) $qcRework['items'][0]['id'],
        'quantity_approved' => 0,
        'quantity_rejected' => 0,
        'quantity_rework' => 1,
        'model_ok' => 1,
        'size_ok' => 1,
        'quantity_ok' => 1,
        'sewing_ok' => 0,
        'finishing_ok' => 0,
        'notes' => 'Costura torcida',
    ]]);
    $qualityRepository->confirm($qcReworkId);
    $confirmedRework = $qualityRepository->findWithDetails($qcReworkId);
    $assert((string) ($confirmedRework['status'] ?? '') === 'rework_required', 'Debe marcar control con reproceso requerido.');
    $assert(count($confirmedRework['rework_orders']) === 1, 'Debe crear orden de reproceso cuando quantity_rework > 0.');
    $assert((string) ($productionRepository->find($plainFlow['production_order_id'])['production_stage'] ?? '') === 'rework_required', 'Reproceso debe mover producción a rework_required.');
    $reworkId = (int) $confirmedRework['rework_orders'][0]['id'];
    $reworkRepository->complete($reworkId, 'Reproceso terminado manualmente');
    $reworkRepository->close($reworkId);
    $qualityRepository->close($qcReworkId);
    $assert((string) ($qualityRepository->find($qcReworkId)['status'] ?? '') === 'closed', 'Debe permitir cerrar calidad cuando reproceso queda cerrado.');

    $supplierId = $supplierRepository->create([
        'name' => 'Proveedor Calidad ' . $suffix,
        'ruc' => '902' . random_int(100000, 999999),
        'contact_name' => 'Contacto',
        'phone' => '',
        'email' => '',
        'address' => '',
        'payment_terms' => '',
        'delivery_terms' => '',
        'status' => 'active',
        'notes' => '',
    ]);

    $externalFlow = $makeFlow('ext', true, 3);
    $eligibleExternal = $externalRepository->eligibleItemsFromCuttingOrder($externalFlow['cutting_order_id']);
    $externalWorkOrderId = $externalRepository->createFromCuttingOrder(
        $externalFlow['cutting_order_id'],
        ['supplier_id' => $supplierId, 'external_work_number' => 'EXT-QC-' . $suffix, 'work_type' => 'embroidery', 'next_stage' => 'quality_control'],
        [['cutting_order_item_id' => (int) $eligibleExternal[0]['id'], 'quantity_sent' => 3, 'work_details' => 'Logo']]
    );
    $externalWorkOrderIds[] = $externalWorkOrderId;
    $externalRepository->send($externalWorkOrderId);
    $externalItemId = (int) $externalRepository->findWithDetails($externalWorkOrderId)['items'][0]['id'];
    $receiptId = $externalRepository->createReceipt(
        $externalWorkOrderId,
        ['receipt_number' => 'REC-QC-FLOW-' . $suffix, 'next_stage' => 'quality_control'],
        [[
            'external_work_order_item_id' => $externalItemId,
            'quantity_received' => 3,
            'quantity_accepted' => 2,
            'quantity_rejected' => 1,
            'next_stage' => 'quality_control',
            'quality_notes' => 'Una pieza rechazada por proveedor',
        ]]
    );

    $unconfirmedReceiptFailed = false;
    try {
        $qualityRepository->createFromExternalReceipt($receiptId, ['qc_number' => 'QC-UNCONF-' . $suffix], []);
    } catch (InvalidArgumentException) {
        $unconfirmedReceiptFailed = true;
    }
    $assert($unconfirmedReceiptFailed, 'No debe crear calidad desde retorno externo no confirmado.');

    $externalRepository->confirmReceipt($receiptId);
    $availableExternalQuality = $qualityRepository->availableItemsFromExternalReceipt($receiptId);
    $assert(count($availableExternalQuality) === 1 && (float) $availableExternalQuality[0]['quantity_available_to_quality'] === 2.0, 'Calidad externa solo debe tomar cantidades aceptadas.');
    $qcExternalId = $qualityRepository->createFromExternalReceipt(
        $receiptId,
        ['qc_number' => 'QC-EXT-' . $suffix],
        [['external_work_receipt_item_id' => (int) $availableExternalQuality[0]['id'], 'quantity_received' => 2]]
    );
    $qualityCheckIds[] = $qcExternalId;
    $qcExternal = $qualityRepository->findWithDetails($qcExternalId);
    $qualityRepository->registerResults($qcExternalId, [[
        'quality_control_check_item_id' => (int) $qcExternal['items'][0]['id'],
        'quantity_approved' => 1,
        'quantity_rejected' => 1,
        'quantity_rework' => 0,
        'model_ok' => 1,
        'size_ok' => 1,
        'quantity_ok' => 1,
        'sewing_ok' => 1,
        'finishing_ok' => 0,
    ]]);
    $qualityRepository->confirm($qcExternalId);
    $assert((string) ($qualityRepository->find($qcExternalId)['status'] ?? '') === 'partially_approved', 'Mezcla de aprobado y rechazado debe quedar partially_approved.');

    $auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE document_type = 'quality_control_checks' AND document_id = " . $qcApprovedId)->fetchColumn();
    $assert($auditCount >= 3, 'audit_log debe registrar acciones principales de calidad.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['POST', '/sewing-orders/' . $sewingOrderId . '/quality-control'],
        ['GET', '/sewing-orders/' . $sewingOrderId . '/quality-control/create'],
        ['POST', '/external-work-orders/' . $externalWorkOrderId . '/receipts/' . $receiptId . '/quality-control'],
        ['GET', '/external-work-orders/' . $externalWorkOrderId . '/receipts/' . $receiptId . '/quality-control/create'],
        ['POST', '/quality-control/' . $qcExternalId . '/results'],
        ['POST', '/quality-control/' . $qcExternalId . '/confirm'],
        ['POST', '/quality-control/' . $qcExternalId . '/close'],
        ['POST', '/quality-reworks/' . $reworkId . '/complete'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/quality-control/' . $qcExternalId)), 'Consulta debe poder ver calidad.');
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/quality-reworks/' . $reworkId)), 'Consulta debe poder ver reprocesos.');
} finally {
    Auth::logout();
    foreach ($qualityCheckIds as $qualityCheckId) {
        $pdo->exec('DELETE FROM quality_control_checks WHERE id = ' . (int) $qualityCheckId);
    }
    if ($cuttingOrderIds !== []) {
        $cuttingIds = implode(',', array_map('intval', $cuttingOrderIds));
        $pdo->exec('DELETE FROM quality_control_checks WHERE production_order_id IN (' . implode(',', array_map('intval', $productionOrderIds ?: [0])) . ')');
        $pdo->exec('DELETE FROM sewing_orders WHERE cutting_order_id IN (' . $cuttingIds . ')');
        $pdo->exec('DELETE FROM external_work_orders WHERE cutting_order_id IN (' . $cuttingIds . ')');
    }
    foreach ($externalWorkOrderIds as $externalWorkOrderId) {
        $pdo->exec('DELETE FROM external_work_orders WHERE id = ' . (int) $externalWorkOrderId);
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
    if (is_int($supplierId)) {
        $pdo->exec('DELETE FROM suppliers WHERE id = ' . $supplierId);
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
