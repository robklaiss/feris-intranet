<section class="page-head">
    <div>
        <p class="eyebrow">Contratación</p>
        <h1>Contratos</h1>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/contracts/create" class="button">Nuevo contrato</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Cliente, contrato o ID">
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
                <th>Contrato</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($contracts as $contract): ?>
                <?php $meta = document_meta('contracts', $contract); ?>
                <tr>
                    <td><a href="/contracts/<?= e((string) $contract['id']) ?>"><?= e($contract['contract_number']) ?></a></td>
                    <td><?= e($contract['client_name'] ?: 'Sin cliente') ?></td>
                    <td><?= e($contract['date']) ?></td>
                    <td><span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span></td>
                    <td>Gs. <?= e(money($contract['total_amount'])) ?></td>
                    <td>
                        <?= \App\Support\View::partial('partials/document_row_actions', [
                            'type' => 'contracts',
                            'document' => $contract,
                            'meta' => $meta,
                            'show_url' => '/contracts/' . (int) $contract['id'],
                            'edit_url' => '/contracts/' . (int) $contract['id'] . '/edit',
                            'print_url' => '/contracts/' . (int) $contract['id'] . '/print',
                            'export_url' => '/contracts/' . (int) $contract['id'] . '/export/csv',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($contracts === []): ?>
                <tr><td colspan="6" class="empty">No hay contratos cargados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
