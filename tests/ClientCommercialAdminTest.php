<?php

declare(strict_types=1);

use App\Repositories\ClientBillingContactRepository;
use App\Repositories\ClientDependencyRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ContractDncpDataRepository;
use App\Repositories\ContractRepository;
use App\Support\AccessControl;
use App\Support\Auth;
use App\Support\Database;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = Database::connection();
$tables = $pdo->query(
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN (
        'client_dependencies',
        'client_billing_contacts',
        'contract_dncp_data'
    )"
)->fetchAll(PDO::FETCH_COLUMN) ?: [];
$assert(count($tables) === 3, 'La migracion 007 debe crear las tres tablas comerciales.');

$clientRepository = new ClientRepository();
$dependencyRepository = new ClientDependencyRepository();
$billingContactRepository = new ClientBillingContactRepository();
$contractRepository = new ContractRepository();
$dncpRepository = new ContractDncpDataRepository();

$suffix = bin2hex(random_bytes(4));
$clientId = null;
$contractId = null;

try {
    $clientId = $clientRepository->create([
        'name' => 'Cliente Textil ' . $suffix,
        'tax_id' => '800' . random_int(100000, 999999) . '-1',
        'addresses' => 'Casa central',
        'contacts' => 'Administracion',
        'status' => 'active',
    ]);

    $dependencyId = $dependencyRepository->create($clientId, [
        'name' => 'Deposito San Lorenzo ' . $suffix,
        'address' => 'Ruta 2',
        'city' => 'San Lorenzo',
        'phone' => '021 555 000',
        'email' => 'deposito@example.test',
        'operational_contact_name' => 'Operaciones',
        'operational_contact_phone' => '0981 100 100',
        'reception_contact_name' => 'Recepcion',
        'reception_contact_phone' => '0981 200 200',
        'billing_contact_id' => '',
        'notes' => 'Recibe uniformes',
    ]);
    $dependencies = $dependencyRepository->byClientId($clientId);
    $assert(count($dependencies) === 1, 'Se debe poder crear y listar una dependencia.');

    $billingContactId = $billingContactRepository->create($clientId, [
        'dependency_id' => $dependencyId,
        'name' => 'Facturacion DNCP ' . $suffix,
        'role' => 'Administracion',
        'phone' => '021 555 111',
        'email' => 'facturacion@example.test',
        'ruc' => '801' . random_int(100000, 999999) . '-2',
        'business_name' => 'Razon Fiscal Textil ' . $suffix,
        'address' => 'Direccion fiscal',
        'is_default' => 1,
        'notes' => 'Contacto default',
    ]);
    $contacts = $billingContactRepository->byClientId($clientId);
    $assert(count($contacts) === 1, 'Se debe poder crear y listar un contacto de facturacion.');
    $assert((int) $contacts[0]['is_default'] === 1, 'El contacto default debe quedar marcado.');

    $dependencyRepository->update($clientId, $dependencyId, [
        'name' => 'Deposito San Lorenzo ' . $suffix,
        'address' => 'Ruta 2',
        'city' => 'San Lorenzo',
        'phone' => '021 555 000',
        'email' => 'deposito@example.test',
        'operational_contact_name' => 'Operaciones',
        'operational_contact_phone' => '0981 100 100',
        'reception_contact_name' => 'Recepcion',
        'reception_contact_phone' => '0981 200 200',
        'billing_contact_id' => $billingContactId,
        'notes' => 'Recibe uniformes',
    ]);

    $contractId = $contractRepository->create(
        [
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'contract_number' => 'CT-TEXTIL-' . $suffix,
            'reference_number' => 'REF-' . $suffix,
            'contract_type' => 'Licitacion',
            'tax_id' => '80012345-6',
            'status' => 'draft',
            'notes' => 'Contrato prueba DNCP',
            'total_amount' => 100000,
            'is_provisional' => 0,
            'provisional_data' => null,
        ],
        [
            [
                'product_name' => 'Camisa institucional',
                'unit_measure' => 'unidad',
                'quantity' => 10,
                'unit_price' => 10000,
                'total_item' => 100000,
                'notes' => '',
            ],
        ]
    );

    $dncpRepository->upsert($contractId, [
        'dncp_tender_id' => 'DNCP-' . $suffix,
        'dncp_contract_number' => '1/2026',
        'dncp_customer_purchase_order_number' => 'OC-' . $suffix,
        'dncp_public_entity' => 'Entidad Publica',
        'dncp_requesting_dependency' => 'Deposito San Lorenzo ' . $suffix,
        'dncp_procurement_modality' => 'LPN',
        'dncp_procurement_code' => 'TEXTIL',
        'dncp_contract_date' => '2026-06-01',
        'dncp_valid_from' => '2026-06-01',
        'dncp_valid_until' => '2026-12-31',
        'dncp_currency' => 'PYG',
        'dncp_fiscal_business_name' => 'Razon Fiscal Textil ' . $suffix,
        'dncp_fiscal_ruc' => '80012345-6',
        'dncp_billing_contact_id' => $billingContactId,
        'dncp_notes' => 'Datos DNCP',
    ]);
    $dncp = $dncpRepository->findByContractId($contractId);
    $assert($dncp !== null, 'Se debe poder guardar DNCP en contrato.');
    $assert($dncp['tender_id'] === 'DNCP-' . $suffix, 'El ID de licitacion DNCP debe persistir.');

    $byDependency = $clientRepository->search(['q' => 'San Lorenzo ' . $suffix]);
    $assert($byDependency !== [], 'La busqueda debe encontrar cliente por dependencia.');
    $assert((int) $byDependency[0]['id'] === $clientId, 'La busqueda por dependencia debe devolver el cliente correcto.');

    $byRuc = $clientRepository->search(['q' => (string) $contacts[0]['ruc']]);
    $assert($byRuc !== [], 'La busqueda debe encontrar cliente por RUC de contacto de facturacion.');

    Auth::logout();
    $assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
    $permission = AccessControl::permissionFor('POST', '/clients/' . $clientId . '/dependencies');
    $assert($permission === 'clients.manage', 'Crear dependencias debe requerir clients.manage.');
    $assert(!Auth::can((string) $permission), 'Usuario consulta no debe poder modificar dependencias.');
    Auth::logout();
} finally {
    if (is_int($contractId)) {
        $contractRepository->delete($contractId);
    }
    if (is_int($clientId)) {
        $clientRepository->delete($clientId);
    }
}

return true;
