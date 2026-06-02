<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Costureros</h1>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/seamsters/create" class="button">Nuevo costurero</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Nombre, documento, email o teléfono">
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
            <thead><tr><th>Costurero</th><th>Documento</th><th>Contacto</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($seamsters as $seamster): ?>
                <tr>
                    <td><strong><?= e($seamster['name']) ?></strong></td>
                    <td><?= e($seamster['document_number'] ?? '-') ?></td>
                    <td>
                        <?= e($seamster['phone'] ?? '-') ?><br>
                        <span class="muted"><?= e($seamster['email'] ?? '') ?></span>
                    </td>
                    <td><span class="<?= e(status_badge_class($seamster['status'])) ?>"><?= e(seamster_status_label($seamster['status'])) ?></span></td>
                    <td class="actions">
                        <a href="/seamsters/<?= e((string) $seamster['id']) ?>">Ver</a>
                        <?php if (can('documents.edit')): ?>
                            <a href="/seamsters/<?= e((string) $seamster['id']) ?>/edit">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($seamsters === []): ?>
                <tr><td colspan="5" class="empty">No hay costureros cargados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
