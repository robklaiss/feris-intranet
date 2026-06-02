<section class="page-head">
    <div>
        <p class="eyebrow">Inventario</p>
        <h1>Producto terminado</h1>
        <p class="muted">Existencias disponibles generadas desde empaquetado.</p>
    </div>
</section>

<section class="panel">
    <form method="get" class="filters">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Código, paquete, cliente, contrato">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['available', 'reserved', 'remitted', 'depleted', 'cancelled'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(finished_goods_status_label($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Código ítem</span><input type="text" name="item_code" value="<?= e($filters['item_code'] ?? '') ?>"></label>
        <label><span>Talle</span><input type="text" name="size" value="<?= e($filters['size'] ?? '') ?>"></label>
        <button type="submit" class="button">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Código interno</th><th>Cliente</th><th>Contrato</th><th>Ítem</th><th>Talle</th><th>Color</th><th>Disponible</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong><?= e($item['internal_code']) ?></strong></td>
                    <td><?= e($item['client_name']) ?></td>
                    <td><?= e($item['contract_number']) ?></td>
                    <td><?= e($item['item_code']) ?></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e((string) $item['quantity_available']) ?></td>
                    <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(finished_goods_status_label($item['status'])) ?></span></td>
                    <td><a href="/finished-goods-inventory/<?= e((string) $item['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="9" class="empty">Sin inventario terminado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
