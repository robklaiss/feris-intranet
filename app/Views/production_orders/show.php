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
        <?php if (can('documents.create') && ($order['status'] ?? '') === 'confirmed'): ?>
            <a class="button" href="/production-orders/<?= e((string) $order['id']) ?>/stock-checks/create">Verificar stock</a>
        <?php endif; ?>
        <?php if (can('documents.create') && ($order['status'] ?? '') === 'confirmed' && ($order['production_stage'] ?? '') === 'ready_for_cutting' && $activeReservations !== []): ?>
            <a class="button" href="/production-orders/<?= e((string) $order['id']) ?>/cutting-orders/create">Crear orden de corte</a>
        <?php endif; ?>
        <?php if (can('documents.transition') && in_array(($order['status'] ?? ''), ['draft', 'confirmed'], true)): ?>
            <form method="post" action="/production-orders/<?= e((string) $order['id']) ?>/cancel" data-confirm="Anular esta orden de producción?"><?= csrf_field() ?><button class="button button--secondary" type="submit">Anular</button></form>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'confirmed'): ?>
            <form method="post" action="/production-orders/<?= e((string) $order['id']) ?>/close"><?= csrf_field() ?><button class="button button--secondary" type="submit">Cerrar</button></form>
        <?php endif; ?>
    </div>
</section>

<?php if (($order['status'] ?? '') === 'confirmed' && ($order['production_stage'] ?? '') !== 'ready_for_cutting' && $activeReservations === []): ?>
    <section class="panel">
        <p class="text-warning">Esta orden todavía no tiene stock reservado suficiente para crear corte.</p>
    </section>
<?php endif; ?>

<section class="grid-two">
    <article class="panel">
        <h2>Verificaciones de stock</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Número</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($stockChecks as $check): ?>
                    <tr>
                        <td><strong><?= e($check['check_number']) ?></strong></td>
                        <td><span class="<?= e(status_badge_class($check['status'])) ?>"><?= e(stock_check_status_label($check['status'])) ?></span></td>
                        <td><?= e(format_datetime($check['checked_at'])) ?></td>
                        <td><a href="/stock-checks/<?= e((string) $check['id']) ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($stockChecks === []): ?>
                    <tr><td colspan="4" class="empty">Sin verificaciones de stock.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
    <article class="panel">
        <h2>Reservas activas</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Insumo</th><th>Item</th><th>Cantidad</th></tr></thead>
                <tbody>
                <?php foreach ($activeReservations as $reservation): ?>
                    <tr>
                        <td><?= e($reservation['internal_code']) ?></td>
                        <td><?= e($reservation['item_code']) ?></td>
                        <td><?= e((string) $reservation['reserved_quantity']) ?> <?= e($reservation['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($activeReservations === []): ?>
                    <tr><td colspan="3" class="empty">Sin reservas activas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="panel">
    <h2>Órdenes de corte</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Estado</th><th>Fecha planificada</th><th>Responsable</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($cuttingOrders as $cuttingOrder): ?>
                <tr>
                    <td><strong><?= e($cuttingOrder['cutting_number']) ?></strong></td>
                    <td><span class="<?= e(status_badge_class($cuttingOrder['status'])) ?>"><?= e(cutting_order_status_label($cuttingOrder['status'])) ?></span></td>
                    <td><?= e($cuttingOrder['planned_date']) ?></td>
                    <td><?= e($cuttingOrder['cut_by']) ?></td>
                    <td><a href="/cutting-orders/<?= e((string) $cuttingOrder['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($cuttingOrders === []): ?>
                <tr><td colspan="5" class="empty">Sin órdenes de corte.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
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
