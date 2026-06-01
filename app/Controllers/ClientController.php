<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\ClientBillingContactRepository;
use App\Repositories\ClientDependencyRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Support\Request;

final class ClientController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('clients/index', [
            'clients' => (new ClientRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $id)
    {
        $client = (new ClientRepository())->find((int) $id);

        if (!$client) {
            return $this->redirectWithMessage('/clients', 'Cliente no encontrado.', 'error');
        }

        return $this->render('clients/show', [
            'client' => $client,
            'contracts' => (new ContractRepository())->byClientId((int) $id),
            'dependencies' => (new ClientDependencyRepository())->byClientId((int) $id),
            'billingContacts' => (new ClientBillingContactRepository())->byClientId((int) $id),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('clients/form', [
            'client' => [
                'name' => '',
                'tax_id' => '',
                'addresses' => '',
                'contacts' => '',
                'status' => 'active',
            ],
            'action' => '/clients',
            'title' => 'Nuevo cliente',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only(['name', 'tax_id', 'addresses', 'contacts', 'status']);
        $data['name'] = trim((string) $data['name']);

        if ($data['name'] === '') {
            $this->flashInput($data);
            return $this->redirectWithMessage('/clients/create', 'El nombre es obligatorio.', 'error');
        }

        $repository = new ClientRepository();
        $id = $repository->create($data);
        (new AuditLogRepository())->log('clients', $id, 'created', $data);

        return $this->redirectWithMessage('/clients/' . $id, 'Cliente creado correctamente.');
    }

    public function edit(Request $request, string $id)
    {
        $client = (new ClientRepository())->find((int) $id);

        if (!$client) {
            return $this->redirectWithMessage('/clients', 'Cliente no encontrado.', 'error');
        }

        return $this->render('clients/form', [
            'client' => $client,
            'action' => '/clients/' . $id . '/update',
            'title' => 'Editar cliente',
        ]);
    }

    public function update(Request $request, string $id)
    {
        $client = (new ClientRepository())->find((int) $id);
        if (!$client) {
            return $this->redirectWithMessage('/clients', 'Cliente no encontrado.', 'error');
        }

        $data = $request->only(['name', 'tax_id', 'addresses', 'contacts', 'status']);
        $data['name'] = trim((string) $data['name']);

        if ($data['name'] === '') {
            return $this->redirectWithMessage('/clients/' . $id . '/edit', 'El nombre es obligatorio.', 'error');
        }

        (new ClientRepository())->update((int) $id, $data);
        (new AuditLogRepository())->log('clients', (int) $id, 'updated', $data);

        return $this->redirectWithMessage('/clients/' . $id, 'Cliente actualizado correctamente.');
    }

    public function delete(Request $request, string $id)
    {
        $repository = new ClientRepository();
        $client = $repository->find((int) $id);

        if (!$client) {
            return $this->redirectWithMessage('/clients', 'Cliente no encontrado.', 'error');
        }

        $repository->delete((int) $id);
        (new AuditLogRepository())->log('clients', (int) $id, 'deleted', $client);

        return $this->redirectWithMessage('/clients', 'Cliente eliminado.');
    }

    public function storeDependency(Request $request, string $id)
    {
        $client = (new ClientRepository())->find((int) $id);
        if (!$client) {
            return $this->redirectWithMessage('/clients', 'Cliente no encontrado.', 'error');
        }

        $data = $this->dependencyPayload($request);
        if ($data['name'] === '') {
            return $this->redirectWithMessage('/clients/' . $id, 'El nombre de la dependencia es obligatorio.', 'error');
        }

        $dependencyId = (new ClientDependencyRepository())->create((int) $id, $data);
        (new AuditLogRepository())->log('client_dependencies', $dependencyId, 'create_client_dependency', $data, [
            'document_type' => 'clients',
            'document_id' => (int) $id,
            'document_number' => (string) $client['name'],
        ]);

        return $this->redirectWithMessage('/clients/' . $id, 'Dependencia creada correctamente.');
    }

    public function updateDependency(Request $request, string $id, string $dependencyId)
    {
        $client = (new ClientRepository())->find((int) $id);
        $repository = new ClientDependencyRepository();
        $dependency = $repository->findForClient((int) $id, (int) $dependencyId);
        if (!$client || !$dependency) {
            return $this->redirectWithMessage('/clients/' . $id, 'Dependencia no encontrada.', 'error');
        }

        $data = $this->dependencyPayload($request);
        if ($data['name'] === '') {
            return $this->redirectWithMessage('/clients/' . $id, 'El nombre de la dependencia es obligatorio.', 'error');
        }

        $repository->update((int) $id, (int) $dependencyId, $data);
        (new AuditLogRepository())->log('client_dependencies', (int) $dependencyId, 'update_client_dependency', $data, [
            'document_type' => 'clients',
            'document_id' => (int) $id,
            'document_number' => (string) $client['name'],
        ]);

        return $this->redirectWithMessage('/clients/' . $id, 'Dependencia actualizada correctamente.');
    }

    public function storeBillingContact(Request $request, string $id)
    {
        $client = (new ClientRepository())->find((int) $id);
        if (!$client) {
            return $this->redirectWithMessage('/clients', 'Cliente no encontrado.', 'error');
        }

        $data = $this->billingContactPayload($request);
        if ($data['name'] === '') {
            return $this->redirectWithMessage('/clients/' . $id, 'El nombre del contacto de facturación es obligatorio.', 'error');
        }

        $contactId = (new ClientBillingContactRepository())->create((int) $id, $data);
        (new AuditLogRepository())->log('client_billing_contacts', $contactId, 'create_billing_contact', $data, [
            'document_type' => 'clients',
            'document_id' => (int) $id,
            'document_number' => (string) $client['name'],
        ]);

        return $this->redirectWithMessage('/clients/' . $id, 'Contacto de facturación creado correctamente.');
    }

    public function updateBillingContact(Request $request, string $id, string $contactId)
    {
        $client = (new ClientRepository())->find((int) $id);
        $repository = new ClientBillingContactRepository();
        $contact = $repository->findForClient((int) $id, (int) $contactId);
        if (!$client || !$contact) {
            return $this->redirectWithMessage('/clients/' . $id, 'Contacto de facturación no encontrado.', 'error');
        }

        $data = $this->billingContactPayload($request);
        if ($data['name'] === '') {
            return $this->redirectWithMessage('/clients/' . $id, 'El nombre del contacto de facturación es obligatorio.', 'error');
        }

        $repository->update((int) $id, (int) $contactId, $data);
        (new AuditLogRepository())->log('client_billing_contacts', (int) $contactId, 'update_billing_contact', $data, [
            'document_type' => 'clients',
            'document_id' => (int) $id,
            'document_number' => (string) $client['name'],
        ]);

        return $this->redirectWithMessage('/clients/' . $id, 'Contacto de facturación actualizado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function dependencyPayload(Request $request): array
    {
        $data = $request->only([
            'name',
            'address',
            'city',
            'phone',
            'email',
            'operational_contact_name',
            'operational_contact_phone',
            'reception_contact_name',
            'reception_contact_phone',
            'billing_contact_id',
            'notes',
        ]);
        $data['name'] = trim((string) $data['name']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function billingContactPayload(Request $request): array
    {
        $data = $request->only([
            'dependency_id',
            'name',
            'role',
            'phone',
            'email',
            'ruc',
            'business_name',
            'address',
            'notes',
        ]);
        $data['name'] = trim((string) $data['name']);
        $data['is_default'] = $request->input('is_default') ? 1 : 0;

        return $data;
    }
}
