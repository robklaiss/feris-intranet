<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
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
foreach (['raw_material_inventory', 'stock_checks', 'stock_check_items', 'raw_material_reservations'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    $assert((string) $exists === $table, 'La migracion 010 debe crear la tabla ' . $table . '.');
}

$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$customerOrderRepository = new CustomerPurchaseOrderRepository();
$productionRepository = new ProductionOrderRepository();
$inventoryRepository = new RawMaterialInventoryRepository();
$stockCheckRepository = new StockCheckRepository();

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$contractId = null;
$customerOrderId = null;
$productionOrderId = null;
$shortMaterialId = null;
$enoughMaterialId = null;

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para stock.');

    $clientId = $clientRepository->create([
        'name' => 'Cliente Stock ' . $suffix,
        'tax_id' => '804' . random_int(100000, 999999) . '-1',
        'addresses' => 'Central',
        'contacts' => 'Produccion',
        'status' => 'active',
    ]);

    $contractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-STOCK-' . $suffix,
            'reference_number' => 'REF-STOCK-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80412345-6',
            'status' => 'confirmed',
            'notes' => '',
            'total_amount' => 80000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [
            [
                'product_name' => 'Remera stock',
                'unit_measure' => 'unidad',
                'quantity' => 8,
                'unit_price' => 10000,
                'total_item' => 80000,
                'notes' => '',
            ],
        ]
    );

    $specId = $specRepository->create($contractId, [
        'item_code' => 'STK-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera',
        'description' => 'Remera algodón',
        'quantity' => 8,
        'unit' => 'unidad',
    ]);
    $specRepository->confirm($contractId, $specId);

    $customerOrderId = $customerOrderRepository->create(
        [
            'contract_id' => $contractId,
            'po_number' => 'OC-STOCK-' . $suffix,
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
            'production_number' => 'OP-STOCK-' . $suffix,
            'planned_start_date' => '',
            'planned_end_date' => '',
            'notes' => '',
        ],
        [['customer_purchase_order_item_id' => $customerItemId, 'quantity' => 4]]
    );

    $draftCheckFailed = false;
    try {
        $stockCheckRepository->createForProductionOrder($productionOrderId, [], []);
    } catch (InvalidArgumentException) {
        $draftCheckFailed = true;
    }
    $assert($draftCheckFailed, 'No debe crear verificación desde OP draft.');

    $productionRepository->confirm($productionOrderId);
    $productionItemId = (int) $pdo->query('SELECT id FROM production_order_items WHERE production_order_id = ' . $productionOrderId . ' LIMIT 1')->fetchColumn();

    $shortMaterialId = $inventoryRepository->create([
        'internal_code' => 'MAT-SHORT-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela insuficiente',
        'unit' => 'unidad',
        'quantity_available' => 2,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_item_code' => 'STK-' . $suffix,
        'status' => 'active',
    ]);

    $shortCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-SHORT-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $shortMaterialId]]
    );
    $shortCheck = $stockCheckRepository->findWithItems($shortCheckId);
    $assert((string) ($shortCheck['status'] ?? '') === 'insufficient', 'Debe marcar verificación insuficiente cuando falta stock.');
    $assert((float) ($shortCheck['items'][0]['missing_quantity'] ?? 0) === 2.0, 'Debe calcular faltante de stock.');
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'stock_pending', 'La OP queda en stock pendiente si hay faltantes.');

    $reserveMissingFailed = false;
    try {
        $stockCheckRepository->reserve($shortCheckId);
    } catch (InvalidArgumentException) {
        $reserveMissingFailed = true;
    }
    $assert($reserveMissingFailed, 'No debe reservar si existen faltantes.');

    $enoughMaterialId = $inventoryRepository->create([
        'internal_code' => 'MAT-OK-' . $suffix,
        'material_type' => 'Remera',
        'description' => 'Tela suficiente',
        'unit' => 'unidad',
        'quantity_available' => 5,
        'quantity_reserved' => 0,
        'minimum_stock' => 0,
        'related_product_type' => 'Remera',
        'status' => 'active',
    ]);

    $okCheckId = $stockCheckRepository->createForProductionOrder(
        $productionOrderId,
        ['check_number' => 'CHK-OK-' . $suffix],
        [['production_order_item_id' => $productionItemId, 'raw_material_inventory_id' => $enoughMaterialId]]
    );
    $okCheck = $stockCheckRepository->findWithItems($okCheckId);
    $assert((string) ($okCheck['status'] ?? '') === 'sufficient', 'Debe marcar verificación suficiente.');

    $stockCheckRepository->reserve($okCheckId);
    $reservedCheck = $stockCheckRepository->findWithItems($okCheckId);
    $material = $inventoryRepository->find($enoughMaterialId);
    $assert((string) ($reservedCheck['status'] ?? '') === 'reserved', 'Debe marcar la verificación como reservada.');
    $assert((float) ($material['quantity_available'] ?? 0) === 5.0, 'Reservar no debe descontar quantity_available.');
    $assert((float) ($material['quantity_reserved'] ?? 0) === 4.0, 'Reservar debe aumentar quantity_reserved.');
    $assert((string) ($productionRepository->find($productionOrderId)['production_stage'] ?? '') === 'ready_for_cutting', 'La OP queda lista para corte si todo está reservado.');

    $doubleReserveFailed = false;
    try {
        $stockCheckRepository->reserve($okCheckId);
    } catch (InvalidArgumentException) {
        $doubleReserveFailed = true;
    }
    $assert($doubleReserveFailed, 'No debe permitir doble reserva sobre el mismo stock_check.');

    $productionRepository->cancel($productionOrderId);
    $releasedMaterial = $inventoryRepository->find($enoughMaterialId);
    $assert((float) ($releasedMaterial['quantity_reserved'] ?? 0) === 0.0, 'Anular OP debe liberar reservas activas.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    foreach ([
        ['POST', '/raw-materials'],
        ['GET', '/raw-materials/create'],
        ['GET', '/raw-materials/' . $enoughMaterialId . '/edit'],
        ['POST', '/production-orders/' . $productionOrderId . '/stock-checks'],
        ['POST', '/stock-checks/' . $okCheckId . '/reserve'],
    ] as [$method, $path]) {
        $permission = AccessControl::permissionFor($method, $path);
        $assert($permission !== null && !Auth::can((string) $permission), 'Usuario consulta no debe operar ' . $path . '.');
    }
    $assert(Auth::can((string) AccessControl::permissionFor('GET', '/raw-materials/' . $enoughMaterialId)), 'Consulta debe poder ver insumos.');
} finally {
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
    if (is_int($contractId)) {
        $contractRepository->delete($contractId);
    }
    if (is_int($clientId)) {
        $clientRepository->delete($clientId);
    }
    Auth::logout();
}

return true;
