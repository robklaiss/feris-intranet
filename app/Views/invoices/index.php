<section class="page-head">
    <div>
        <p class="eyebrow">Facturación</p>
        <h1>Facturas</h1>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/invoices/create" class="button">Nueva factura</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Factura, remisión o cliente">
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
                <th>Estado</th>
                <th>Estado local</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($invoices as $invoice): ?>
                <?php $meta = document_meta('invoices', $invoice); ?>
                <tr>
                    <td><a href="/invoices/<?= e((string) $invoice['id']) ?>"><?= e($invoice['invoice_number']) ?></a></td>
                    <td><?= e($invoice['client_name'] ?: '-') ?></td>
                    <td><?= e($invoice['contract_number'] ?: '-') ?></td>
                    <td><?= e($invoice['invoice_date']) ?></td>
                    <td><span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span></td>
                    <td><span class="<?= e(status_badge_class($invoice['billing_status'])) ?>"><?= e($invoice['billing_status']) ?></span></td>
                    <td>Gs. <?= e(money($invoice['total_amount'])) ?></td>
                    <td>
                        <?= \App\Support\View::partial('partials/document_row_actions', [
                            'type' => 'invoices',
                            'document' => $invoice,
                            'meta' => $meta,
                            'show_url' => '/invoices/' . (int) $invoice['id'],
                            'print_url' => '/invoices/' . (int) $invoice['id'] . '/print',
                            'export_url' => '/invoices/' . (int) $invoice['id'] . '/export/csv',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($invoices === []): ?>
                <tr><td colspan="8" class="empty">No hay facturas cargadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
