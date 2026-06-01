<section class="page-head">
    <div>
        <p class="eyebrow">Orden de producción</p>
        <h1><?= e($order['production_number']) ?></h1>
        <p class="muted"><?= e($order['client_name']) ?> · OC cliente <?= e($order['po_number']) ?> · Contrato <?= e($order['contract_number']) ?></p>
        <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(document_status_label($order['status'])) ?></span>
    </div>
    <div class="page-actions">
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/production-orders/<?= e((string) $order['id']) ?>/confirm"><?= csrf_field() ?><button class="button" type="submit">Confirmar</button></form>
        <?php endif; ?>
        <?php if (can('documents.transition') && in_array(($order['status'] ?? ''), ['draft', 'confirmed'], true)): ?>
            <form method="post" action="/production-orders/<?= e((string) $order['id']) ?>/cancel" data-confirm="Anular esta orden de producción?"><?= csrf_field() ?><button class="button button--secondary" type="submit">Anular</button></form>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'confirmed'): ?>
            <form method="post" action="/production-orders/<?= e((string) $order['id']) ?>/close"><?= csrf_field() ?><button class="button button--secondary" type="submit">Cerrar</button></form>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($order['client_name']) ?></dd></div>
            <div><dt>Contrato</dt><dd><a href="/contracts/<?= e((string) $order['contract_id']) ?>"><?= e($order['contract_number']) ?></a></dd></div>
            <div><dt>OC cliente</dt><dd><a href="/customer-purchase-orders/<?= e((string) $order['customer_purchase_order_id']) ?>"><?= e($order['po_number']) ?></a></dd></div>
            <div><dt>Dependencia</dt><dd><?= e($order['dependency_name']) ?></dd></div>
            <div><dt>Etapa</dt><dd><?= e(production_stage_label($order['production_stage'])) ?></dd></div>
            <div><dt>Inicio planificado</dt><dd><?= e($order['planned_start_date']) ?></dd></div>
            <div><dt>Fin planificado</dt><dd><?= e($order['planned_end_date']) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($order['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('production_orders', (int) $order['id']),
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
                <th>Cantidad</th>
                <th>Saldo</th>
                <th>Bordado</th>
                <th>Serigrafía</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['product_type']) ?></td>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e((string) $item['quantity']) ?> <?= e($item['unit']) ?></td>
                    <td><?= e((string) $item['balance_quantity']) ?></td>
                    <td><?= !empty($item['requires_embroidery']) ? 'Sí' : 'No' ?></td>
                    <td><?= !empty($item['requires_screen_printing']) ? 'Sí' : 'No' ?></td>
                    <td><?= e(production_stage_label($item['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
