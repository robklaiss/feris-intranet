<section class="page-head">
    <div>
        <p class="eyebrow">Orden de compra proveedor</p>
        <h1><?= e($order['supplier_po_number']) ?></h1>
        <p class="muted">Proveedor <?= e($order['supplier_name']) ?> · Pedido <a href="/purchase-requisitions/<?= e((string) $order['purchase_requisition_id']) ?>"><?= e($order['requisition_number']) ?></a></p>
        <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(supplier_purchase_order_status_label($order['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/supplier-purchase-orders" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/supplier-purchase-orders/<?= e((string) $order['id']) ?>/confirm"><?= csrf_field() ?><button type="submit" class="button">Confirmar</button></form>
        <?php endif; ?>
        <?php if (can('documents.transition') && !in_array(($order['status'] ?? ''), ['cancelled', 'closed'], true)): ?>
            <form method="post" action="/supplier-purchase-orders/<?= e((string) $order['id']) ?>/cancel" data-confirm="Anular esta orden de compra proveedor?"><?= csrf_field() ?><button type="submit" class="button button--secondary">Anular</button></form>
            <form method="post" action="/supplier-purchase-orders/<?= e((string) $order['id']) ?>/close"><?= csrf_field() ?><button type="submit" class="button button--secondary">Cerrar</button></form>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos ISO 9001</h2>
        <dl class="detail-list">
            <div><dt>Formulario ISO</dt><dd><?= e($order['iso_form_number'] ?? '-') ?></dd></div>
            <div><dt>Presupuesto aprobado</dt><dd><?= e($order['quote_number']) ?></dd></div>
            <div><dt>Solicitante</dt><dd><?= e((string) ($order['requested_by'] ?? '-')) ?></dd></div>
            <div><dt>Aprobador</dt><dd><?= e((string) ($order['approved_by'] ?? '-')) ?> · <?= e(format_datetime($order['approved_at'])) ?></dd></div>
            <div><dt>Fecha OC</dt><dd><?= e($order['order_date']) ?></dd></div>
            <div><dt>Entrega esperada</dt><dd><?= e($order['expected_delivery_date'] ?? '-') ?></dd></div>
            <div><dt>Pago</dt><dd><?= e($order['payment_terms'] ?? '-') ?></dd></div>
            <div><dt>Entrega</dt><dd><?= e($order['delivery_terms'] ?? '-') ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('supplier_purchase_orders', (int) $order['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Evidencia de compra</h2>
    <dl class="detail-list">
        <div><dt>Motivo de compra</dt><dd><?= nl2br(e($order['purchase_reason'] ?? '-')) ?></dd></div>
        <div><dt>Comparación proveedores</dt><dd><?= nl2br(e($order['supplier_comparison_summary'] ?? '-')) ?></dd></div>
        <div><dt>Especificaciones</dt><dd><?= nl2br(e($order['product_specifications'] ?? '-')) ?></dd></div>
        <div><dt>Requisitos de calidad</dt><dd><?= nl2br(e($order['quality_requirements'] ?? '-')) ?></dd></div>
        <div><dt>Observaciones</dt><dd><?= nl2br(e($order['notes'] ?? '-')) ?></dd></div>
    </dl>
</section>

<section class="panel">
    <h2>Ítems</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Descripción</th>
                <th>Unidad</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td>
                        <strong><?= e($item['description']) ?></strong><br>
                        <span class="muted"><?= e($item['required_material_type']) ?></span>
                    </td>
                    <td><?= e($item['unit']) ?></td>
                    <td><?= e((string) $item['quantity']) ?></td>
                    <td><?= e($order['currency']) ?> <?= e(money($item['unit_price'])) ?></td>
                    <td><?= e($order['currency']) ?> <?= e(money($item['total_price'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="summary-row">
        <span>Subtotal: <?= e($order['currency']) ?> <?= e(money($order['subtotal'])) ?></span>
        <span>Impuestos: <?= e($order['currency']) ?> <?= e(money($order['tax_amount'])) ?></span>
        <strong>Total: <?= e($order['currency']) ?> <?= e(money($order['total_amount'])) ?></strong>
    </div>
</section>
