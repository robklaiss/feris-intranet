<section class="page-head">
    <div>
        <p class="eyebrow">Recepción de insumos</p>
        <h1><?= e($receipt['receipt_number']) ?></h1>
        <p class="muted">OC proveedor <a href="/supplier-purchase-orders/<?= e((string) $receipt['supplier_purchase_order_id']) ?>"><?= e($receipt['supplier_po_number']) ?></a> · <?= e($receipt['supplier_name']) ?></p>
        <span class="<?= e(status_badge_class($receipt['status'])) ?>"><?= e(goods_receipt_status_label($receipt['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/goods-receipts" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && ($receipt['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/goods-receipts/<?= e((string) $receipt['id']) ?>/confirm"><?= csrf_field() ?><button type="submit" class="button">Confirmar e ingresar</button></form>
            <form method="post" action="/goods-receipts/<?= e((string) $receipt['id']) ?>/cancel" data-confirm="Anular esta recepción en borrador?"><?= csrf_field() ?><button type="submit" class="button button--secondary">Anular</button></form>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos de recepción</h2>
        <dl class="detail-list">
            <div><dt>Proveedor</dt><dd><?= e($receipt['supplier_name']) ?> · <?= e($receipt['supplier_ruc'] ?? '-') ?></dd></div>
            <div><dt>Fecha</dt><dd><?= e(format_datetime($receipt['received_at'] ?? null)) ?></dd></div>
            <div><dt>Remito proveedor</dt><dd><?= e($receipt['delivery_note_number'] ?? '-') ?></dd></div>
            <div><dt>Factura proveedor</dt><dd><?= e($receipt['invoice_number'] ?? '-') ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($receipt['notes'] ?? '-')) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('goods_receipts', (int) $receipt['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Ítems recibidos</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Descripción</th>
                <th>Ordenado</th>
                <th>Previo</th>
                <th>Recibido</th>
                <th>Aceptado</th>
                <th>Rechazado</th>
                <th>Código interno</th>
                <th>Lote</th>
                <th>Ubicación</th>
                <th>Costo</th>
                <th>Calidad</th>
                <th>Inventario</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($receipt['items'] as $item): ?>
                <tr>
                    <td><strong><?= e($item['description']) ?></strong><br><span class="muted"><?= e($item['material_type']) ?> · <?= e($item['unit']) ?></span></td>
                    <td><?= e((string) $item['ordered_quantity']) ?></td>
                    <td><?= e((string) $item['previously_received_quantity']) ?></td>
                    <td><?= e((string) $item['received_quantity']) ?></td>
                    <td><?= e((string) $item['accepted_quantity']) ?></td>
                    <td><?= e((string) $item['rejected_quantity']) ?></td>
                    <td><?= e($item['internal_code'] ?? '-') ?></td>
                    <td><?= e($item['lot_number'] ?? '-') ?></td>
                    <td><?= e($item['location'] ?? '-') ?></td>
                    <td><?= e((string) ($item['cost'] ?? '-')) ?></td>
                    <td><span class="<?= e(status_badge_class($item['quality_status'])) ?>"><?= e(goods_receipt_quality_status_label($item['quality_status'])) ?></span></td>
                    <td>
                        <?php if (!empty($item['raw_material_inventory_id'])): ?>
                            <a href="/raw-materials/<?= e((string) $item['raw_material_inventory_id']) ?>"><?= e($item['inventory_internal_code'] ?? $item['internal_code']) ?></a>
                        <?php else: ?>
                            <span class="muted">Sin ingreso</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
