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
        <label><span>Cliente ID</span><input type="text" name="client_id" value="<?= e($filters['client_id'] ?? '') ?>"></label>
        <label><span>Contrato ID</span><input type="text" name="contract_id" value="<?= e($filters['contract_id'] ?? '') ?>"></label>
        <label><span>Dependencia ID</span><input type="text" name="dependency_id" value="<?= e($filters['dependency_id'] ?? '') ?>"></label>
        <label><span>Código ítem</span><input type="text" name="item_code" value="<?= e($filters['item_code'] ?? '') ?>"></label>
        <label><span>Talle</span><input type="text" name="size" value="<?= e($filters['size'] ?? '') ?>"></label>
        <label><span>Color</span><input type="text" name="color" value="<?= e($filters['color'] ?? '') ?>"></label>
        <label><span>Etiqueta</span><input type="text" name="label" value="<?= e($filters['label'] ?? '') ?>"></label>
        <label><span>Ubicación</span><input type="text" name="location" value="<?= e($filters['location'] ?? '') ?>"></label>
        <button type="submit" class="button">Filtrar</button>
    </form>

    <form method="get" action="/remissions/create" class="form-stack">
        <input type="hidden" name="source" value="finished_goods">
        <div class="form-actions">
            <button type="submit" class="button">Generar remisión</button>
        </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th>Código interno</th><th>Cliente</th><th>Contrato</th><th>Ítem</th><th>Talle</th><th>Color</th><th>Etiqueta</th><th>Ubicación</th><th>Disponible</th><th>Reservado</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $canRemit = in_array((string) $item['status'], ['available', 'reserved'], true) && (float) ($item['real_available'] ?? 0) > 0.0001; ?>
                <tr>
                    <td>
                        <?php if ($canRemit): ?>
                            <input type="checkbox" name="finished_goods_inventory_ids[]" value="<?= e((string) $item['id']) ?>">
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e($item['internal_code']) ?></strong></td>
                    <td><?= e($item['client_name']) ?></td>
                    <td><?= e($item['contract_number']) ?></td>
                    <td><?= e($item['item_code']) ?></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e($item['label'] ?? '-') ?></td>
                    <td><?= e($item['location'] ?? '-') ?></td>
                    <td><?= e((string) ($item['real_available'] ?? $item['quantity_available'])) ?></td>
                    <td><?= e((string) $item['quantity_reserved']) ?></td>
                    <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(finished_goods_status_label($item['status'])) ?></span></td>
                    <td><a href="/finished-goods-inventory/<?= e((string) $item['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="13" class="empty">Sin inventario terminado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    </form>
</section>
