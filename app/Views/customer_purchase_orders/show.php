<section class="page-head">
    <div>
        <p class="eyebrow">Orden de compra cliente</p>
        <h1><?= e($order['po_number']) ?></h1>
        <p class="muted"><?= e($order['client_name']) ?> · Contrato <?= e($order['contract_number']) ?></p>
        <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(document_status_label($order['status'])) ?></span>
    </div>
    <div class="page-actions">
        <?php if (can('documents.edit') && ($order['status'] ?? '') === 'draft'): ?>
            <a class="button button--secondary" href="/customer-purchase-orders/<?= e((string) $order['id']) ?>/edit">Editar</a>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/customer-purchase-orders/<?= e((string) $order['id']) ?>/confirm"><?= csrf_field() ?><button class="button" type="submit">Confirmar</button></form>
        <?php endif; ?>
        <?php if (can('documents.create') && ($order['status'] ?? '') === 'confirmed' && array_sum(array_map(static fn ($item) => (float) $item['balance_quantity'], $balances)) > 0): ?>
            <a class="button" href="/production-orders/create?customer_purchase_order_id=<?= e((string) $order['id']) ?>">Crear orden de producción</a>
        <?php endif; ?>
        <?php if (can('documents.transition') && in_array(($order['status'] ?? ''), ['draft', 'confirmed'], true)): ?>
            <form method="post" action="/customer-purchase-orders/<?= e((string) $order['id']) ?>/cancel" data-confirm="Anular esta OC cliente?"><?= csrf_field() ?><button class="button button--secondary" type="submit">Anular</button></form>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'confirmed'): ?>
            <form method="post" action="/customer-purchase-orders/<?= e((string) $order['id']) ?>/close"><?= csrf_field() ?><button class="button button--secondary" type="submit">Cerrar</button></form>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($order['client_name']) ?></dd></div>
            <div><dt>Dependencia</dt><dd><?= e($order['dependency_name']) ?></dd></div>
            <div><dt>Contrato</dt><dd><a href="/contracts/<?= e((string) $order['contract_id']) ?>"><?= e($order['contract_number']) ?></a></dd></div>
            <div><dt>Fecha OC</dt><dd><?= e($order['po_date']) ?></dd></div>
            <div><dt>Recepción</dt><dd><?= e($order['received_date']) ?></dd></div>
            <div><dt>Contacto facturación</dt><dd><?= e($order['billing_contact_name']) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($order['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('customer_purchase_orders', (int) $order['id']),
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
                <th>Descripción</th>
                <th>Cantidad OC</th>
                <th>Producido/asignado</th>
                <th>Saldo producción</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($balances as $item): ?>
                <tr>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e((string) $item['quantity']) ?> <?= e($item['unit']) ?></td>
                    <td><?= e((string) $item['produced_quantity']) ?></td>
                    <td><?= e((string) $item['balance_quantity']) ?></td>
                    <td><span class="<?= (float) $item['balance_quantity'] > 0.0001 ? 'text-warning' : 'text-success' ?>"><?= (float) $item['balance_quantity'] > 0.0001 ? 'Pendiente' : 'Completo' ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Órdenes de producción</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Etapa</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($productionOrders as $production): ?>
                <tr>
                    <td><?= e($production['production_number']) ?></td>
                    <td><?= e(production_stage_label($production['production_stage'])) ?></td>
                    <td><span class="<?= e(status_badge_class($production['status'])) ?>"><?= e(document_status_label($production['status'])) ?></span></td>
                    <td><a href="/production-orders/<?= e((string) $production['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($productionOrders === []): ?>
                <tr><td colspan="4" class="empty">No hay órdenes de producción generadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
