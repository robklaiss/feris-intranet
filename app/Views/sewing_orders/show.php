<section class="page-head">
    <div>
        <p class="eyebrow">Orden de confección</p>
        <h1><?= e($order['sewing_number']) ?></h1>
        <p class="muted">
            Costurero <a href="/seamsters/<?= e((string) $order['seamster_id']) ?>"><?= e($order['seamster_name']) ?></a>
            · Producción <a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a>
            · Corte <a href="/cutting-orders/<?= e((string) $order['cutting_order_id']) ?>"><?= e($order['cutting_number']) ?></a>
            <?php if (!empty($order['external_work_order_id'])): ?>
                · Externo <a href="/external-work-orders/<?= e((string) $order['external_work_order_id']) ?>"><?= e($order['external_work_number']) ?></a>
            <?php endif; ?>
        </p>
        <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(sewing_order_status_label($order['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/sewing-orders" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/sewing-orders/<?= e((string) $order['id']) ?>/confirm" data-confirm="Confirmar esta orden de confección?">
                <?= csrf_field() ?>
                <button type="submit" class="button">Confirmar</button>
            </form>
            <form method="post" action="/sewing-orders/<?= e((string) $order['id']) ?>/cancel" data-confirm="Cancelar esta orden de confección en borrador?">
                <?= csrf_field() ?>
                <button type="submit" class="button button--secondary">Anular</button>
            </form>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'completed'): ?>
            <form method="post" action="/sewing-orders/<?= e((string) $order['id']) ?>/close">
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
            <div><dt>Contrato</dt><dd><?= e($order['contract_number']) ?></dd></div>
            <div><dt>OC cliente</dt><dd><?= e($order['po_number']) ?></dd></div>
            <div><dt>Retorno externo</dt><dd><?= e($order['receipt_number'] ?? '-') ?></dd></div>
            <div><dt>Fecha asignación</dt><dd><?= e(format_datetime($order['assigned_at'])) ?></dd></div>
            <div><dt>Finalización esperada</dt><dd><?= e($order['expected_completion_date'] ?? '-') ?></dd></div>
            <div><dt>Inicio</dt><dd><?= e(format_datetime($order['started_at'])) ?></dd></div>
            <div><dt>Fin</dt><dd><?= e(format_datetime($order['completed_at'])) ?></dd></div>
            <div><dt>Etapa producción</dt><dd><?= e(production_stage_label($order['production_stage'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($order['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('sewing_orders', (int) $order['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Ítems</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Código</th>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Talle</th>
                <th>Color</th>
                <th>Asignado</th>
                <th>Confeccionado</th>
                <th>Rechazado</th>
                <th>Pendiente</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['product_type']) ?></td>
                    <td><?= e($item['description']) ?><br><span class="muted"><?= e($item['notes']) ?></span></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e((string) $item['quantity_assigned']) ?> <?= e($item['unit']) ?></td>
                    <td><?= e((string) $item['quantity_completed']) ?></td>
                    <td><?= e((string) $item['quantity_rejected']) ?></td>
                    <td><?= e((string) $item['quantity_pending']) ?></td>
                    <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(sewing_item_status_label($item['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (can('documents.transition') && in_array(($order['status'] ?? ''), ['confirmed', 'in_progress', 'partially_completed'], true)): ?>
    <section class="panel">
        <h2>Registrar avance</h2>
        <form method="post" action="/sewing-orders/<?= e((string) $order['id']) ?>/progress">
            <?= csrf_field() ?>
            <div class="form-grid compact">
                <label>
                    <span>Fecha</span>
                    <input type="date" name="progress_date" value="<?= e(date('Y-m-d')) ?>">
                </label>
                <label class="span-2">
                    <span>Observaciones</span>
                    <input type="text" name="notes" value="">
                </label>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Código</th><th>Pendiente</th><th>Confeccionado</th><th>Rechazado</th><th>Notas</th></tr></thead>
                    <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <?php if ((float) $item['quantity_pending'] <= 0.0001) {
                            continue;
                        } ?>
                        <tr>
                            <td><strong><?= e($item['item_code']) ?></strong></td>
                            <td><?= e((string) $item['quantity_pending']) ?> <?= e($item['unit']) ?></td>
                            <td>
                                <input type="hidden" name="sewing_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                                <input type="number" step="0.01" min="0" max="<?= e((string) $item['quantity_pending']) ?>" name="quantity_completed[]" value="0">
                            </td>
                            <td><input type="number" step="0.01" min="0" max="<?= e((string) $item['quantity_pending']) ?>" name="quantity_rejected[]" value="0"></td>
                            <td><input type="text" name="notes_item[]" value=""></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button type="submit" class="button">Registrar avance</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Registros de avance</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fecha</th><th>Ítem</th><th>Confeccionado</th><th>Rechazado</th><th>Usuario</th><th>Notas</th></tr></thead>
            <tbody>
            <?php foreach (($order['progress_entries'] ?? []) as $entry): ?>
                <tr>
                    <td><?= e($entry['progress_date']) ?></td>
                    <td><?= e($entry['item_code'] ?? '-') ?></td>
                    <td><?= e((string) $entry['quantity_completed']) ?></td>
                    <td><?= e((string) $entry['quantity_rejected']) ?></td>
                    <td><?= e($entry['created_by_name'] ?? '-') ?></td>
                    <td><?= e($entry['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($order['progress_entries'] ?? []) === []): ?>
                <tr><td colspan="6" class="empty">Sin avances registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
