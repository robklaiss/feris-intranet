<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RawMaterialInventoryRepository;
use App\Support\Request;
use Throwable;

final class RawMaterialInventoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
            'material_type' => trim((string) $request->input('material_type', '')),
        ];

        return $this->render('raw_materials/index', [
            'materials' => (new RawMaterialInventoryRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('raw_materials/form', [
            'title' => 'Nuevo insumo',
            'action' => '/raw-materials',
            'material' => $this->emptyMaterial(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->materialData($request);

        try {
            $id = (new RawMaterialInventoryRepository())->create($data);
            return $this->redirectWithMessage('/raw-materials/' . $id, 'Insumo registrado.');
        } catch (Throwable $exception) {
            $this->flashInput($data);
            return $this->redirectWithMessage('/raw-materials/create', 'No se pudo registrar el insumo: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $repo = new RawMaterialInventoryRepository();
        $material = $repo->find((int) $id);

        if (!$material) {
            return $this->redirectWithMessage('/raw-materials', 'Insumo no encontrado.', 'error');
        }

        return $this->render('raw_materials/show', [
            'material' => $material,
            'reservations' => $repo->activeReservations((int) $id),
        ]);
    }

    public function edit(Request $request, string $id)
    {
        $material = (new RawMaterialInventoryRepository())->find((int) $id);
        if (!$material) {
            return $this->redirectWithMessage('/raw-materials', 'Insumo no encontrado.', 'error');
        }

        return $this->render('raw_materials/form', [
            'title' => 'Editar insumo',
            'action' => '/raw-materials/' . $id . '/update',
            'material' => $material,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $data = $this->materialData($request);

        try {
            (new RawMaterialInventoryRepository())->update((int) $id, $data);
            return $this->redirectWithMessage('/raw-materials/' . $id, 'Insumo actualizado.');
        } catch (Throwable $exception) {
            $this->flashInput($data);
            return $this->redirectWithMessage('/raw-materials/' . $id . '/edit', 'No se pudo actualizar el insumo: ' . $exception->getMessage(), 'error');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function materialData(Request $request): array
    {
        return $request->only([
            'internal_code',
            'material_type',
            'description',
            'unit',
            'quantity_available',
            'quantity_reserved',
            'minimum_stock',
            'supplier_name',
            'supplier_ruc',
            'lot_number',
            'location',
            'cost',
            'related_item_code',
            'related_product_type',
            'status',
            'notes',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMaterial(): array
    {
        return [
            'internal_code' => '',
            'material_type' => '',
            'description' => '',
            'unit' => 'unidad',
            'quantity_available' => '0',
            'quantity_reserved' => '0',
            'minimum_stock' => '0',
            'supplier_name' => '',
            'supplier_ruc' => '',
            'lot_number' => '',
            'location' => '',
            'cost' => '',
            'related_item_code' => '',
            'related_product_type' => '',
            'status' => 'active',
            'notes' => '',
        ];
    }
}
