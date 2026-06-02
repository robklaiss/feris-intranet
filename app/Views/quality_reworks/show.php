<section class="page-head">
    <div>
        <p class="eyebrow">Reproceso de calidad</p>
        <h1><?= e($order['rework_number']) ?></h1>
        <p class="muted">
            Control <a href="/quality-control/<?= e((string) $order['quality_control_check_id']) ?>"><?= e($order['qc_number']) ?></a>
            · Producción <a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a>
            <?php if (!empty($order['sewing_order_id'])): ?>
                · Confección <a href="/sewing-orders/<?= e((string) $order['sewing_order_id']) ?>"><?= e($order['sewing_number']) ?></a>
            <?php endif; ?>
        </p>
        <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(quality_rework_status_label($order['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/quality-reworks" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && in_array(($order['status'] ?? ''), ['draft', 'assigned'], true)): ?>
            <form method="post" action="/quality-reworks/<?= e((string) $order['id']) ?>/complete">
                <?= csrf_field() ?>
                <button type="submit" class="button">Completar</button>
            </form>
            <form method="post" action="/quality-reworks/<?= e((string) $order['id']) ?>/cancel" data-confirm="Anular este reproceso?">
                <?= csrf_field() ?>
                <button type="submit" class="button button--secondary">Anular</button>
            </form>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'completed'): ?>
            <form method="post" action="/quality-reworks/<?= e((string) $order['id']) ?>/close">
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
            <div><dt>Ítem</dt><dd><?= e($order['item_code']) ?> · <?= e($order['description']) ?></dd></div>
            <div><dt>Talle / color</dt><dd><?= e($order['size']) ?> / <?= e($order['color']) ?></dd></div>
            <div><dt>Cantidad</dt><dd><?= e((string) $order['quantity']) ?></dd></div>
            <div><dt>Costurero</dt><dd><?= e($order['seamster_name'] ?? '-') ?></dd></div>
            <div><dt>Asignado a</dt><dd><?= e($order['assigned_to'] ?? '-') ?></dd></div>
            <div><dt>Vencimiento</dt><dd><?= e($order['due_date'] ?? '-') ?></dd></div>
            <div><dt>Completado</dt><dd><?= e(format_datetime($order['completed_at'])) ?></dd></div>
            <div><dt>Motivo</dt><dd><?= nl2br(e($order['reason'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($order['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('quality_rework_orders', (int) $order['id']),
        ]) ?>
    </article>
</section>
