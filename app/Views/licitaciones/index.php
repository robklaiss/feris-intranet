<section class="page-head">
    <div>
        <p class="eyebrow">Contratación</p>
        <h1>Licitaciones</h1>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/licitaciones/create" class="button">Nueva licitación</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Número, título o entidad">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (licitacion_statuses() as $status => $label): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($label) ?></option>
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
                <th>Llamado</th>
                <th>Entidad</th>
                <th>Fechas</th>
                <th>Estado</th>
                <th>Checklist</th>
                <th>Adjuntos</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($licitaciones as $licitacion): ?>
                <tr>
                    <td>
                        <a href="/licitaciones/<?= e((string) $licitacion['id']) ?>"><?= e($licitacion['call_number']) ?></a>
                        <div class="muted"><?= e($licitacion['title']) ?></div>
                    </td>
                    <td><?= e($licitacion['institution']) ?></td>
                    <td>
                        <div>Publicación: <?= e($licitacion['publish_date'] ?: '-') ?></div>
                        <div>Apertura: <?= e($licitacion['opening_date'] ?: '-') ?></div>
                    </td>
                    <td><span class="<?= e(licitacion_status_badge_class($licitacion['status'])) ?>"><?= e(licitacion_status_label($licitacion['status'])) ?></span></td>
                    <td><?= e((string) ($licitacion['checklist_attached'] ?? 0)) ?> / <?= e((string) ($licitacion['checklist_total'] ?? 0)) ?></td>
                    <td><?= e((string) ($licitacion['file_total'] ?? 0)) ?></td>
                    <td class="table-actions">
                        <a href="/licitaciones/<?= e((string) $licitacion['id']) ?>">Ver</a>
                        <?php if (can('documents.edit')): ?>
                            <a href="/licitaciones/<?= e((string) $licitacion['id']) ?>/edit">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($licitaciones === []): ?>
                <tr><td colspan="7" class="empty">No hay licitaciones cargadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
