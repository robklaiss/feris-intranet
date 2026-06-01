<section class="page-head">
    <div>
        <p class="eyebrow">Despacho</p>
        <h1>Remisiones</h1>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/remissions/create" class="button">Nueva remisión</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Remisión, nota o cliente">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (document_statuses() as $status => $label): ?>
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
                <th>Número</th>
                <th>Cliente</th>
                <th>Contrato</th>
                <th>Fecha</th>
                <th>Notas</th>
                <th>Estado</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($remissions as $remission): ?>
                <?php $meta = document_meta('remissions', $remission); ?>
                <tr>
                    <td><a href="/remissions/<?= e((string) $remission['id']) ?>"><?= e($remission['remission_number']) ?></a></td>
                    <td><?= e($remission['client_name'] ?: '-') ?></td>
                    <td><?= e($remission['contract_number'] ?: '-') ?></td>
                    <td><?= e($remission['remission_date']) ?></td>
                    <td><?= e($remission['notes_numbers'] ?: '-') ?></td>
                    <td><span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span></td>
                    <td>Gs. <?= e(money($remission['total_amount'])) ?></td>
                    <td>
                        <?= \App\Support\View::partial('partials/document_row_actions', [
                            'type' => 'remissions',
                            'document' => $remission,
                            'meta' => $meta,
                            'show_url' => '/remissions/' . (int) $remission['id'],
                            'print_url' => '/remissions/' . (int) $remission['id'] . '/print',
                            'export_url' => '/remissions/' . (int) $remission['id'] . '/export/csv',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($remissions === []): ?>
                <tr><td colspan="8" class="empty">No hay remisiones cargadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
