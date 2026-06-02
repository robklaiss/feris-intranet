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
        <span class="muted">Inventario terminado -> remisión -> factura</span>
    </div>
    <div class="trace-list">
        <?php foreach ($traceability as $item): ?>
            <article class="trace-card">
                <strong><?= e($item['product_name']) ?></strong>
                <span>Remisión: <?= e((string) $item['quantity']) ?> <?= e($item['unit_measure']) ?></span>
                <?php if (!empty($item['finished_goods_inventory_id'])): ?>
                    <span>Inventario terminado: <a href="/finished-goods-inventory/<?= e((string) $item['finished_goods_inventory_id']) ?>"><?= e($item['finished_goods_internal_code'] ?? ('#' . $item['finished_goods_inventory_id'])) ?></a></span>
                    <span>Producción: <a href="/production-orders/<?= e((string) $item['production_order_id']) ?>"><?= e($item['production_number'] ?? '') ?></a></span>
                    <span>Empaque: <a href="/packaging-orders/<?= e((string) $item['packaging_order_id']) ?>"><?= e($item['packaging_number'] ?? '') ?></a></span>
                    <?php if (!empty($item['quality_control_check_id'])): ?>
                        <span>Calidad: <a href="/quality-control/<?= e((string) $item['quality_control_check_id']) ?>"><?= e($item['qc_number'] ?? '') ?></a></span>
                    <?php endif; ?>
                    <span>Contrato: <?= e($item['contract_number'] ?? '') ?> · Ítem <?= e($item['item_code'] ?? '') ?></span>
                <?php endif; ?>
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
        <h2><?= !empty($remission['source_finished_goods']) ? 'Inventario vinculado' : 'Notas vinculadas' ?></h2>
        <div class="list-stack">
            <?php if (!empty($remission['source_finished_goods'])): ?>
                <?php foreach ($remission['source_finished_goods'] as $inventory): ?>
                    <div class="list-item">
                        <strong><a href="/finished-goods-inventory/<?= e((string) $inventory['id']) ?>"><?= e($inventory['internal_code']) ?></a></strong>
                        <span><?= e($inventory['item_code']) ?> · <?= e($inventory['size'] ?? '-') ?> · <?= e($inventory['color'] ?? '-') ?> · <?= e($inventory['label'] ?? '-') ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($remission['source_notes'] as $note): ?>
                    <div class="list-item">
                        <strong><?= e($note['note_number']) ?></strong>
                        <span><?= e($note['note_date']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </article>
    <article class="panel">
        <h2>Resumen</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($remission['client_name']) ?></dd></div>
            <div><dt>Contrato</dt><dd><?= e($remission['contract_number']) ?></dd></div>
            <div><dt>Origen</dt><dd><?= !empty($remission['source_finished_goods']) ? 'Inventario terminado' : 'Nota interna' ?></dd></div>
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
                <th>Trazabilidad</th>
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
                    <td>
                        <?php if (!empty($item['finished_goods_inventory_id'])): ?>
                            <a href="/finished-goods-inventory/<?= e((string) $item['finished_goods_inventory_id']) ?>"><?= e($item['finished_goods_internal_code'] ?? '') ?></a>
                            <small class="muted"><?= e($item['item_code'] ?? '') ?> · <?= e($item['size'] ?? '-') ?> · <?= e($item['color'] ?? '-') ?></small>
                        <?php else: ?>
                            <span class="muted">Nota interna</span>
                        <?php endif; ?>
                    </td>
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
