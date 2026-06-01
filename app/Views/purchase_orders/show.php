<section class="page-head">
    <div>
        <p class="eyebrow">Orden de compra</p>
        <h1><?= e($order['order_number']) ?></h1>
        <p class="muted"><?= e($order['client_name'] ?: 'Sin cliente') ?> · <?= e($order['order_date']) ?></p>
        <span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span>
    </div>
    <?= \App\Support\View::partial('partials/document_actions', [
        'type' => 'purchase_orders',
        'document' => $order,
        'meta' => $meta,
        'create_url' => '/delivery-notes/create?purchase_order_id=' . (int) $order['id'],
        'create_label' => 'Crear nota',
        'print_url' => '/purchase-orders/' . (int) $order['id'] . '/print',
        'export_url' => '/purchase-orders/' . (int) $order['id'] . '/export/csv',
    ]) ?>
</section>

<?php if (!empty($meta['locks']['locked'])): ?>
    <section class="alert alert--warning">
        <?= e((string) $meta['locks']['reason']) ?>
    </section>
<?php endif; ?>
<?php if (!empty($meta['close_reason'])): ?>
    <section class="alert alert--info">
        <?= e((string) $meta['close_reason']) ?>
    </section>
<?php endif; ?>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Contrato</dt><dd><?= e($order['contract_number'] ?: 'Carga manual') ?></dd></div>
            <div><dt>ID referencia</dt><dd><?= e($order['effective_reference_number']) ?></dd></div>
            <div><dt>Modalidad</dt><dd><?= e($order['effective_contract_type']) ?></dd></div>
            <div><dt>Cliente</dt><dd><?= e($order['client_name'] ?: 'Sin cliente') ?></dd></div>
            <div><dt>RUC</dt><dd><?= e($order['effective_tax_id']) ?></dd></div>
            <div><dt>Provisorio</dt><dd><?= !empty($order['is_provisional']) ? 'Sí' : 'No' ?></dd></div>
            <div><dt>Estado</dt><dd><?= e(document_status_label($meta['document']['status'])) ?></dd></div>
            <div><dt>Total</dt><dd>Gs. <?= e(money($order['total_amount'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Saldos por item</h2>
        <div class="list-stack">
            <?php foreach ($balances as $balance): ?>
                <div class="balance-item">
                    <strong><?= e($balance['product_name']) ?></strong>
                    <span>Ordenado: <?= e((string) $balance['quantity']) ?></span>
                    <span>Entregado: <?= e((string) $balance['consumed_quantity']) ?></span>
                    <span class="<?= (float) $balance['remaining_quantity'] > 0.0001 ? 'text-warning' : 'text-success' ?>">Saldo: <?= e((string) $balance['remaining_quantity']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Producto</th>
                <th>Unidad</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td><?= e($item['unit_measure']) ?></td>
                    <td><?= e((string) $item['quantity']) ?></td>
                    <td>Gs. <?= e(money($item['unit_price'])) ?></td>
                    <td>Gs. <?= e(money($item['total_item'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?= \App\Support\View::partial('partials/audit_trail', [
    'entries' => document_audit_entries('purchase_orders', (int) $order['id']),
]) ?>

<section class="panel">
    <div class="panel__header">
        <h2>Trazabilidad</h2>
        <span class="muted">Orden -> nota -> remisión -> factura</span>
    </div>
    <div class="trace-list">
        <?php foreach ($traceability as $item): ?>
            <article class="trace-card">
                <strong><?= e($item['product_name']) ?></strong>
                <span>Orden: <?= e((string) $item['quantity']) ?> <?= e($item['unit_measure']) ?></span>
                <?php foreach ($item['delivery_note_items'] as $deliveryItem): ?>
                    <span>Nota <?= e($deliveryItem['note_number']) ?>: <?= e((string) $deliveryItem['quantity']) ?></span>
                    <?php foreach ($deliveryItem['remission_items'] as $remissionItem): ?>
                        <span>Remisión <?= e($remissionItem['remission_number']) ?>: <?= e((string) $remissionItem['quantity']) ?></span>
                        <?php foreach ($remissionItem['invoice_items'] as $invoiceItem): ?>
                            <span>Factura <?= e($invoiceItem['invoice_number']) ?>: <?= e((string) $invoiceItem['quantity']) ?></span>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
