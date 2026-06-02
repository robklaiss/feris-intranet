<?php

declare(strict_types=1);

use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Repositories\FinishedGoodsInventoryRepository;
use App\Repositories\RemissionRepository;
use App\Services\DocumentContextService;
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
$columns = $pdo->query('PRAGMA table_info(remission_items)')->fetchAll() ?: [];
$columnIndex = [];
foreach ($columns as $column) {
    $columnIndex[(string) $column['name']] = $column;
}
foreach (['finished_goods_inventory_id', 'packaging_order_id', 'production_order_id', 'contract_item_spec_id', 'item_code', 'label', 'size', 'color'] as $column) {
    $assert(isset($columnIndex[$column]), 'La migracion 018 debe agregar remission_items.' . $column . '.');
}
$assert((int) ($columnIndex['delivery_note_item_id']['notnull'] ?? 1) === 0, 'delivery_note_item_id debe ser nullable para remisiones desde inventario terminado.');

$clientRepository = new ClientRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();
$finishedRepository = new FinishedGoodsInventoryRepository();
$remissionRepository = new RemissionRepository();
$workflow = new DocumentWorkflowService();

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$contractId = null;
$customerOrderId = null;
$productionOrderId = null;
$qualityCheckId = null;
$packagingOrderId = null;
$remissionId = null;

try {
    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para remisiones desde inventario terminado.');

    $clientId = $clientRepository->create([
        'name' => 'Cliente PT Remision ' . $suffix,
        'tax_id' => '801' . random_int(100000, 999999) . '-1',
        'addresses' => 'Deposito destino',
        'contacts' => 'Operaciones',
        'status' => 'active',
    ]);

    $contractId = $contractRepository->create([
        'client_id' => $clientId,
        'date' => '2026-06-01',
        'contract_number' => 'CT-PT-REM-' . $suffix,
        'reference_number' => 'REF-PT-REM-' . $suffix,
        'contract_type' => 'Licitacion',
        'tax_id' => '80712345-6',
        'status' => 'confirmed',
        'notes' => '',
        'total_amount' => 5 * 11000,
        'is_provisional' => 0,
        'provisional_data' => null,
    ], [[
        'product_name' => 'Chomba terminada',
        'unit_measure' => 'unidad',
        'quantity' => 5,
        'unit_price' => 11000,
        'total_item' => 5 * 11000,
        'notes' => '',
    ]]);

    $specId = $specRepository->create($contractId, [
        'item_code' => 'PTREM-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Chomba',
        'description' => 'Chomba terminada',
        'size' => 'M',
        'color' => 'Azul',
        'fabric' => 'Pique',
        'measurements' => 'Molde M',
        'has_embroidery' => 0,
        'has_screen_printing' => 0,
        'quantity' => 5,
        'unit' => 'unidad',
        'label' => 'Etiqueta PT',
    ]);
    $specRepository->confirm($contractId, $specId);

    $pdo->prepare(
        'INSERT INTO customer_purchase_orders (
            client_id, contract_id, po_number, po_date, received_date, status, notes, updated_at
         ) VALUES (
            :client_id, :contract_id, :po_number, "2026-06-01", "2026-06-01", "confirmed", "", CURRENT_TIMESTAMP
         )'
    )->execute([
        'client_id' => $clientId,
        'contract_id' => $contractId,
        'po_number' => 'OC-PT-REM-' . $suffix,
    ]);
    $customerOrderId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO customer_purchase_order_items (
            customer_purchase_order_id, contract_item_spec_id, item_code, description, product_type,
            quantity, unit, balance_quantity, updated_at
         ) VALUES (
            :order_id, :spec_id, :item_code, "Chomba terminada", "Chomba", 5, "unidad", 5, CURRENT_TIMESTAMP
         )'
    )->execute([
        'order_id' => $customerOrderId,
        'spec_id' => $specId,
        'item_code' => 'PTREM-' . $suffix,
    ]);
    $customerOrderItemId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO production_orders (
            customer_purchase_order_id, contract_id, client_id, production_number, status, production_stage, updated_at
         ) VALUES (
            :customer_order_id, :contract_id, :client_id, :production_number, "confirmed", "packaging", CURRENT_TIMESTAMP
         )'
    )->execute([
        'customer_order_id' => $customerOrderId,
        'contract_id' => $contractId,
        'client_id' => $clientId,
        'production_number' => 'OP-PT-REM-' . $suffix,
    ]);
    $productionOrderId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO production_order_items (
            production_order_id, customer_purchase_order_item_id, contract_item_spec_id, item_code,
            product_type, description, size, color, quantity, unit, balance_quantity, updated_at
         ) VALUES (
            :production_order_id, :customer_item_id, :spec_id, :item_code,
            "Chomba", "Chomba terminada", "M", "Azul", 5, "unidad", 5, CURRENT_TIMESTAMP
         )'
    )->execute([
        'production_order_id' => $productionOrderId,
        'customer_item_id' => $customerOrderItemId,
        'spec_id' => $specId,
        'item_code' => 'PTREM-' . $suffix,
    ]);
    $productionOrderItemId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO quality_control_checks (
            production_order_id, qc_number, status, updated_at
         ) VALUES (
            :production_order_id, :qc_number, "approved", CURRENT_TIMESTAMP
         )'
    )->execute([
        'production_order_id' => $productionOrderId,
        'qc_number' => 'QC-PT-REM-' . $suffix,
    ]);
    $qualityCheckId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO quality_control_check_items (
            quality_control_check_id, production_order_item_id, contract_item_spec_id, item_code,
            product_type, description, size, color, quantity_received, quantity_approved, status, updated_at
         ) VALUES (
            :quality_check_id, :production_item_id, :spec_id, :item_code,
            "Chomba", "Chomba terminada", "M", "Azul", 5, 5, "approved", CURRENT_TIMESTAMP
         )'
    )->execute([
        'quality_check_id' => $qualityCheckId,
        'production_item_id' => $productionOrderItemId,
        'spec_id' => $specId,
        'item_code' => 'PTREM-' . $suffix,
    ]);
    $qualityCheckItemId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO packaging_orders (
            production_order_id, quality_control_check_id, packaging_number, status, updated_at
         ) VALUES (
            :production_order_id, :quality_check_id, :packaging_number, "packed", CURRENT_TIMESTAMP
         )'
    )->execute([
        'production_order_id' => $productionOrderId,
        'quality_check_id' => $qualityCheckId,
        'packaging_number' => 'EMP-PT-REM-' . $suffix,
    ]);
    $packagingOrderId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO packaging_order_items (
            packaging_order_id, quality_control_check_item_id, production_order_item_id, contract_item_spec_id,
            item_code, product_type, description, size, color, quantity_approved, quantity_to_pack,
            quantity_packed, unit, label, package_code, status, updated_at
         ) VALUES (
            :packaging_order_id, :quality_item_id, :production_item_id, :spec_id,
            :item_code, "Chomba", "Chomba terminada", "M", "Azul", 5, 5,
            5, "unidad", "Etiqueta PT", :package_code, "packed", CURRENT_TIMESTAMP
         )'
    )->execute([
        'packaging_order_id' => $packagingOrderId,
        'quality_item_id' => $qualityCheckItemId,
        'production_item_id' => $productionOrderItemId,
        'spec_id' => $specId,
        'item_code' => 'PTREM-' . $suffix,
        'package_code' => 'PKG-PT-REM-' . $suffix,
    ]);
    $packagingOrderItemId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO finished_goods_inventory (
            internal_code, packaging_order_id, packaging_order_item_id, production_order_id,
            contract_id, client_id, contract_item_spec_id, item_code, product_type, description,
            size, color, quantity_available, quantity_reserved, quantity_remitted, unit, label,
            package_code, location, status, notes, updated_at
         ) VALUES (
            :internal_code, :packaging_order_id, :packaging_order_item_id, :production_order_id,
            :contract_id, :client_id, :spec_id, :item_code, "Chomba", "Chomba terminada",
            "M", "Azul", 5, 0, 0, "unidad", "Etiqueta PT",
            :package_code, "Deposito terminado", "available", "", CURRENT_TIMESTAMP
         )'
    )->execute([
        'internal_code' => 'PT-REM-' . $suffix,
        'packaging_order_id' => $packagingOrderId,
        'packaging_order_item_id' => $packagingOrderItemId,
        'production_order_id' => $productionOrderId,
        'contract_id' => $contractId,
        'client_id' => $clientId,
        'spec_id' => $specId,
        'item_code' => 'PTREM-' . $suffix,
        'package_code' => 'PKG-PT-REM-' . $suffix,
    ]);
    $inventoryId = (int) $pdo->lastInsertId();

    [$overItems, $overErrors] = $finishedRepository->prepareRemissionItems([$inventoryId], [$inventoryId => 6.0]);
    $assert($overItems === [] && $overErrors !== [], 'No debe preparar una remisión por encima de la disponibilidad.');

    [$items, $errors, $context] = $finishedRepository->prepareRemissionItems([$inventoryId], [$inventoryId => 3.0]);
    $assert($errors === [] && count($items) === 1, 'Debe preparar inventario terminado disponible para remisión.');
    $assert((int) ($context['document']['client_id'] ?? 0) === $clientId, 'El contexto debe conservar cliente de inventario terminado.');

    $remissionData = [
        'remission_number' => (new NumberingService())->next('remissions'),
        'remission_date' => '2026-06-02',
        'status' => 'draft',
        'notes' => 'Remisión desde producto terminado',
        'total_amount' => 3 * 11000,
        'client_id' => $clientId,
        'contract_id' => $contractId,
        'reference_number' => 'REF-PT-REM-' . $suffix,
        'contract_type' => 'Licitacion',
        'tax_id' => '80712345-6',
        'origin_address' => 'Deposito terminado',
        'destination_address' => 'Cliente',
        'transfer_start_date' => '2026-06-02',
        'transfer_end_date' => '2026-06-02',
        'vehicle_brand' => '',
        'vehicle_plate' => '',
        'carrier_name' => '',
        'carrier_tax_id' => '',
        'driver_name' => '',
        'driver_document' => '',
    ];
    $remissionId = $remissionRepository->create($remissionData, $items, []);
    $created = $remissionRepository->findWithItems($remissionId);
    $assert($created !== null && count($created['items']) === 1, 'Debe crear remisión con item de inventario terminado.');
    $assert($created['items'][0]['delivery_note_item_id'] === null, 'El item de remisión desde inventario terminado no debe requerir nota interna.');
    $assert((int) $created['items'][0]['finished_goods_inventory_id'] === $inventoryId, 'Debe guardar trazabilidad a finished_goods_inventory.');

    $invoiceContext = (new DocumentContextService())->invoiceContext([$remissionId]);
    $assert($invoiceContext['items'] === [], 'Una remisión draft no debe alimentar facturación todavía.');

    $workflow->transition('remissions', $remissionId, 'confirm');

    $inventory = $finishedRepository->find($inventoryId);
    $assert((float) ($inventory['quantity_available'] ?? 0) === 2.0, 'Confirmar remisión debe descontar disponibilidad.');
    $assert((float) ($inventory['quantity_remitted'] ?? 0) === 3.0, 'Confirmar remisión debe aumentar quantity_remitted.');
    $assert((string) ($inventory['status'] ?? '') === 'available', 'Inventario con saldo debe permanecer available.');

    $confirmed = $remissionRepository->findWithItems($remissionId);
    $assert((float) ($confirmed['items'][0]['quantity_available_before'] ?? 0) === 5.0, 'Debe auditar disponibilidad antes del consumo.');
    $assert((float) ($confirmed['items'][0]['quantity_available_after'] ?? 0) === 2.0, 'Debe auditar disponibilidad posterior al consumo.');

    $invoiceContext = (new DocumentContextService())->invoiceContext([$remissionId]);
    $assert(count($invoiceContext['items']) === 1 && (float) $invoiceContext['items'][0]['suggested_quantity'] === 3.0, 'La remisión confirmada debe seguir alimentando facturación.');

    $blocked = false;
    try {
        $workflow->transition('remissions', $remissionId, 'cancel');
    } catch (RuntimeException $exception) {
        $blocked = str_contains($exception->getMessage(), 'inventario terminado');
    }
    $assert($blocked, 'Debe bloquear anulación de remisión confirmada con inventario terminado consumido.');

    foreach (['confirm_remission_from_finished_goods', 'consume_finished_goods_inventory', 'block_remission_cancel_with_finished_goods_consumption'] as $action) {
        $count = (int) $pdo->query(
            'SELECT COUNT(*) FROM audit_log WHERE document_type = "remissions" AND document_id = ' . $remissionId . ' AND action = "' . $action . '"'
        )->fetchColumn();
        $assert($count >= 1, 'audit_log debe registrar ' . $action . '.');
    }
} finally {
    Auth::logout();
    if ($remissionId !== null) {
        $pdo->exec('DELETE FROM invoice_items WHERE remission_item_id IN (SELECT id FROM remission_items WHERE remission_id = ' . (int) $remissionId . ')');
        $pdo->exec('DELETE FROM remission_items WHERE remission_id = ' . (int) $remissionId);
        $pdo->exec('DELETE FROM remissions WHERE id = ' . (int) $remissionId);
    }
    if ($packagingOrderId !== null) {
        $pdo->exec('DELETE FROM finished_goods_inventory WHERE packaging_order_id = ' . (int) $packagingOrderId);
        $pdo->exec('DELETE FROM packaging_orders WHERE id = ' . (int) $packagingOrderId);
    }
    if ($qualityCheckId !== null) {
        $pdo->exec('DELETE FROM quality_control_checks WHERE id = ' . (int) $qualityCheckId);
    }
    if ($productionOrderId !== null) {
        $pdo->exec('DELETE FROM production_orders WHERE id = ' . (int) $productionOrderId);
    }
    if ($customerOrderId !== null) {
        $pdo->exec('DELETE FROM customer_purchase_orders WHERE id = ' . (int) $customerOrderId);
    }
    if ($contractId !== null) {
        $contractRepository->delete((int) $contractId);
    }
    if ($clientId !== null) {
        $clientRepository->delete((int) $clientId);
    }
}

return true;
