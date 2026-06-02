<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\CuttingOrderRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\RawMaterialInventoryRepository;
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
foreach (['cutting_orders', 'cutting_order_items', 'cutting_order_materials'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 013 debe crear la tabla ' . $table . '.');
}

$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$customerOrderRepository = new CustomerPurchaseOrderRepository();
$productionRepository = new ProductionOrderRepository();
$inventoryRepository = new RawMaterialInventoryRepository();
$stockCheckRepository = new StockCheckRepository();
$cuttingRepository = new CuttingOrderRepository();

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$contractId = null;
$customerOrderId = null;
$productionOrderId = null;
$materialId = null;
$cuttingOrderId = null;

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para corte.');

    $clientId = $clientRepository->create([
        'name' => 'Cliente Corte ' . $suffix,
        'tax_id' => '805' . random_int(100000, 999999) . '-1',
        'addresses' => 'Central',
        'contacts' => 'Produccion',
        'status' => 'active',
    ]);

    $contractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-CUT-' . $suffix,
            'reference_number' => 'REF-CUT-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80512345-6',
            'status' => 'confirmed',
            'notes' => '',
            'total_amount' => 60000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [
            [
                'product_name' => 'Remera corte',
                'unit_measure' => 'unidad',
                'quantity' => 6,
                'unit_price' => 10000,
                'total_item' => 60000,
                'notes' => '',
            ],
        ]
    );

    $specId = $specRepository->create($contractId, [
        'item_code' => 'CUT-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera algodón corte',
        'size' => 'M',
        'color' => 'Azul',
        'fabric' => 'Algodón',
        'measurements' => 'Molde base M',
        'has_embroidery' => 0,
        'has_screen_printing' => 0,
        'quantity' => 6,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($contractId, $specId);

    $customerOrderId = $customerOrderRepository->create(
        [
            'contract_id' => $contractId,
            'po_number' => 'OC-CUT-' . $suffix,
            'po_date' => '2026-06-01',
            'received_date' => '2026-06-01',
            'dependency_id' => '',
            'billing_contact_id' => '',
            'notes' => '',
            'attachment_path' => '',
        ],
        [['contract_item_spec_id' => $specId, 'quantity' => 4]]
    );
    $customerOrderRepository->confirm($customerOrderId);

    $customerItemId = (int) $pdo->query('SELECT id FROM customer_purchase_order_items WHERE customer_purchase_order_id = ' . $customerOrderId . ' LIMIT 1')->fetchColumn();
    $productionOrderId = $productionRepository->create(
        [
            'customer_purchase_order_id' => $customerOrderId,
            'production_number' => 'OP-CUT-' . $suffix,
            'planned_start_date' => '',
            'planned_end_date' => '',
            'notes' => '',
        ],
        [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => 4]]
    );

    $productionItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();

    $draftCutFailed = false;
    try {
        $cuttingRepository->createFromProductionOrder($productionOrderId, ['cutting_number' => 'CUT-DRAFT-' . $suffix], []);
    } catch (InvalidArgumentException) {
        $draftCutFailed = true;
    }
    $assert($draftCutFailed, 'No debe crear corte desde OP draft.');

    $productionRepository->confirm($productionOrderId);
    $materialId = $inventoryRepository->create([
        'internal_code' => 'MAT-CUT-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela para corte',
        'unit' => 'unidad',
        'quantity_available' => 4,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_item_code' => 'CUT-' . $suffix,
        'status' => 'active',
    ]);

    $stockCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-CUT-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $materialId]]
    );
    $stockCheckRepository->reserve($stockCheckId);

    $cuttingOrderId = $cuttingRepository->createFromProductionOrder(
        $productionOrderId,
        [
            'cutting_number' => 'CORTE-' . $suffix,
            'planned_date' => '2026-06-03',
            'cut_by' => 'Mesa 1',
            'notes' => 'Corte completo',
        ],
        [['production_order_item_id' => $productionItemId, 'quantity_to_cut' => 4, 'notes' => 'Primera tanda']]
    );
    $cuttingOrder = $cuttingRepository->findWithDetails($cuttingOrderId);
    $assert((string) ($cuttingOrder['status'] ?? '') === 'draft', 'La orden de corte debe crearse en borrador.');
    $assert(count($cuttingOrder['items'] ?? []) === 1, 'Debe copiar ítems técnicos a corte.');
    $assert(count($cuttingOrder['materials'] ?? []) === 1, 'Debe vincular materiales reservados a corte.');
    $assert((string) ($cuttingOrder['items'][0]['fabric'] ?? '') === 'Algodón', 'Debe conservar especificación técnica de tela.');

    $overCutFailed = false;
    try {
        $cuttingRepository->createFromProductionOrder(
            $productionOrderId,
            ['cutting_number' => 'CORTE-OVER-' . $suffix],
            [['production_order_item_id' => $productionItemId, 'quantity_to_cut' => 1]]
        );
    } catch (InvalidArgumentException) {
        $overCutFailed = true;
    }
    $assert($overCutFailed, 'No debe duplicar corte por encima del saldo pendiente.');

    $cuttingRepository->confirm($cuttingOrderId);
    $material = $inventoryRepository->find($materialId);
    $reservationStatus = (string) $pdo->query('SELECT status FROM raw_material_reservations WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();
    $assert((float) ($material['quantity_available'] ?? 0) === 0.0, 'Confirmar corte debe descontar quantity_available.');
    $assert((float) ($material['quantity_reserved'] ?? 0) === 0.0, 'Confirmar corte debe descontar quantity_reserved.');
    $assert($reservationStatus === 'consumed', 'Confirmar corte debe marcar la reserva como consumida.');
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'in_cutting', 'Confirmar corte debe mover producción a en corte.');

    $confirmedCut = $cuttingRepository->findWithDetails($cuttingOrderId);
    $cuttingRepository->complete($cuttingOrderId, [(int) $confirmedCut['items'][0]['id'] => 4.0]);
    $completedCut = $cuttingRepository->findWithDetails($cuttingOrderId);
    $assert((string) ($completedCut['status'] ?? '') === 'completed', 'Debe completar orden de corte.');
    $assert((float) ($completedCut['items'][0]['quantity_cut'] ?? 0) === 4.0, 'Debe registrar cantidad cortada.');
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'in_sewing', 'Sin externo, el siguiente paso preparado debe ser confección.');

    $cancelConsumedFailed = false;
    try {
        $cuttingRepository->cancelDraft($cuttingOrderId);
    } catch (InvalidArgumentException) {
        $cancelConsumedFailed = true;
    }
    $assert($cancelConsumedFailed, 'No debe anular libremente una orden de corte con consumo.');

    $cuttingRepository->close($cuttingOrderId);
    $assert((string) ($cuttingRepository->find($cuttingOrderId)['status'] ?? '') === 'closed', 'Debe cerrar orden de corte completada.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['POST', '/production-orders/' . $productionOrderId . '/cutting-orders'],
        ['GET', '/production-orders/' . $productionOrderId . '/cutting-orders/create'],
        ['POST', '/cutting-orders/' . $cuttingOrderId . '/confirm'],
        ['POST', '/cutting-orders/' . $cuttingOrderId . '/complete'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/cutting-orders/' . $cuttingOrderId)), 'Consulta debe poder ver corte.');
} finally {
    if (is_int($cuttingOrderId)) {
        $pdo->exec('DELETE FROM cutting_orders WHERE id = ' . $cuttingOrderId);
    }
    if (is_int($productionOrderId)) {
        $pdo->exec('DELETE FROM production_orders WHERE id = ' . $productionOrderId);
    }
    if (is_int($customerOrderId)) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . $customerOrderId);
    }
    if (is_int($materialId)) {
        $pdo->exec('DELETE FROM raw_material_inventory WHERE id = ' . $materialId);
    }
    if (is_int($contractId)) {
        $contractRepository->delete($contractId);
    }
    if (is_int($clientId)) {
        $clientRepository->delete($clientId);
    }
    Auth::logout();
}

return true;
