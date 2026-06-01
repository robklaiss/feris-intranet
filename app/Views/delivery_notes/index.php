<section class="page-head">
    <div>
        <p class="eyebrow">Operación</p>
        <h1>Notas internas de entrega</h1>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/delivery-notes/create" class="button">Nueva nota</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Nota, orden o cliente">
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
                <th>Orden</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($notes as $note): ?>
                <?php $meta = document_meta('delivery_notes', $note); ?>
                <tr>
                    <td><a href="/delivery-notes/<?= e((string) $note['id']) ?>"><?= e($note['note_number']) ?></a></td>
                    <td><?= e($note['order_numbers'] ?: '-') ?></td>
                    <td><?= e($note['client_name']) ?></td>
                    <td><?= e($note['note_date']) ?></td>
                    <td><span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span></td>
                    <td>Gs. <?= e(money($note['total_amount'])) ?></td>
                    <td>
                        <?= \App\Support\View::partial('partials/document_row_actions', [
                            'type' => 'delivery_notes',
                            'document' => $note,
                            'meta' => $meta,
                            'show_url' => '/delivery-notes/' . (int) $note['id'],
                            'print_url' => '/delivery-notes/' . (int) $note['id'] . '/print',
                            'export_url' => '/delivery-notes/' . (int) $note['id'] . '/export/csv',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($notes === []): ?>
                <tr><td colspan="7" class="empty">No hay notas cargadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
