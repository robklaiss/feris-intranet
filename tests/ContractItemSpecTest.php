<?php

declare(strict_types=1);

use App\Controllers\ContractController;
use App\Repositories\ClientDependencyRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ContractItemSpecRepository;
use App\Repositories\ContractRepository;
use App\Services\BalanceService;
use App\Services\DocumentWorkflowService;
use App\Services\TraceabilityService;
use App\Support\AccessControl;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Request;
use App\Support\View;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = Database::connection();
$tables = $pdo->query(
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'contract_item_specs'"
)->fetchAll(PDO::FETCH_COLUMN) ?: [];
$assert($tables === ['contract_item_specs'], 'La migracion 008 debe crear contract_item_specs.');

$columns = $pdo->query('PRAGMA table_info(contract_item_specs)')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$columnNames = array_column($columns, 'name');
foreach (['contract_id', 'item_code', 'product_category', 'quantity', 'destination_dependency_id', 'status'] as $column) {
    $assert(in_array($column, $columnNames, true), 'contract_item_specs debe incluir la columna ' . $column . '.');
}

$clientRepository = new ClientRepository();
$dependencyRepository = new ClientDependencyRepository();
$contractRepository = new ContractRepository();
$specRepository = new ContractItemSpecRepository();

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$contractId = null;

try {
    $clientId = $clientRepository->create([
        'name' => 'Cliente Specs ' . $suffix,
        'tax_id' => '802' . random_int(100000, 999999) . '-1',
        'addresses' => 'Casa central',
        'contacts' => 'Compras',
        'status' => 'active',
    ]);

    $dependencyId = $dependencyRepository->create($clientId, [
        'name' => 'Deposito Specs ' . $suffix,
        'address' => 'Ruta textil',
        'city' => 'Asuncion',
        'phone' => '',
        'email' => '',
        'operational_contact_name' => '',
        'operational_contact_phone' => '',
        'reception_contact_name' => '',
        'reception_contact_phone' => '',
        'billing_contact_id' => '',
        'notes' => '',
    ]);

    $contractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-SPEC-' . $suffix,
            'reference_number' => 'REF-SPEC-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80212345-6',
            'status' => 'draft',
            'notes' => 'Contrato prueba specs',
            'total_amount' => 150000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [
            [
                'product_name' => 'Remera institucional',
                'unit_measure' => 'unidad',
                'quantity' => 15,
                'unit_price' => 10000,
                'total_item' => 150000,
                'notes' => '',
            ],
        ]
    );

    $specId = $specRepository->create($contractId, [
        'item_code' => 'IT-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera polo',
        'description' => 'Remera pique manga corta ' . $suffix,
        'size' => 'M',
        'color' => 'Azul',
        'fabric' => 'Pique',
        'grammage' => '180 g',
        'measurements' => 'Tabla estandar',
        'finishing' => 'Cuello tejido',
        'has_embroidery' => 1,
        'embroidery_details' => 'Logo pecho',
        'has_screen_printing' => 0,
        'screen_printing_details' => '',
        'logo_position' => 'Pecho izquierdo',
        'quantity' => 15,
        'unit' => 'unidad',
        'label' => 'Lote tecnico ' . $suffix,
        'destination_dependency_id' => $dependencyId,
        'technical_notes' => 'Controlar color',
    ]);

    $spec = $specRepository->findForContract($contractId, $specId);
    $assert($spec !== null, 'Se debe poder crear un item tecnico de contrato.');
    $assert((string) $spec['item_code'] === 'IT-' . $suffix, 'El codigo de item tecnico debe persistir.');

    $specRepository->update($contractId, $specId, [
        'item_code' => 'IT-' . $suffix,
        'product_category' => 'textil',
        'product_type' => 'Remera polo premium',
        'description' => 'Remera pique actualizada ' . $suffix,
        'quantity' => 20,
        'unit' => 'unidad',
        'destination_dependency_id' => $dependencyId,
    ]);
    $updated = $specRepository->findForContract($contractId, $specId);
    $assert((float) ($updated['quantity'] ?? 0) === 20.0, 'Se debe poder editar un item tecnico.');

    $emptyCodeFailed = false;
    try {
        $specRepository->create($contractId, [
            'item_code' => '',
            'product_category' => 'textil',
            'quantity' => 1,
        ]);
    } catch (InvalidArgumentException) {
        $emptyCodeFailed = true;
    }
    $assert($emptyCodeFailed, 'No debe permitir item_code vacio.');

    $invalidQuantityFailed = false;
    try {
        $specRepository->create($contractId, [
            'item_code' => 'BAD-QTY-' . $suffix,
            'product_category' => 'textil',
            'quantity' => 0,
        ]);
    } catch (InvalidArgumentException) {
        $invalidQuantityFailed = true;
    }
    $assert($invalidQuantityFailed, 'No debe permitir cantidad cero o negativa.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    $createPermission = AccessControl::permissionFor('POST', '/contracts/' . $contractId . '/item-specs');
    $editPermission = AccessControl::permissionFor('POST', '/contracts/' . $contractId . '/item-specs/' . $specId . '/update');
    $assert($createPermission === 'documents.create', 'Crear specs debe requerir documents.create.');
    $assert($editPermission === 'documents.edit', 'Editar specs debe requerir documents.edit.');
    $assert(!Auth::can((string) $createPermission), 'Usuario consulta no debe poder crear items tecnicos.');
    $assert(!Auth::can((string) $editPermission), 'Usuario consulta no debe poder editar items tecnicos.');
    Auth::logout();

    $contract = $contractRepository->findWithItems($contractId);
    $html = View::render('contracts/show', [
        'contract' => $contract,
        'balances' => (new BalanceService())->contractItemBalances($contractId),
        'traceability' => (new TraceabilityService())->contractTrace($contractId),
        'meta' => (new DocumentWorkflowService())->metadata('contracts', $contractId),
    ]);
    $assert(str_contains($html, 'Ítems técnicos / productos contratados'), 'El detalle del contrato debe listar la seccion de items tecnicos.');
    $assert(str_contains($html, 'IT-' . $suffix), 'El detalle del contrato debe listar el codigo del item tecnico.');

    $byCode = $contractRepository->search(['q' => 'IT-' . $suffix]);
    $assert($byCode !== [], 'La busqueda debe encontrar contrato por codigo de item.');
    $assert((int) $byCode[0]['id'] === $contractId, 'La busqueda por codigo debe devolver el contrato correcto.');

    $byDescription = $contractRepository->search(['q' => 'actualizada ' . $suffix]);
    $assert($byDescription !== [], 'La busqueda debe encontrar contrato por descripcion de item.');
    $assert((int) $byDescription[0]['id'] === $contractId, 'La busqueda por descripcion debe devolver el contrato correcto.');

    Auth::logout();
    $assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse para auditar.');
    $controller = new ContractController();
    $controller->storeItemSpec(new Request([], [
        'item_code' => 'AUD-' . $suffix,
        'product_category' => 'consumo',
        'product_type' => 'Insumo consumo',
        'description' => 'Item auditado ' . $suffix,
        'quantity' => '3',
        'unit' => 'unidad',
        'label' => 'Auditoria',
        'destination_dependency_id' => (string) $dependencyId,
    ], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/contracts/' . $contractId . '/item-specs']), (string) $contractId);

    $auditSpec = $pdo->prepare('SELECT id FROM contract_item_specs WHERE contract_id = :contract_id AND item_code = :item_code');
    $auditSpec->execute(['contract_id' => $contractId, 'item_code' => 'AUD-' . $suffix]);
    $auditSpecId = (int) ($auditSpec->fetchColumn() ?: 0);
    $assert($auditSpecId > 0, 'La accion del controlador debe crear item tecnico.');

    $controller->updateItemSpec(new Request([], [
        'item_code' => 'AUD-' . $suffix,
        'product_category' => 'consumo',
        'product_type' => 'Insumo consumo editado',
        'description' => 'Item auditado editado ' . $suffix,
        'quantity' => '4',
        'unit' => 'unidad',
        'label' => 'Auditoria',
        'destination_dependency_id' => (string) $dependencyId,
    ], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/contracts/' . $contractId . '/item-specs/' . $auditSpecId . '/update']), (string) $contractId, (string) $auditSpecId);

    $auditCount = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_log
         WHERE document_type = 'contracts'
           AND document_id = :contract_id
           AND action IN ('create_contract_item_spec', 'update_contract_item_spec')"
    );
    $auditCount->execute(['contract_id' => $contractId]);
    $assert((int) $auditCount->fetchColumn() >= 2, 'audit_log debe registrar creacion y edicion de specs.');
    Auth::logout();
} finally {
    if (is_int($contractId)) {
        $contractRepository->delete($contractId);
    }
    if (is_int($clientId)) {
        $clientRepository->delete($clientId);
    }
    Auth::logout();
}

return true;
