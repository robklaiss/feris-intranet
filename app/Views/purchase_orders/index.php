<section class="page-head">
    <div>
        <p class="eyebrow">Abastecimiento</p>
        <h1>Órdenes de compra</h1>
    </div>
    <?php if (can('documents.create')): ?>
        <a href="/purchase-orders/create" class="button">Nueva orden</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Orden, contrato o cliente">
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
                <th>Orden</th>
                <th>Cliente</th>
                <th>Contrato</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <?php $meta = document_meta('purchase_orders', $order); ?>
                <tr>
                    <td><a href="/purchase-orders/<?= e((string) $order['id']) ?>"><?= e($order['order_number']) ?></a></td>
                    <td><?= e($order['client_name'] ?: 'Sin cliente') ?></td>
                    <td><?= e($order['contract_number'] ?: 'Manual') ?></td>
                    <td><?= e($order['order_date']) ?></td>
                    <td><span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span></td>
                    <td>Gs. <?= e(money($order['total_amount'])) ?></td>
                    <td>
                        <?= \App\Support\View::partial('partials/document_row_actions', [
                            'type' => 'purchase_orders',
                            'document' => $order,
                            'meta' => $meta,
                            'show_url' => '/purchase-orders/' . (int) $order['id'],
                            'print_url' => '/purchase-orders/' . (int) $order['id'] . '/print',
                            'export_url' => '/purchase-orders/' . (int) $order['id'] . '/export/csv',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="7" class="empty">No hay órdenes cargadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
