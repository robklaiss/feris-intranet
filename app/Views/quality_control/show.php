<section class="page-head">
    <div>
        <p class="eyebrow">Control de calidad</p>
        <h1><?= e($check['qc_number']) ?></h1>
        <p class="muted">
            Producción <a href="/production-orders/<?= e((string) $check['production_order_id']) ?>"><?= e($check['production_number']) ?></a>
            <?php if (!empty($check['sewing_order_id'])): ?>
                · Confección <a href="/sewing-orders/<?= e((string) $check['sewing_order_id']) ?>"><?= e($check['sewing_number']) ?></a>
            <?php endif; ?>
            <?php if (!empty($check['external_work_order_id'])): ?>
                · Externo <a href="/external-work-orders/<?= e((string) $check['external_work_order_id']) ?>"><?= e($check['external_work_number']) ?></a>
            <?php endif; ?>
        </p>
        <span class="<?= e(status_badge_class($check['status'])) ?>"><?= e(quality_control_status_label($check['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/quality-control" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && ($check['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/quality-control/<?= e((string) $check['id']) ?>/confirm" data-confirm="Confirmar este control de calidad?">
                <?= csrf_field() ?>
                <button type="submit" class="button">Confirmar</button>
            </form>
            <form method="post" action="/quality-control/<?= e((string) $check['id']) ?>/cancel" data-confirm="Anular este control de calidad en borrador?">
                <?= csrf_field() ?>
                <button type="submit" class="button button--secondary">Anular</button>
            </form>
        <?php endif; ?>
        <?php if (can('documents.transition') && in_array(($check['status'] ?? ''), ['approved', 'partially_approved', 'rejected'], true)): ?>
            <form method="post" action="/quality-control/<?= e((string) $check['id']) ?>/close">
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
            <div><dt>Cliente</dt><dd><?= e($check['client_name']) ?></dd></div>
            <div><dt>Contrato</dt><dd><?= e($check['contract_number']) ?></dd></div>
            <div><dt>OC cliente</dt><dd><?= e($check['po_number']) ?></dd></div>
            <div><dt>Controlado por</dt><dd><?= e($check['checked_by_name'] ?? '-') ?></dd></div>
            <div><dt>Fecha control</dt><dd><?= e(format_datetime($check['checked_at'])) ?></dd></div>
            <div><dt>Confirmado</dt><dd><?= e(format_datetime($check['confirmed_at'])) ?></dd></div>
            <div><dt>Etapa producción</dt><dd><?= e(production_stage_label($check['production_stage'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($check['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('quality_control_checks', (int) $check['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Ítems</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Código</th><th>Descripción</th><th>Talle</th><th>Color</th><th>Recibido</th><th>Aprobado</th><th>Rechazado</th><th>Reproceso</th><th>Verificación</th><th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($check['items'] as $item): ?>
                <tr>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['description']) ?><br><span class="muted"><?= e($item['notes']) ?></span></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e((string) $item['quantity_received']) ?></td>
                    <td><?= e((string) $item['quantity_approved']) ?></td>
                    <td><?= e((string) $item['quantity_rejected']) ?></td>
                    <td><?= e((string) $item['quantity_rework']) ?></td>
                    <td class="muted">
                        Modelo <?= !empty($item['model_ok']) ? 'OK' : 'No' ?> ·
                        Talle <?= !empty($item['size_ok']) ? 'OK' : 'No' ?> ·
                        Cant. <?= !empty($item['quantity_ok']) ? 'OK' : 'No' ?> ·
                        Conf. <?= !empty($item['sewing_ok']) ? 'OK' : 'No' ?> ·
                        Term. <?= !empty($item['finishing_ok']) ? 'OK' : 'No' ?>
                    </td>
                    <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(quality_control_item_status_label($item['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (can('documents.transition') && ($check['status'] ?? '') === 'draft'): ?>
    <section class="panel">
        <h2>Registrar resultados</h2>
        <form method="post" action="/quality-control/<?= e((string) $check['id']) ?>/results">
            <?= csrf_field() ?>
            <div class="form-grid compact">
                <label class="span-2">
                    <span>Observaciones generales</span>
                    <input type="text" name="notes" value="">
                </label>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Código</th><th>Recibido</th><th>Aprobado</th><th>Rechazado</th><th>Reproceso</th><th>Checks</th><th>Notas</th></tr></thead>
                    <tbody>
                    <?php foreach ($check['items'] as $index => $item): ?>
                        <tr>
                            <td><strong><?= e($item['item_code']) ?></strong></td>
                            <td><?= e((string) $item['quantity_received']) ?></td>
                            <td>
                                <input type="hidden" name="quality_control_check_item_id[]" value="<?= e((string) $item['id']) ?>">
                                <input type="number" step="0.01" min="0" max="<?= e((string) $item['quantity_received']) ?>" name="quantity_approved[]" value="<?= e((string) $item['quantity_received']) ?>">
                            </td>
                            <td><input type="number" step="0.01" min="0" max="<?= e((string) $item['quantity_received']) ?>" name="quantity_rejected[]" value="0"></td>
                            <td><input type="number" step="0.01" min="0" max="<?= e((string) $item['quantity_received']) ?>" name="quantity_rework[]" value="0"></td>
                            <td>
                                <?php foreach (['model_ok' => 'Modelo', 'size_ok' => 'Talle', 'quantity_ok' => 'Cantidad', 'sewing_ok' => 'Confección', 'finishing_ok' => 'Terminación'] as $field => $label): ?>
                                    <input type="hidden" name="<?= e($field) ?>[<?= e((string) $index) ?>]" value="0">
                                    <label class="checkbox-inline"><input type="checkbox" name="<?= e($field) ?>[<?= e((string) $index) ?>]" value="1" checked> <?= e($label) ?></label>
                                <?php endforeach; ?>
                            </td>
                            <td><input type="text" name="notes_item[]" value=""></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button type="submit" class="button">Registrar resultados</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Órdenes de reproceso</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Ítem</th><th>Cantidad</th><th>Costurero</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach (($check['rework_orders'] ?? []) as $order): ?>
                <tr>
                    <td><strong><?= e($order['rework_number']) ?></strong></td>
                    <td><?= e($order['item_code']) ?></td>
                    <td><?= e((string) $order['quantity']) ?></td>
                    <td><?= e($order['seamster_name'] ?? '-') ?></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(quality_rework_status_label($order['status'])) ?></span></td>
                    <td><a href="/quality-reworks/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($check['rework_orders'] ?? []) === []): ?>
                <tr><td colspan="6" class="empty">Sin reprocesos.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
