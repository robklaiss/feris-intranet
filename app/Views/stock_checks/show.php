<section class="page-head">
    <div>
        <p class="eyebrow">Verificación de stock</p>
        <h1><?= e($check['check_number']) ?></h1>
        <p class="muted">Orden de producción <a href="/production-orders/<?= e((string) $check['production_order_id']) ?>"><?= e($check['production_number']) ?></a></p>
        <span class="<?= e(status_badge_class($check['status'])) ?>"><?= e(stock_check_status_label($check['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/production-orders/<?= e((string) $check['production_order_id']) ?>" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && ($check['status'] ?? '') === 'sufficient'): ?>
            <form method="post" action="/stock-checks/<?= e((string) $check['id']) ?>/reserve"><?= csrf_field() ?><button class="button" type="submit">Reservar stock</button></form>
        <?php endif; ?>
        <?php if (can('documents.transition') && in_array(($check['status'] ?? ''), ['draft', 'sufficient', 'insufficient', 'reserved'], true)): ?>
            <form method="post" action="/stock-checks/<?= e((string) $check['id']) ?>/cancel" data-confirm="Anular esta verificación y liberar reservas activas?"><?= csrf_field() ?><button class="button button--secondary" type="submit">Anular</button></form>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($check['client_name']) ?></dd></div>
            <div><dt>Estado OP</dt><dd><?= e(document_status_label($check['production_order_status'])) ?></dd></div>
            <div><dt>Etapa OP</dt><dd><?= e(production_stage_label($check['production_stage'])) ?></dd></div>
            <div><dt>Fecha verificación</dt><dd><?= e(format_datetime($check['checked_at'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($check['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('stock_checks', (int) $check['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Resultado</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Requerimiento</th>
                <th>Insumo</th>
                <th>Requerido</th>
                <th>Disponible libre</th>
                <th>Reservado</th>
                <th>Faltante</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($check['items'] as $item): ?>
                <tr>
                    <td>
                        <strong><?= e($item['required_material_type']) ?></strong><br>
                        <span class="muted"><?= e($item['required_description']) ?></span>
                    </td>
                    <td>
                        <?php if (!empty($item['raw_material_inventory_id'])): ?>
                            <a href="/raw-materials/<?= e((string) $item['raw_material_inventory_id']) ?>"><?= e($item['internal_code']) ?></a>
                        <?php else: ?>
                            <span class="text-warning">Sin insumo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) $item['required_quantity']) ?> <?= e($item['required_unit']) ?></td>
                    <td><?= e((string) $item['available_quantity']) ?></td>
                    <td><?= e((string) $item['reserved_quantity']) ?></td>
                    <td><?= e((string) $item['missing_quantity']) ?></td>
                    <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(stock_check_status_label($item['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
