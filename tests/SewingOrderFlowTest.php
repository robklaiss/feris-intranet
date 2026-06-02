<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\CuttingOrderRepository;
use App\Repositories\ExternalWorkOrderRepository;
use App\Repositories\ProductionOrderRepository;
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
foreach (['seamsters', 'sewing_orders', 'sewing_order_items', 'sewing_progress_entries'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 015 debe crear la tabla ' . $table . '.');
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

$suffix = bin2hex(random_bytes(4));
$clientIds = [];
$contractIds = [];
$customerOrderIds = [];
$productionOrderIds = [];
$materialIds = [];
$cuttingOrderIds = [];
$externalWorkOrderIds = [];
$supplierId = null;
$seamsterIds = [];
$sewingOrderIds = [];

$makeFlow = static function (
    string $tag,
    bool $requiresExternal,
    int $quantity
) use (
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
        'name' => 'Cliente Confeccion ' . $tag . ' ' . $suffix,
        'tax_id' => '807' . random_int(100000, 999999) . '-1',
        'addresses' => 'Central',
        'contacts' => 'Produccion',
        'status' => 'active',
    ]);
    $clientIds[] = $clientId;

    $contractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-SEW-' . $tag . '-' . $suffix,
            'reference_number' => 'REF-SEW-' . $tag . '-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80712345-6',
            'status' => 'confirmed',
            'notes' => '',
            'total_amount' => $quantity * 10000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [[
            'product_name' => 'Remera confeccion ' . $tag,
            'unit_measure' => 'unidad',
            'quantity' => $quantity,
            'unit_price' => 10000,
            'total_item' => $quantity * 10000,
            'notes' => '',
        ]]
    );
    $contractIds[] = $contractId;

    $specId = $specRepository->create($contractId, [
        'item_code' => 'SEW-' . strtoupper($tag) . '-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera para confección ' . $tag,
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

    $customerOrderId = $customerOrderRepository->create(
        [
            'contract_id' => $contractId,
            'po_number' => 'OC-SEW-' . $tag . '-' . $suffix,
            'po_date' => '2026-06-01',
            'received_date' => '2026-06-01',
            'dependency_id' => '',
            'billing_contact_id' => '',
            'notes' => '',
            'attachment_path' => '',
        ],
        [['contract_item_spec_id' => $specId, 'quantity' => $quantity]]
    );
    $customerOrderIds[] = $customerOrderId;
    $customerOrderRepository->confirm($customerOrderId);

    $customerItemId = (int) $pdo->query('SELECT id FROM customer_purchase_order_items WHERE customer_purchase_order_id = ' . $customerOrderId . ' LIMIT 1')->fetchColumn();
    $productionOrderId = $productionRepository->create(
        [
            'customer_purchase_order_id' => $customerOrderId,
            'production_number' => 'OP-SEW-' . $tag . '-' . $suffix,
            'planned_start_date' => '',
            'planned_end_date' => '',
            'notes' => '',
        ],
        [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => $quantity]]
    );
    $productionOrderIds[] = $productionOrderId;

    $productionItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();
    $productionRepository->confirm($productionOrderId);

    $materialId = $inventoryRepository->create([
        'internal_code' => 'MAT-SEW-' . strtoupper($tag) . '-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela para confección ' . $tag,
        'unit' => 'unidad',
        'quantity_available' => $quantity,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_item_code' => 'SEW-' . strtoupper($tag) . '-' . $suffix,
        'status' => 'active',
    ]);
    $materialIds[] = $materialId;

    $stockCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-SEW-' . $tag . '-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $materialId]]
    );
    $stockCheckRepository->reserve($stockCheckId);

    $cuttingOrderId = $cuttingRepository->createFromProductionOrder(
        $productionOrderId,
        ['cutting_number' => 'CORTE-SEW-' . $tag . '-' . $suffix, 'planned_date' => '2026-06-03', 'cut_by' => 'Mesa 1', 'notes' => 'Corte para confección'],
        [['production_order_item_id' => $productionItemId, 'quantity_to_cut' => $quantity, 'notes' => 'Corte completo']]
    );
    $cuttingOrderIds[] = $cuttingOrderId;

    return [
        'production_order_id' => $productionOrderId,
        'production_item_id' => $productionItemId,
        'cutting_order_id' => $cuttingOrderId,
    ];
};

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para confección.');

    $seamsterId = $seamsterRepository->create([
        'name' => 'Costurera Activa ' . $suffix,
        'document_number' => 'CI-' . $suffix,
        'phone' => '0981000000',
        'email' => 'costura-' . $suffix . '@example.test',
        'address' => 'Taller',
        'status' => 'active',
        'notes' => 'Disponible',
    ]);
    $seamsterIds[] = $seamsterId;
    $assert((string) ($seamsterRepository->find($seamsterId)['status'] ?? '') === 'active', 'Se debe crear costurero activo.');

    $inactiveSeamsterId = $seamsterRepository->create([
        'name' => 'Costurera Inactiva ' . $suffix,
        'document_number' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'status' => 'inactive',
        'notes' => '',
    ]);
    $seamsterIds[] = $inactiveSeamsterId;

    $plainFlow = $makeFlow('plain', false, 4);

    $draftSewingFailed = false;
    try {
        $sewingRepository->createFromCuttingOrder(
            $plainFlow['cutting_order_id'],
            ['seamster_id' => $seamsterId],
            []
        );
    } catch (InvalidArgumentException) {
        $draftSewingFailed = true;
    }
    $assert($draftSewingFailed, 'No debe permitir confección desde corte draft.');

    foreach (['in_progress', 'cancelled'] as $invalidStatus) {
        $pdo->prepare(
            'INSERT INTO cutting_orders (production_order_id, cutting_number, status, created_by, updated_at)
             VALUES (:production_order_id, :cutting_number, :status, :created_by, CURRENT_TIMESTAMP)'
        )->execute([
            'production_order_id' => $plainFlow['production_order_id'],
            'cutting_number' => 'CORTE-SEW-' . strtoupper($invalidStatus) . '-' . $suffix,
            'status' => $invalidStatus,
            'created_by' => Auth::id(),
        ]);
        $invalidCuttingOrderId = (int) $pdo->lastInsertId();
        $cuttingOrderIds[] = $invalidCuttingOrderId;

        $invalidStatusFailed = false;
        try {
            $sewingRepository->createFromCuttingOrder($invalidCuttingOrderId, ['seamster_id' => $seamsterId], []);
        } catch (InvalidArgumentException) {
            $invalidStatusFailed = true;
        }
        $assert($invalidStatusFailed, 'No debe permitir confección desde corte ' . $invalidStatus . '.');
    }

    $cuttingRepository->confirm($plainFlow['cutting_order_id']);
    $plainCut = $cuttingRepository->findWithDetails($plainFlow['cutting_order_id']);
    $cuttingRepository->complete($plainFlow['cutting_order_id'], [(int) $plainCut['items'][0]['id'] => 4.0]);

    $inactiveFailed = false;
    try {
        $sewingRepository->createFromCuttingOrder($plainFlow['cutting_order_id'], ['seamster_id' => $inactiveSeamsterId], []);
    } catch (InvalidArgumentException) {
        $inactiveFailed = true;
    }
    $assert($inactiveFailed, 'No debe permitir usar costurero inactive.');

    $availablePlain = $sewingRepository->availableItemsFromCuttingOrder($plainFlow['cutting_order_id']);
    $assert(count($availablePlain) === 1 && (float) $availablePlain[0]['quantity_available_to_sew'] === 4.0, 'Debe listar saldo de corte sin externo para confección.');

    $plainSewingOrderId = $sewingRepository->createFromCuttingOrder(
        $plainFlow['cutting_order_id'],
        [
            'seamster_id' => $seamsterId,
            'sewing_number' => 'CONF-PLAIN-' . $suffix,
            'assigned_at' => '2026-06-04',
            'expected_completion_date' => '2026-06-10',
            'notes' => 'Asignación parcial',
        ],
        [['cutting_order_item_id' => (int) $availablePlain[0]['id'], 'quantity_assigned' => 3, 'notes' => 'Primera tanda']]
    );
    $sewingOrderIds[] = $plainSewingOrderId;

    $duplicateOverFailed = false;
    try {
        $sewingRepository->createFromCuttingOrder(
            $plainFlow['cutting_order_id'],
            ['seamster_id' => $seamsterId, 'sewing_number' => 'CONF-PLAIN-OVER-' . $suffix],
            [['cutting_order_item_id' => (int) $availablePlain[0]['id'], 'quantity_assigned' => 2]]
        );
    } catch (InvalidArgumentException) {
        $duplicateOverFailed = true;
    }
    $assert($duplicateOverFailed, 'No debe duplicar asignaciones por encima del saldo pendiente.');

    $sewingRepository->confirm($plainSewingOrderId);
    $confirmedPlain = $sewingRepository->findWithDetails($plainSewingOrderId);
    $assert((string) ($confirmedPlain['status'] ?? '') === 'confirmed', 'Debe confirmar orden de confección.');
    $assert((string) ($productionRepository->find($plainFlow['production_order_id'])['production_stage'] ?? '') === 'in_sewing', 'Confirmar confección debe dejar producción en confección.');

    $plainItemId = (int) $confirmedPlain['items'][0]['id'];
    $sewingRepository->registerProgress($plainSewingOrderId, [[
        'sewing_order_item_id' => $plainItemId,
        'quantity_completed' => 1,
        'quantity_rejected' => 0,
        'notes' => 'Avance parcial',
    ]], '2026-06-05');
    $partialPlain = $sewingRepository->findWithDetails($plainSewingOrderId);
    $assert((string) ($partialPlain['status'] ?? '') === 'partially_completed', 'Avance parcial debe marcar partially_completed.');
    $assert((float) ($partialPlain['items'][0]['quantity_completed'] ?? 0) === 1.0, 'Debe acumular cantidad confeccionada.');
    $assert((float) ($partialPlain['items'][0]['quantity_pending'] ?? 0) === 2.0, 'Debe recalcular pendiente.');

    $overProgressFailed = false;
    try {
        $sewingRepository->registerProgress($plainSewingOrderId, [[
            'sewing_order_item_id' => $plainItemId,
            'quantity_completed' => 3,
            'quantity_rejected' => 0,
        ]], '2026-06-06');
    } catch (InvalidArgumentException) {
        $overProgressFailed = true;
    }
    $assert($overProgressFailed, 'quantity_completed + quantity_rejected no debe superar quantity_assigned.');

    $cancelWithProgressFailed = false;
    try {
        $sewingRepository->cancelDraft($plainSewingOrderId);
    } catch (InvalidArgumentException) {
        $cancelWithProgressFailed = true;
    }
    $assert($cancelWithProgressFailed, 'No debe cancelar orden con avances registrados.');

    $sewingRepository->registerProgress($plainSewingOrderId, [[
        'sewing_order_item_id' => $plainItemId,
        'quantity_completed' => 1,
        'quantity_rejected' => 1,
        'notes' => 'Cierre con rechazo',
    ]], '2026-06-06');
    $completedPlain = $sewingRepository->findWithDetails($plainSewingOrderId);
    $assert((string) ($completedPlain['status'] ?? '') === 'completed', 'Debe marcar completed cuando todo queda confeccionado o rechazado.');
    $assert((float) ($completedPlain['items'][0]['quantity_rejected'] ?? 0) === 1.0, 'Debe acumular rechazados.');
    $assert((string) ($productionRepository->find($plainFlow['production_order_id'])['production_stage'] ?? '') === 'quality_control', 'Orden completada debe quedar lista para control de calidad.');
    $sewingRepository->close($plainSewingOrderId);
    $assert((string) ($sewingRepository->find($plainSewingOrderId)['status'] ?? '') === 'closed', 'Debe cerrar orden de confección completada.');

    $externalFlow = $makeFlow('ext', true, 4);
    $cuttingRepository->confirm($externalFlow['cutting_order_id']);
    $externalCut = $cuttingRepository->findWithDetails($externalFlow['cutting_order_id']);
    $cuttingRepository->complete($externalFlow['cutting_order_id'], [(int) $externalCut['items'][0]['id'] => 4.0]);
    $assert($sewingRepository->availableItemsFromCuttingOrder($externalFlow['cutting_order_id']) === [], 'Un ítem que requiere externo no debe pasar directo a confección desde corte.');

    $supplierId = $supplierRepository->create([
        'name' => 'Proveedor Externo Confeccion ' . $suffix,
        'ruc' => '901' . random_int(100000, 999999),
        'contact_name' => 'Contacto',
        'phone' => '',
        'email' => '',
        'address' => '',
        'payment_terms' => '',
        'delivery_terms' => '',
        'status' => 'active',
        'notes' => '',
    ]);

    $externalEligible = (new ExternalWorkOrderRepository())->eligibleItemsFromCuttingOrder($externalFlow['cutting_order_id']);
    $externalWorkOrderId = $externalRepository->createFromCuttingOrder(
        $externalFlow['cutting_order_id'],
        [
            'supplier_id' => $supplierId,
            'external_work_number' => 'EXT-SEW-' . $suffix,
            'work_type' => 'embroidery',
            'next_stage' => 'sewing',
            'notes' => 'Bordado antes de confección',
        ],
        [['cutting_order_item_id' => (int) $externalEligible[0]['id'], 'quantity_sent' => 4, 'work_details' => 'Logo', 'notes' => 'Bordar']]
    );
    $externalWorkOrderIds[] = $externalWorkOrderId;
    $externalRepository->send($externalWorkOrderId);
    $sentExternal = $externalRepository->findWithDetails($externalWorkOrderId);
    $assert((string) ($sentExternal['status'] ?? '') === 'sent', 'El trabajo externo debe quedar enviado antes del retorno.');
    $assert($sewingRepository->search(['external_work_order_id' => $externalWorkOrderId]) === [], 'No debe existir confección desde trabajo externo sent sin retorno.');

    $externalItemId = (int) $sentExternal['items'][0]['id'];
    $receiptId = $externalRepository->createReceipt(
        $externalWorkOrderId,
        ['receipt_number' => 'REC-SEW-' . $suffix, 'next_stage' => 'sewing', 'notes' => 'Retorno aceptado para confección'],
        [[
            'external_work_order_item_id' => $externalItemId,
            'quantity_received' => 3,
            'quantity_accepted' => 2,
            'quantity_rejected' => 1,
            'next_stage' => 'sewing',
            'quality_notes' => 'Una pieza rechazada',
        ]]
    );
    $externalRepository->confirmReceipt($receiptId);
    $availableExternal = $sewingRepository->availableItemsFromExternalReceipt($receiptId);
    $assert(count($availableExternal) === 1 && (float) $availableExternal[0]['quantity_available_to_sew'] === 2.0, 'Solo cantidades aceptadas con next_stage sewing deben quedar disponibles.');

    $assignRejectedFailed = false;
    try {
        $sewingRepository->createFromExternalReceipt(
            $receiptId,
            ['seamster_id' => $seamsterId, 'sewing_number' => 'CONF-EXT-OVER-' . $suffix],
            [['external_work_receipt_item_id' => (int) $availableExternal[0]['id'], 'quantity_assigned' => 3]]
        );
    } catch (InvalidArgumentException) {
        $assignRejectedFailed = true;
    }
    $assert($assignRejectedFailed, 'No debe asignar cantidades rechazadas por proveedor externo.');

    $externalSewingOrderId = $sewingRepository->createFromExternalReceipt(
        $receiptId,
        ['seamster_id' => $seamsterId, 'sewing_number' => 'CONF-EXT-' . $suffix, 'assigned_at' => '2026-06-07'],
        [['external_work_receipt_item_id' => (int) $availableExternal[0]['id'], 'quantity_assigned' => 2]]
    );
    $sewingOrderIds[] = $externalSewingOrderId;
    $externalSewing = $sewingRepository->findWithDetails($externalSewingOrderId);
    $assert((int) ($externalSewing['external_work_order_id'] ?? 0) === $externalWorkOrderId, 'Debe trazar trabajo externo en orden de confección.');
    $assert((int) ($externalSewing['external_work_receipt_id'] ?? 0) === $receiptId, 'Debe trazar recepción externa en orden de confección.');

    $receiptQcId = $externalRepository->createReceipt(
        $externalWorkOrderId,
        ['receipt_number' => 'REC-QC-' . $suffix, 'next_stage' => 'quality_control', 'notes' => 'Retorno directo a calidad'],
        [[
            'external_work_order_item_id' => $externalItemId,
            'quantity_received' => 1,
            'quantity_accepted' => 1,
            'quantity_rejected' => 0,
            'next_stage' => 'quality_control',
        ]]
    );
    $externalRepository->confirmReceipt($receiptQcId);
    $qcReceiptFailed = false;
    try {
        $sewingRepository->createFromExternalReceipt($receiptQcId, ['seamster_id' => $seamsterId], []);
    } catch (InvalidArgumentException) {
        $qcReceiptFailed = true;
    }
    $assert($qcReceiptFailed, 'No debe crear confección desde retorno externo con next_stage distinto de sewing.');

    $auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE document_type = 'sewing_orders' AND document_id = " . $plainSewingOrderId)->fetchColumn();
    $assert($auditCount >= 4, 'audit_log debe registrar acciones principales de confección.');
    $seamsterAuditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE document_type = 'seamsters' AND document_id = " . $seamsterId)->fetchColumn();
    $assert($seamsterAuditCount >= 1, 'audit_log debe registrar creación de costurero.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['POST', '/seamsters'],
        ['GET', '/seamsters/create'],
        ['POST', '/cutting-orders/' . $plainFlow['cutting_order_id'] . '/sewing-orders'],
        ['GET', '/cutting-orders/' . $plainFlow['cutting_order_id'] . '/sewing-orders/create'],
        ['POST', '/external-work-orders/' . $externalWorkOrderId . '/receipts/' . $receiptId . '/sewing-orders'],
        ['GET', '/external-work-orders/' . $externalWorkOrderId . '/receipts/' . $receiptId . '/sewing-orders/create'],
        ['POST', '/sewing-orders/' . $plainSewingOrderId . '/confirm'],
        ['POST', '/sewing-orders/' . $plainSewingOrderId . '/progress'],
        ['POST', '/sewing-orders/' . $plainSewingOrderId . '/close'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/sewing-orders/' . $plainSewingOrderId)), 'Consulta debe poder ver confección.');
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/seamsters/' . $seamsterId)), 'Consulta debe poder ver costureros.');
} finally {
    Auth::logout();
    if ($cuttingOrderIds !== []) {
        $cuttingIds = implode(',', array_map('intval', $cuttingOrderIds));
        $pdo->exec('DELETE FROM sewing_orders WHERE cutting_order_id IN (' . $cuttingIds . ')');
        $pdo->exec('DELETE FROM external_work_orders WHERE cutting_order_id IN (' . $cuttingIds . ')');
    }
    foreach ($sewingOrderIds as $sewingOrderId) {
        $pdo->exec('DELETE FROM sewing_orders WHERE id = ' . (int) $sewingOrderId);
    }
    foreach ($externalWorkOrderIds as $externalWorkOrderId) {
        $pdo->exec('DELETE FROM external_work_orders WHERE id = ' . (int) $externalWorkOrderId);
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
