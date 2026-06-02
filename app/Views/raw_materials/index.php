<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Insumos</h1>
        <p class="muted">Inventario de materiales disponibles para órdenes de producción.</p>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/raw-materials/create" class="button">Nuevo insumo</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Código, tipo, proveedor o relación">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo', 'depleted' => 'Agotado'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Tipo</span>
            <input type="text" name="material_type" value="<?= e($filters['material_type'] ?? '') ?>">
        </label>
        <div class="filter-actions">
            <button type="submit" class="button button--secondary">Filtrar</button>
        </div>
    </div>
</form>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Código</th>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Disponible</th>
                <th>Reservado</th>
                <th>Libre</th>
                <th>Relación</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($materials as $material): ?>
                <tr>
                    <td><strong><?= e($material['internal_code']) ?></strong></td>
                    <td><?= e($material['material_type']) ?></td>
                    <td><?= e($material['description']) ?></td>
                    <td><?= e((string) $material['quantity_available']) ?> <?= e($material['unit']) ?></td>
                    <td><?= e((string) $material['quantity_reserved']) ?></td>
                    <td><?= e((string) $material['available_to_reserve']) ?></td>
                    <td><?= e(trim((string) ($material['related_item_code'] ?? '') . ' ' . (string) ($material['related_product_type'] ?? ''))) ?></td>
                    <td><span class="<?= e(status_badge_class($material['status'])) ?>"><?= e($material['status']) ?></span></td>
                    <td class="actions">
                        <a href="/raw-materials/<?= e((string) $material['id']) ?>">Ver</a>
                        <?php if (can('documents.edit')): ?>
                            <a href="/raw-materials/<?= e((string) $material['id']) ?>/edit">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($materials === []): ?>
                <tr><td colspan="9" class="empty">No hay insumos cargados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
