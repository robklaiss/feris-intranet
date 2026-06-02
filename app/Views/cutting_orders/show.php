<section class="page-head">
    <div>
        <p class="eyebrow">Orden de corte</p>
        <h1><?= e($order['cutting_number']) ?></h1>
        <p class="muted">
            Producción <a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a>
            · <?= e($order['client_name']) ?>
            · Contrato <?= e($order['contract_number']) ?>
            · OC cliente <?= e($order['po_number']) ?>
        </p>
        <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(cutting_order_status_label($order['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/cutting-orders" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/cutting-orders/<?= e((string) $order['id']) ?>/confirm" data-confirm="Confirmar y consumir materiales reservados?"><?= csrf_field() ?><button class="button" type="submit">Confirmar corte</button></form>
            <form method="post" action="/cutting-orders/<?= e((string) $order['id']) ?>/cancel" data-confirm="Anular esta orden de corte en borrador?"><?= csrf_field() ?><button class="button button--secondary" type="submit">Anular</button></form>
        <?php endif; ?>
        <?php if (can('documents.transition') && in_array(($order['status'] ?? ''), ['confirmed', 'in_progress'], true)): ?>
            <form method="post" action="/cutting-orders/<?= e((string) $order['id']) ?>/complete" data-confirm="Completar esta orden de corte?"><?= csrf_field() ?><button class="button" type="submit">Completar corte</button></form>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'completed'): ?>
            <form method="post" action="/cutting-orders/<?= e((string) $order['id']) ?>/close"><?= csrf_field() ?><button class="button button--secondary" type="submit">Cerrar</button></form>
        <?php endif; ?>
        <?php if (can('documents.create') && in_array(($order['status'] ?? ''), ['completed', 'closed'], true) && ($externalEligibleItems ?? []) !== []): ?>
            <a href="/cutting-orders/<?= e((string) $order['id']) ?>/external-work-orders/create" class="button">Enviar a serigrafía/bordado</a>
        <?php endif; ?>
        <?php if (can('documents.create') && in_array(($order['status'] ?? ''), ['completed', 'closed'], true) && ($sewingEligibleItems ?? []) !== []): ?>
            <a href="/cutting-orders/<?= e((string) $order['id']) ?>/sewing-orders/create" class="button">Crear orden de confección</a>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Producción</dt><dd><a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a></dd></div>
            <div><dt>Cliente</dt><dd><?= e($order['client_name']) ?></dd></div>
            <div><dt>Contrato</dt><dd><?= e($order['contract_number']) ?></dd></div>
            <div><dt>OC cliente</dt><dd><?= e($order['po_number']) ?></dd></div>
            <div><dt>Etapa producción</dt><dd><?= e(production_stage_label($order['production_stage'])) ?></dd></div>
            <div><dt>Fecha planificada</dt><dd><?= e($order['planned_date']) ?></dd></div>
            <div><dt>Responsable</dt><dd><?= e($order['cut_by']) ?></dd></div>
            <div><dt>Inicio</dt><dd><?= e(format_datetime($order['started_at'])) ?></dd></div>
            <div><dt>Fin</dt><dd><?= e(format_datetime($order['completed_at'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($order['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('cutting_orders', (int) $order['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Ítems a cortar</h2>
    <form method="post" action="/cutting-orders/<?= e((string) $order['id']) ?>/complete">
        <?= csrf_field() ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Código</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Talle</th>
                    <th>Color</th>
                    <th>Tela</th>
                    <th>Medidas</th>
                    <th>A cortar</th>
                    <th>Cortado</th>
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
                        <td><?= e($item['fabric']) ?></td>
                        <td><?= e($item['measurements']) ?></td>
                        <td><?= e((string) $item['quantity_to_cut']) ?> <?= e($item['unit']) ?></td>
                        <td>
                            <?php if (in_array(($order['status'] ?? ''), ['confirmed', 'in_progress'], true) && can('documents.transition')): ?>
                                <input type="hidden" name="cutting_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                                <input type="number" step="0.01" min="0.01" max="<?= e((string) $item['quantity_to_cut']) ?>" name="quantity_cut[]" value="<?= e((string) ($item['quantity_cut'] ?: $item['quantity_to_cut'])) ?>">
                            <?php else: ?>
                                <?= e((string) $item['quantity_cut']) ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(cutting_item_status_label($item['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (in_array(($order['status'] ?? ''), ['confirmed', 'in_progress'], true) && can('documents.transition')): ?>
            <div class="form-actions">
                <button type="submit" class="button">Guardar cantidades y completar</button>
            </div>
        <?php endif; ?>
    </form>
</section>

<section class="panel">
    <h2>Trabajos externos asociados</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Tipo</th><th>Proveedor</th><th>Estado</th><th>Nota envío</th><th>Retorno esperado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach (($externalWorkOrders ?? []) as $externalOrder): ?>
                <tr>
                    <td><strong><?= e($externalOrder['external_work_number']) ?></strong></td>
                    <td><?= e(external_work_type_label($externalOrder['work_type'])) ?></td>
                    <td><?= e($externalOrder['supplier_name'] ?? '-') ?></td>
                    <td><span class="<?= e(status_badge_class($externalOrder['status'])) ?>"><?= e(external_work_order_status_label($externalOrder['status'])) ?></span></td>
                    <td><?= e($externalOrder['send_note_number'] ?? '-') ?></td>
                    <td><?= e($externalOrder['expected_return_date'] ?? '-') ?></td>
                    <td><a href="/external-work-orders/<?= e((string) $externalOrder['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($externalWorkOrders ?? []) === []): ?>
                <tr><td colspan="7" class="empty">Sin trabajos externos asociados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Órdenes de confección asociadas</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Costurero</th><th>Estado</th><th>Asignación</th><th>Finalización esperada</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach (($sewingOrders ?? []) as $sewingOrder): ?>
                <tr>
                    <td><strong><?= e($sewingOrder['sewing_number']) ?></strong></td>
                    <td><?= e($sewingOrder['seamster_name']) ?></td>
                    <td><span class="<?= e(status_badge_class($sewingOrder['status'])) ?>"><?= e(sewing_order_status_label($sewingOrder['status'])) ?></span></td>
                    <td><?= e(format_datetime($sewingOrder['assigned_at'])) ?></td>
                    <td><?= e($sewingOrder['expected_completion_date'] ?? '-') ?></td>
                    <td><a href="/sewing-orders/<?= e((string) $sewingOrder['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($sewingOrders ?? []) === []): ?>
                <tr><td colspan="6" class="empty">Sin órdenes de confección asociadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Materiales reservados y consumidos</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Insumo</th>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Reservado</th>
                <th>Consumido</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order['materials'] as $material): ?>
                <tr>
                    <td><a href="/raw-materials/<?= e((string) $material['raw_material_inventory_id']) ?>"><?= e($material['internal_code']) ?></a></td>
                    <td><?= e($material['material_type']) ?></td>
                    <td><?= e($material['description']) ?></td>
                    <td><?= e((string) $material['reserved_quantity']) ?> <?= e($material['unit']) ?></td>
                    <td><?= e((string) $material['consumed_quantity']) ?> <?= e($material['unit']) ?></td>
                    <td><span class="<?= e(status_badge_class($material['status'])) ?>"><?= e(cutting_material_status_label($material['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($order['materials'] === []): ?>
                <tr><td colspan="6" class="empty">Sin materiales asociados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
