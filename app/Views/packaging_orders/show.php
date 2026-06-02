<section class="page-head">
    <div>
        <p class="eyebrow">Empaquetado</p>
        <h1><?= e($order['packaging_number']) ?></h1>
        <p class="muted">
            Calidad <a href="/quality-control/<?= e((string) $order['quality_control_check_id']) ?>"><?= e($order['qc_number']) ?></a>
            · Producción <a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a>
        </p>
        <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(packaging_order_status_label($order['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/packaging-orders" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/packaging-orders/<?= e((string) $order['id']) ?>/cancel" data-confirm="Anular este empaquetado en borrador?">
                <?= csrf_field() ?>
                <button type="submit" class="button button--secondary">Anular</button>
            </form>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'packed'): ?>
            <form method="post" action="/packaging-orders/<?= e((string) $order['id']) ?>/close">
                <?= csrf_field() ?>
                <button type="submit" class="button button--secondary">Cerrar</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($order['client_name']) ?></dd></div>
            <div><dt>Dependencia</dt><dd><?= e($order['dependency_name'] ?? '-') ?></dd></div>
            <div><dt>Contrato</dt><dd><?= e($order['contract_number']) ?></dd></div>
            <div><dt>OC cliente</dt><dd><?= e($order['po_number']) ?></dd></div>
            <div><dt>Empacado por</dt><dd><?= e($order['packed_by_name'] ?? '-') ?></dd></div>
            <div><dt>Fecha empaque</dt><dd><?= e(format_datetime($order['packed_at'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($order['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('packaging_orders', (int) $order['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Ítems</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Código</th><th>Descripción</th><th>Talle</th><th>Color</th><th>Aprobado</th><th>A empacar</th><th>Empacado</th><th>Paquete</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['description']) ?><br><span class="muted"><?= e($item['label'] ?? '') ?></span></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e((string) $item['quantity_approved']) ?></td>
                    <td><?= e((string) $item['quantity_to_pack']) ?></td>
                    <td><?= e((string) $item['quantity_packed']) ?></td>
                    <td><?= e($item['package_code'] ?? '-') ?></td>
                    <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(packaging_item_status_label($item['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (can('documents.transition') && in_array(($order['status'] ?? ''), ['draft', 'confirmed'], true)): ?>
    <section class="panel">
        <h2>Confirmar empaque</h2>
        <form method="post" action="/packaging-orders/<?= e((string) $order['id']) ?>/pack">
            <?= csrf_field() ?>
            <div class="form-grid compact">
                <label>
                    <span>Ubicación</span>
                    <input type="text" name="location" value="Depósito terminado">
                </label>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Código</th><th>A empacar</th><th>Empacado</th><th>Paquete</th><th>Notas</th></tr></thead>
                    <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td><strong><?= e($item['item_code']) ?></strong></td>
                            <td><?= e((string) $item['quantity_to_pack']) ?></td>
                            <td>
                                <input type="hidden" name="packaging_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                                <input type="number" step="0.01" min="0.01" max="<?= e((string) $item['quantity_to_pack']) ?>" name="quantity_packed[]" value="<?= e((string) $item['quantity_to_pack']) ?>">
                            </td>
                            <td><input type="text" name="package_code[]" value="<?= e($item['package_code'] ?? '') ?>" placeholder="Automático si se deja vacío"></td>
                            <td><input type="text" name="notes_item[]" value="<?= e($item['notes'] ?? '') ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button type="submit" class="button">Confirmar y crear inventario</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Inventario terminado generado</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Código interno</th><th>Ítem</th><th>Cantidad</th><th>Paquete</th><th>Ubicación</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach (($order['inventory'] ?? []) as $inventory): ?>
                <tr>
                    <td><strong><?= e($inventory['internal_code']) ?></strong></td>
                    <td><?= e($inventory['item_code']) ?></td>
                    <td><?= e((string) $inventory['quantity_available']) ?></td>
                    <td><?= e($inventory['package_code'] ?? '-') ?></td>
                    <td><?= e($inventory['location'] ?? '-') ?></td>
                    <td><span class="<?= e(status_badge_class($inventory['status'])) ?>"><?= e(finished_goods_status_label($inventory['status'])) ?></span></td>
                    <td><a href="/finished-goods-inventory/<?= e((string) $inventory['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($order['inventory'] ?? []) === []): ?>
                <tr><td colspan="7" class="empty">Sin inventario terminado generado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
