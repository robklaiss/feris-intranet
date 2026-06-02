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
foreach (['external_work_orders', 'external_work_order_items', 'external_work_receipts', 'external_work_receipt_items'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 014 debe crear la tabla ' . $table . '.');
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

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$contractId = null;
$customerOrderId = null;
$productionOrderId = null;
$materialIds = [];
$cuttingOrderId = null;
$invalidCuttingOrderIds = [];
$externalWorkOrderId = null;
$supplierId = null;

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para trabajo externo.');

    $clientId = $clientRepository->create([
        'name' => 'Cliente Externo ' . $suffix,
        'tax_id' => '806' . random_int(100000, 999999) . '-1',
        'addresses' => 'Central',
        'contacts' => 'Produccion',
        'status' => 'active',
    ]);

    $contractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-EXT-' . $suffix,
            'reference_number' => 'REF-EXT-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80612345-6',
            'status' => 'confirmed',
            'notes' => '',
            'total_amount' => 100000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [
            [
                'product_name' => 'Remera bordada',
                'unit_measure' => 'unidad',
                'quantity' => 4,
                'unit_price' => 10000,
                'total_item' => 40000,
                'notes' => '',
            ],
            [
                'product_name' => 'Remera lisa',
                'unit_measure' => 'unidad',
                'quantity' => 2,
                'unit_price' => 10000,
                'total_item' => 20000,
                'notes' => '',
            ],
        ]
    );

    $externalSpecId = $specRepository->create($contractId, [
        'item_code' => 'EXT-BOR-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera con bordado',
        'size' => 'M',
        'color' => 'Azul',
        'fabric' => 'Algodón',
        'measurements' => 'Molde M',
        'has_embroidery' => 1,
        'has_screen_printing' => 0,
        'quantity' => 4,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($contractId, $externalSpecId);

    $plainSpecId = $specRepository->create($contractId, [
        'item_code' => 'EXT-LIS-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera lisa',
        'size' => 'M',
        'color' => 'Blanco',
        'fabric' => 'Algodón',
        'measurements' => 'Molde M',
        'has_embroidery' => 0,
        'has_screen_printing' => 0,
        'quantity' => 2,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($contractId, $plainSpecId);

    $customerOrderId = $customerOrderRepository->create(
        [
            'contract_id' => $contractId,
            'po_number' => 'OC-EXT-' . $suffix,
            'po_date' => '2026-06-01',
            'received_date' => '2026-06-01',
            'dependency_id' => '',
            'billing_contact_id' => '',
            'notes' => '',
            'attachment_path' => '',
        ],
        [
            ['contract_item_spec_id' => $externalSpecId, 'quantity' => 4],
            ['contract_item_spec_id' => $plainSpecId, 'quantity' => 2],
        ]
    );
    $customerOrderRepository->confirm($customerOrderId);

    $customerItems = $pdo->query('SELECT id, item_code FROM customer_purchase_order_items WHERE customer_purchase_order_id = ' . $customerOrderId . ' ORDER BY id')->fetchAll();
    $productionOrderId = $productionRepository->create(
        [
            'customer_purchase_order_id' => $customerOrderId,
            'production_number' => 'OP-EXT-' . $suffix,
            'planned_start_date' => '',
            'planned_end_date' => '',
            'notes' => '',
        ],
        [
            ['customer_purchase_order_item_id' => (int) $customerItems[0]['id'], 'quantity' => 4],
            ['customer_purchase_order_item_id' => (int) $customerItems[1]['id'], 'quantity' => 2],
        ]
    );
    $productionItems = $pdo->query('SELECT id, item_code FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' ORDER BY id')->fetchAll();

    $productionRepository->confirm($productionOrderId);
    foreach ($productionItems as $productionItem) {
        $materialIds[] = $inventoryRepository->create([
            'internal_code' => 'MAT-EXT-' . $productionItem['item_code'],
            'material_type' => 'Remera',
            'description' => 'Tela para ' . $productionItem['item_code'],
            'unit' => 'unidad',
            'quantity_available' => $productionItem['item_code'] === $customerItems[0]['item_code'] ? 4 : 2,
            'quantity_reserved' => 0,
            'minimum_stock' => 0,
            'related_item_code' => $productionItem['item_code'],
            'status' => 'active',
        ]);
    }

    $stockCheckItems = [];
    foreach ($productionItems as $index => $productionItem) {
        $stockCheckItems[] = [
            'production_order_item_id' => (int) $productionItem['id'],
            'raw_material_inventory_id' => $materialIds[$index],
        ];
    }
    $stockCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-EXT-' . $suffix],
        $stockCheckItems
    );
    $stockCheckRepository->reserve($stockCheckId);

    $cuttingOrderId = $cuttingRepository->createFromProductionOrder(
        $productionOrderId,
        ['cutting_number' => 'CORTE-EXT-' . $suffix, 'planned_date' => '2026-06-03', 'cut_by' => 'Mesa 1', 'notes' => 'Corte externo'],
        [
            ['production_order_item_id' => (int) $productionItems[0]['id'], 'quantity_to_cut' => 4, 'notes' => 'Bordado'],
            ['production_order_item_id' => (int) $productionItems[1]['id'], 'quantity_to_cut' => 2, 'notes' => 'Liso'],
        ]
    );

    $draftExternalFailed = false;
    try {
        $externalRepository->createFromCuttingOrder($cuttingOrderId, ['work_type' => 'both'], []);
    } catch (InvalidArgumentException) {
        $draftExternalFailed = true;
    }
    $assert($draftExternalFailed, 'No debe crear trabajo externo desde corte draft.');

    $cuttingRepository->confirm($cuttingOrderId);
    $confirmedExternalFailed = false;
    try {
        $externalRepository->createFromCuttingOrder($cuttingOrderId, ['work_type' => 'both'], []);
    } catch (InvalidArgumentException) {
        $confirmedExternalFailed = true;
    }
    $assert($confirmedExternalFailed, 'No debe crear trabajo externo desde corte in_progress/confirmed.');

    foreach (['in_progress', 'cancelled'] as $invalidStatus) {
        $pdo->prepare(
            'INSERT INTO cutting_orders (production_order_id, cutting_number, status, created_by, updated_at)
             VALUES (:production_order_id, :cutting_number, :status, :created_by, CURRENT_TIMESTAMP)'
        )->execute([
            'production_order_id' => $productionOrderId,
            'cutting_number' => 'CORTE-' . strtoupper($invalidStatus) . '-' . $suffix,
            'status' => $invalidStatus,
            'created_by' => Auth::id(),
        ]);
        $invalidCuttingOrderId = (int) $pdo->lastInsertId();
        $invalidCuttingOrderIds[] = $invalidCuttingOrderId;
        $invalidStatusFailed = false;
        try {
            $externalRepository->createFromCuttingOrder($invalidCuttingOrderId, ['work_type' => 'both'], []);
        } catch (InvalidArgumentException) {
            $invalidStatusFailed = true;
        }
        $assert($invalidStatusFailed, 'No debe crear trabajo externo desde corte ' . $invalidStatus . '.');
    }

    $completedCut = $cuttingRepository->findWithDetails($cuttingOrderId);
    $cuttingRepository->complete($cuttingOrderId, [
        (int) $completedCut['items'][0]['id'] => 4.0,
        (int) $completedCut['items'][1]['id'] => 2.0,
    ]);
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'waiting_external_work', 'Corte con ítem externo debe dejar producción esperando externo.');

    $eligible = $externalRepository->eligibleItemsFromCuttingOrder($cuttingOrderId);
    $assert(count($eligible) === 1, 'Solo debe listar ítems con bordado/serigrafía como elegibles por defecto.');
    $assert((string) $eligible[0]['item_code'] === (string) $productionItems[0]['item_code'], 'El ítem elegible debe ser el marcado con bordado.');

    $allCandidates = $externalRepository->eligibleItemsFromCuttingOrder($cuttingOrderId, true);
    $plainCandidate = array_values(array_filter($allCandidates, static fn (array $item): bool => (string) $item['item_code'] === (string) $productionItems[1]['item_code']))[0];
    $overrideWithoutNotesFailed = false;
    try {
        $externalRepository->createFromCuttingOrder(
            $cuttingOrderId,
            ['work_type' => 'other'],
            [['cutting_order_item_id' => (int) $plainCandidate['id'], 'quantity_sent' => 1, 'manual_override' => 1, 'notes' => '']]
        );
    } catch (InvalidArgumentException) {
        $overrideWithoutNotesFailed = true;
    }
    $assert($overrideWithoutNotesFailed, 'El override manual debe exigir motivo auditado en notas.');

    $zeroQuantityFailed = false;
    try {
        $externalRepository->createFromCuttingOrder(
            $cuttingOrderId,
            ['work_type' => 'both'],
            [['cutting_order_item_id' => (int) $eligible[0]['id'], 'quantity_sent' => 0]]
        );
    } catch (InvalidArgumentException) {
        $zeroQuantityFailed = true;
    }
    $assert($zeroQuantityFailed, 'No debe permitir quantity_sent <= 0.');

    $overSendFailed = false;
    try {
        $externalRepository->createFromCuttingOrder(
            $cuttingOrderId,
            ['work_type' => 'both'],
            [['cutting_order_item_id' => (int) $eligible[0]['id'], 'quantity_sent' => 5]]
        );
    } catch (InvalidArgumentException) {
        $overSendFailed = true;
    }
    $assert($overSendFailed, 'No debe permitir enviar más que quantity_cut disponible.');

    $supplierId = $supplierRepository->create([
        'name' => 'Proveedor Bordado ' . $suffix,
        'ruc' => '900' . random_int(100000, 999999),
        'contact_name' => 'Contacto',
        'phone' => '',
        'email' => '',
        'address' => '',
        'payment_terms' => '',
        'delivery_terms' => '',
        'status' => 'active',
        'notes' => '',
    ]);

    $externalWorkOrderId = $externalRepository->createFromCuttingOrder(
        $cuttingOrderId,
        [
            'supplier_id' => $supplierId,
            'external_work_number' => 'EXT-' . $suffix,
            'work_type' => 'embroidery',
            'expected_return_date' => '2026-06-08',
            'next_stage' => 'quality_control',
            'notes' => 'Enviar bordado',
        ],
        [['cutting_order_item_id' => (int) $eligible[0]['id'], 'quantity_sent' => 4, 'work_details' => 'Logo pecho', 'notes' => 'Bordado logo']]
    );
    $externalOrder = $externalRepository->findWithDetails($externalWorkOrderId);
    $assert((string) ($externalOrder['status'] ?? '') === 'draft', 'La orden externa debe crearse draft.');
    $assert(count($externalOrder['items'] ?? []) === 1, 'Debe copiar ítems enviados a trabajo externo.');

    $externalRepository->send($externalWorkOrderId);
    $sentOrder = $externalRepository->findWithDetails($externalWorkOrderId);
    $assert((string) ($sentOrder['status'] ?? '') === 'sent', 'Enviar debe marcar orden externa sent.');
    $assert(trim((string) ($sentOrder['send_note_number'] ?? '')) !== '', 'Enviar debe generar o exigir nota de envío.');
    $assert((string) ($sentOrder['items'][0]['status'] ?? '') === 'sent', 'Enviar debe marcar ítems sent.');
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'external_work_sent', 'Enviar debe mover producción a proveedor externo.');

    $editSentFailed = false;
    try {
        $externalRepository->updateDraft($externalWorkOrderId, ['work_type' => 'embroidery'], []);
    } catch (InvalidArgumentException) {
        $editSentFailed = true;
    }
    $assert($editSentFailed, 'No debe editar libremente una orden externa enviada.');

    $cancelSentFailed = false;
    try {
        $externalRepository->cancelDraft($externalWorkOrderId);
    } catch (InvalidArgumentException) {
        $cancelSentFailed = true;
    }
    $assert($cancelSentFailed, 'No debe cancelar orden externa sent.');

    $externalItemId = (int) $sentOrder['items'][0]['id'];
    $overReceiptFailed = false;
    try {
        $externalRepository->createReceipt(
            $externalWorkOrderId,
            ['next_stage' => 'quality_control'],
            [['external_work_order_item_id' => $externalItemId, 'quantity_received' => 5, 'quantity_accepted' => 5, 'quantity_rejected' => 0]]
        );
    } catch (InvalidArgumentException) {
        $overReceiptFailed = true;
    }
    $assert($overReceiptFailed, 'No debe recibir más que pendiente de retorno.');

    $badAcceptedRejectedFailed = false;
    try {
        $externalRepository->createReceipt(
            $externalWorkOrderId,
            ['next_stage' => 'quality_control'],
            [['external_work_order_item_id' => $externalItemId, 'quantity_received' => 2, 'quantity_accepted' => 2, 'quantity_rejected' => 1]]
        );
    } catch (InvalidArgumentException) {
        $badAcceptedRejectedFailed = true;
    }
    $assert($badAcceptedRejectedFailed, 'No debe permitir accepted + rejected mayor a received.');

    $receiptId = $externalRepository->createReceipt(
        $externalWorkOrderId,
        ['receipt_number' => 'REC-PARC-' . $suffix, 'next_stage' => 'quality_control', 'notes' => 'Retorno parcial'],
        [['external_work_order_item_id' => $externalItemId, 'quantity_received' => 2, 'quantity_accepted' => 1, 'quantity_rejected' => 1, 'quality_notes' => 'Una pieza manchada']]
    );
    $externalRepository->confirmReceipt($receiptId);
    $partialOrder = $externalRepository->findWithDetails($externalWorkOrderId);
    $assert((string) ($partialOrder['status'] ?? '') === 'partially_returned', 'Retorno parcial debe marcar orden partially_returned.');
    $assert((float) ($partialOrder['items'][0]['quantity_returned'] ?? 0) === 2.0, 'Debe acumular cantidad retornada.');
    $assert((float) ($partialOrder['items'][0]['quantity_rejected'] ?? 0) === 1.0, 'Debe trazar cantidad rechazada.');
    $assert((string) ($partialOrder['items'][0]['status'] ?? '') === 'partially_returned', 'Ítem parcial debe quedar partially_returned.');
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'external_work_received', 'Retorno parcial debe registrar recepción externa sin cerrar flujo.');

    $receiptId2 = $externalRepository->createReceipt(
        $externalWorkOrderId,
        ['receipt_number' => 'REC-TOT-' . $suffix, 'next_stage' => 'quality_control', 'notes' => 'Retorno total'],
        [['external_work_order_item_id' => $externalItemId, 'quantity_received' => 2, 'quantity_accepted' => 2, 'quantity_rejected' => 0, 'quality_notes' => 'OK']]
    );
    $externalRepository->confirmReceipt($receiptId2);
    $returnedOrder = $externalRepository->findWithDetails($externalWorkOrderId);
    $assert((string) ($returnedOrder['status'] ?? '') === 'returned', 'Retorno total debe marcar orden returned.');
    $assert((float) ($returnedOrder['items'][0]['quantity_returned'] ?? 0) === 4.0, 'Retorno total debe acumular todo lo enviado.');
    $assert((float) ($returnedOrder['items'][0]['quantity_rejected'] ?? 0) === 1.0, 'Lo rechazado debe quedar trazado y no avanzar.');
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'quality_control', 'next_stage quality_control debe quedar registrado en producción.');

    $externalRepository->close($externalWorkOrderId);
    $assert((string) ($externalRepository->find($externalWorkOrderId)['status'] ?? '') === 'closed', 'Debe cerrar orden externa returned.');

    $auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE document_type = 'external_work_orders' AND document_id = " . $externalWorkOrderId)->fetchColumn();
    $assert($auditCount >= 5, 'audit_log debe registrar acciones principales de trabajo externo.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['POST', '/cutting-orders/' . $cuttingOrderId . '/external-work-orders'],
        ['GET', '/cutting-orders/' . $cuttingOrderId . '/external-work-orders/create'],
        ['POST', '/external-work-orders/' . $externalWorkOrderId . '/send'],
        ['POST', '/external-work-orders/' . $externalWorkOrderId . '/receipts'],
        ['GET', '/external-work-orders/' . $externalWorkOrderId . '/receipts/create'],
        ['POST', '/external-work-orders/' . $externalWorkOrderId . '/receipts/' . $receiptId2 . '/confirm'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/external-work-orders/' . $externalWorkOrderId)), 'Consulta debe poder ver trabajos externos.');
} finally {
    Auth::logout();
    if (is_int($externalWorkOrderId)) {
        $pdo->exec('DELETE FROM external_work_orders WHERE id = ' . $externalWorkOrderId);
    }
    if (is_int($cuttingOrderId)) {
        $pdo->exec('DELETE FROM cutting_orders WHERE id = ' . $cuttingOrderId);
    }
    foreach ($invalidCuttingOrderIds as $invalidCuttingOrderId) {
        $pdo->exec('DELETE FROM cutting_orders WHERE id = ' . (int) $invalidCuttingOrderId);
    }
    if (is_int($productionOrderId)) {
        $pdo->exec('DELETE FROM production_orders WHERE id = ' . $productionOrderId);
    }
    if (is_int($customerOrderId)) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . $customerOrderId);
    }
    foreach ($materialIds as $materialId) {
        $pdo->exec('DELETE FROM raw_material_inventory WHERE id = ' . (int) $materialId);
    }
    if (is_int($supplierId)) {
        $pdo->exec('DELETE FROM suppliers WHERE id = ' . $supplierId);
    }
    if (is_int($contractId)) {
        $contractRepository->delete($contractId);
    }
    if (is_int($clientId)) {
        $clientRepository->delete($clientId);
    }
}

return true;
