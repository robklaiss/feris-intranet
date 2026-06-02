<section class="page-head">
    <div>
        <p class="eyebrow">Compras</p>
        <h1>Proveedores</h1>
        <p class="muted">Registro de proveedores para solicitudes de presupuesto y órdenes de compra.</p>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/suppliers/create" class="button">Nuevo proveedor</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Nombre, RUC, contacto, email o teléfono">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
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
                <th>Proveedor</th>
                <th>RUC</th>
                <th>Contacto</th>
                <th>Condiciones</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><strong><?= e($supplier['name']) ?></strong></td>
                    <td><?= e($supplier['ruc'] ?? '-') ?></td>
                    <td>
                        <?= e($supplier['contact_name'] ?? '-') ?><br>
                        <span class="muted"><?= e(trim((string) ($supplier['phone'] ?? '') . ' ' . (string) ($supplier['email'] ?? ''))) ?></span>
                    </td>
                    <td>
                        <?= e($supplier['payment_terms'] ?? '-') ?><br>
                        <span class="muted"><?= e($supplier['delivery_terms'] ?? '') ?></span>
                    </td>
                    <td><span class="<?= e(status_badge_class($supplier['status'])) ?>"><?= e(supplier_status_label($supplier['status'])) ?></span></td>
                    <td class="actions">
                        <a href="/suppliers/<?= e((string) $supplier['id']) ?>">Ver</a>
                        <?php if (can('documents.edit')): ?>
                            <a href="/suppliers/<?= e((string) $supplier['id']) ?>/edit">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($suppliers === []): ?>
                <tr><td colspan="6" class="empty">No hay proveedores cargados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
