<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\FinishedGoodsInventoryRepository;
use App\Support\Request;

final class FinishedGoodsInventoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
            'client_id' => trim((string) $request->input('client_id', '')),
            'contract_id' => trim((string) $request->input('contract_id', '')),
            'dependency_id' => trim((string) $request->input('dependency_id', '')),
            'item_code' => trim((string) $request->input('item_code', '')),
            'size' => trim((string) $request->input('size', '')),
            'color' => trim((string) $request->input('color', '')),
            'label' => trim((string) $request->input('label', '')),
            'location' => trim((string) $request->input('location', '')),
            'available_only' => trim((string) $request->input('available_only', '')),
        ];

        return $this->render('finished_goods_inventory/index', [
            'items' => (new FinishedGoodsInventoryRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $id)
    {
        $item = (new FinishedGoodsInventoryRepository())->find((int) $id);
        if (!$item) {
            return $this->redirectWithMessage('/finished-goods-inventory', 'Inventario terminado no encontrado.', 'error');
        }

        return $this->render('finished_goods_inventory/show', ['item' => $item]);
    }
}
