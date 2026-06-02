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
            'item_code' => trim((string) $request->input('item_code', '')),
            'size' => trim((string) $request->input('size', '')),
            'label' => trim((string) $request->input('label', '')),
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
