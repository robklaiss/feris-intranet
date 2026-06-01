<section class="page-head">
    <div>
        <p class="eyebrow">Factura</p>
        <h1><?= e($invoice['invoice_number']) ?></h1>
        <p class="muted"><?= e($invoice['invoice_date']) ?> · Estado integración: <?= e($invoice['billing_status']) ?></p>
        <span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span>
    </div>
    <?= \App\Support\View::partial('partials/document_actions', [
        'type' => 'invoices',
        'document' => $invoice,
        'meta' => $meta,
        'send_url' => '/invoices/' . (int) $invoice['id'] . '/send',
        'print_url' => '/invoices/' . (int) $invoice['id'] . '/print',
        'export_url' => '/invoices/' . (int) $invoice['id'] . '/export/csv',
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

<section class="panel">
    <div class="panel__header">
        <h2>Trazabilidad de cierre</h2>
        <span class="muted">Cada item facturado referencia su remisión origen.</span>
    </div>
    <div class="trace-list">
        <?php foreach ($traceability as $item): ?>
            <article class="trace-card">
                <strong><?= e($item['product_name']) ?></strong>
                <span>Factura: <?= e((string) $item['quantity']) ?> <?= e($item['unit_measure']) ?></span>
                <span>Remisión origen: <?= e($item['remission_number']) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?= \App\Support\View::partial('partials/audit_trail', [
    'entries' => document_audit_entries('invoices', (int) $invoice['id']),
]) ?>

<section class="grid-two">
    <article class="panel">
        <h2>Remisiones fuente</h2>
        <div class="list-stack">
            <?php foreach ($invoice['source_remissions'] as $remission): ?>
                <div class="list-item">
                    <strong><?= e($remission['remission_number']) ?></strong>
                    <span><?= e($remission['remission_date']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
    <article class="panel">
        <h2>Estado adapter</h2>
        <pre class="code-block"><?= e($invoice['billing_payload'] ?: 'Sin payload todavía.') ?></pre>
    </article>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos de facturación</h2>
        <dl class="detail-list">
            <div><dt>Condición de venta</dt><dd><?= e($invoice['sale_condition']) ?></dd></div>
            <div><dt>Estado</dt><dd><?= e(document_status_label($meta['document']['status'])) ?></dd></div>
            <div><dt>Cliente</dt><dd><?= e($invoice['client_name']) ?></dd></div>
            <div><dt>Contrato</dt><dd><?= e($invoice['contract_number']) ?></dd></div>
            <div><dt>N° de ID</dt><dd><?= e($invoice['reference_number']) ?></dd></div>
            <div><dt>Modalidad</dt><dd><?= e($invoice['contract_type']) ?></dd></div>
            <div><dt>RUC</dt><dd><?= e($invoice['tax_id']) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Dirección</h2>
        <p><?= nl2br(e($invoice['billing_address'])) ?></p>
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
            <?php foreach ($invoice['items'] as $item): ?>
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
