<section class="page-head">
    <div>
        <p class="eyebrow">Nota interna</p>
        <h1><?= e($note['note_number']) ?></h1>
        <p class="muted"><?= e($note['client_name']) ?> · Órdenes <?= e(implode(', ', array_map(static fn (array $order): string => (string) $order['order_number'], $note['source_orders'] ?? []))) ?></p>
        <span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span>
    </div>
    <?= \App\Support\View::partial('partials/document_actions', [
        'type' => 'delivery_notes',
        'document' => $note,
        'meta' => $meta,
        'create_url' => '/remissions/create?delivery_note_ids=' . (int) $note['id'],
        'create_label' => 'Nueva remisión',
        'print_url' => '/delivery-notes/' . (int) $note['id'] . '/print',
        'export_url' => '/delivery-notes/' . (int) $note['id'] . '/export/csv',
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
        <h2>Trazabilidad</h2>
        <span class="muted">Nota -> remisión -> factura</span>
    </div>
    <div class="trace-list">
        <?php foreach ($traceability as $item): ?>
            <article class="trace-card">
                <strong><?= e($item['product_name']) ?></strong>
                <span>Nota: <?= e((string) $item['quantity']) ?> <?= e($item['unit_measure']) ?></span>
                <?php foreach ($item['remission_items'] as $remissionItem): ?>
                    <span>Remisión <?= e($remissionItem['remission_number']) ?>: <?= e((string) $remissionItem['quantity']) ?></span>
                    <?php foreach ($remissionItem['invoice_items'] as $invoiceItem): ?>
                        <span>Factura <?= e($invoiceItem['invoice_number']) ?>: <?= e((string) $invoiceItem['quantity']) ?></span>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?= \App\Support\View::partial('partials/audit_trail', [
    'entries' => document_audit_entries('delivery_notes', (int) $note['id']),
]) ?>

<section class="grid-two">
    <article class="panel">
        <h2>Datos del documento</h2>
        <dl class="detail-list">
            <div><dt>Contrato</dt><dd><?= e($note['contract_number']) ?></dd></div>
            <div><dt>N° de ID</dt><dd><?= e($note['identifier_number']) ?></dd></div>
            <div><dt>Modalidad</dt><dd><?= e($note['contract_type']) ?></dd></div>
            <div><dt>RUC</dt><dd><?= e($note['tax_id']) ?></dd></div>
            <div><dt>Dirección de entrega</dt><dd><?= nl2br(e($note['delivery_address'])) ?></dd></div>
            <div><dt>Estado</dt><dd><?= e(document_status_label($meta['document']['status'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Saldos de consumo</h2>
        <div class="list-stack">
            <?php foreach ($balances as $balance): ?>
                <div class="balance-item">
                    <strong><?= e($balance['product_name']) ?></strong>
                    <span>Despachado: <?= e((string) $balance['quantity']) ?></span>
                    <span>Remisionado: <?= e((string) $balance['consumed_quantity']) ?></span>
                    <span class="<?= (float) $balance['remaining_quantity'] > 0.0001 ? 'text-warning' : 'text-success' ?>">Saldo: <?= e((string) $balance['remaining_quantity']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="panel">
    <h2>Firmas</h2>
    <dl class="detail-list">
        <div><dt>Recibe</dt><dd><?= e($note['receiver_name']) ?></dd></div>
        <div><dt>Firma recibe</dt><dd><?= e($note['receiver_signature']) ?></dd></div>
        <div><dt>Entrega</dt><dd><?= e($note['issuer_name']) ?></dd></div>
        <div><dt>Firma entrega</dt><dd><?= e($note['issuer_signature']) ?></dd></div>
    </dl>
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
            <?php foreach ($note['items'] as $item): ?>
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
