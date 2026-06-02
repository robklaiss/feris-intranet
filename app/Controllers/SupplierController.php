<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SupplierRepository;
use App\Support\Request;
use Throwable;

final class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('suppliers/index', [
            'suppliers' => (new SupplierRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('suppliers/form', [
            'title' => 'Nuevo proveedor',
            'action' => '/suppliers',
            'supplier' => $this->emptySupplier(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->supplierData($request);

        try {
            $id = (new SupplierRepository())->create($data);
            return $this->redirectWithMessage('/suppliers/' . $id, 'Proveedor registrado.');
        } catch (Throwable $exception) {
            $this->flashInput($data);
            return $this->redirectWithMessage('/suppliers/create', 'No se pudo registrar el proveedor: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $supplier = (new SupplierRepository())->find((int) $id);
        if (!$supplier) {
            return $this->redirectWithMessage('/suppliers', 'Proveedor no encontrado.', 'error');
        }

        return $this->render('suppliers/show', ['supplier' => $supplier]);
    }

    public function edit(Request $request, string $id)
    {
        $supplier = (new SupplierRepository())->find((int) $id);
        if (!$supplier) {
            return $this->redirectWithMessage('/suppliers', 'Proveedor no encontrado.', 'error');
        }

        return $this->render('suppliers/form', [
            'title' => 'Editar proveedor',
            'action' => '/suppliers/' . $id . '/update',
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $data = $this->supplierData($request);

        try {
            (new SupplierRepository())->update((int) $id, $data);
            return $this->redirectWithMessage('/suppliers/' . $id, 'Proveedor actualizado.');
        } catch (Throwable $exception) {
            $this->flashInput($data);
            return $this->redirectWithMessage('/suppliers/' . $id . '/edit', 'No se pudo actualizar el proveedor: ' . $exception->getMessage(), 'error');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierData(Request $request): array
    {
        return $request->only([
            'name',
            'ruc',
            'contact_name',
            'phone',
            'email',
            'address',
            'payment_terms',
            'delivery_terms',
            'status',
            'notes',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySupplier(): array
    {
        return [
            'name' => '',
            'ruc' => '',
            'contact_name' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'payment_terms' => '',
            'delivery_terms' => '',
            'status' => 'active',
            'notes' => '',
        ];
    }
}
