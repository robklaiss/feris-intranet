<section class="page-head">
    <div>
        <p class="eyebrow">Remisión</p>
        <h1><?= e($remission['remission_number']) ?></h1>
        <p class="muted"><?= e($remission['remission_date']) ?></p>
        <span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span>
    </div>
    <?= \App\Support\View::partial('partials/document_actions', [
        'type' => 'remissions',
        'document' => $remission,
        'meta' => $meta,
        'create_url' => '/invoices/create?remission_ids=' . (int) $remission['id'],
        'create_label' => 'Nueva factura',
        'print_url' => '/remissions/' . (int) $remission['id'] . '/print',
        'export_url' => '/remissions/' . (int) $remission['id'] . '/export/csv',
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
        <span class="muted">Remisión -> factura</span>
    </div>
    <div class="trace-list">
        <?php foreach ($traceability as $item): ?>
            <article class="trace-card">
                <strong><?= e($item['product_name']) ?></strong>
                <span>Remisión: <?= e((string) $item['quantity']) ?> <?= e($item['unit_measure']) ?></span>
                <?php foreach ($item['invoice_items'] as $invoiceItem): ?>
                    <span>Factura <?= e($invoiceItem['invoice_number']) ?>: <?= e((string) $invoiceItem['quantity']) ?></span>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?= \App\Support\View::partial('partials/audit_trail', [
    'entries' => document_audit_entries('remissions', (int) $remission['id']),
]) ?>

<section class="grid-two">
    <article class="panel">
        <h2>Notas vinculadas</h2>
        <div class="list-stack">
            <?php foreach ($remission['source_notes'] as $note): ?>
                <div class="list-item">
                    <strong><?= e($note['note_number']) ?></strong>
                    <span><?= e($note['note_date']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
    <article class="panel">
        <h2>Resumen</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($remission['client_name']) ?></dd></div>
            <div><dt>Contrato</dt><dd><?= e($remission['contract_number']) ?></dd></div>
            <div><dt>N° de ID</dt><dd><?= e($remission['reference_number']) ?></dd></div>
            <div><dt>Modalidad</dt><dd><?= e($remission['contract_type']) ?></dd></div>
            <div><dt>RUC</dt><dd><?= e($remission['tax_id']) ?></dd></div>
            <div><dt>Estado</dt><dd><?= e(document_status_label($meta['document']['status'])) ?></dd></div>
            <div><dt>Total</dt><dd>Gs. <?= e(money($remission['total_amount'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($remission['notes'])) ?></dd></div>
        </dl>
    </article>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Traslado</h2>
        <dl class="detail-list">
            <div><dt>Punto de partida</dt><dd><?= nl2br(e($remission['origin_address'])) ?></dd></div>
            <div><dt>Punto de llegada</dt><dd><?= nl2br(e($remission['destination_address'])) ?></dd></div>
            <div><dt>Inicio</dt><dd><?= e($remission['transfer_start_date']) ?></dd></div>
            <div><dt>Término</dt><dd><?= e($remission['transfer_end_date']) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Transporte</h2>
        <dl class="detail-list">
            <div><dt>Vehículo</dt><dd><?= e($remission['vehicle_brand']) ?></dd></div>
            <div><dt>Chapa</dt><dd><?= e($remission['vehicle_plate']) ?></dd></div>
            <div><dt>Transportista</dt><dd><?= e($remission['carrier_name']) ?></dd></div>
            <div><dt>RUC transportista</dt><dd><?= e($remission['carrier_tax_id']) ?></dd></div>
            <div><dt>Conductor</dt><dd><?= e($remission['driver_name']) ?></dd></div>
            <div><dt>C.I. conductor</dt><dd><?= e($remission['driver_document']) ?></dd></div>
        </dl>
    </article>
</section>

<section class="panel">
    <h2>Saldo facturable</h2>
    <div class="list-stack">
        <?php foreach ($balances as $balance): ?>
            <div class="balance-item">
                <strong><?= e($balance['product_name']) ?></strong>
                <span>Remisionado: <?= e((string) $balance['quantity']) ?></span>
                <span>Facturado: <?= e((string) $balance['consumed_quantity']) ?></span>
                <span class="<?= (float) $balance['remaining_quantity'] > 0.0001 ? 'text-warning' : 'text-success' ?>">Saldo: <?= e((string) $balance['remaining_quantity']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
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
            <?php foreach ($remission['items'] as $item): ?>
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
