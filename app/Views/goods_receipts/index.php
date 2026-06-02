<section class="page-head">
    <div>
        <p class="eyebrow">Compras</p>
        <h1>Recepciones de insumos</h1>
        <p class="muted">Ingreso, codificación y trazabilidad de insumos comprados.</p>
    </div>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Recepción, OC, proveedor, remito o factura">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['draft' => 'Borrador', 'confirmed' => 'Confirmada', 'cancelled' => 'Anulada', 'closed' => 'Cerrada'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button type="submit" class="button button--secondary">Filtrar</button>
        </div>
    </div>
</form>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Recepción</th>
                <th>OC proveedor</th>
                <th>Proveedor</th>
                <th>Fecha</th>
                <th>Aceptado</th>
                <th>Rechazado</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($receipts as $receipt): ?>
                <tr>
                    <td><strong><?= e($receipt['receipt_number']) ?></strong></td>
                    <td><a href="/supplier-purchase-orders/<?= e((string) $receipt['supplier_purchase_order_id']) ?>"><?= e($receipt['supplier_po_number']) ?></a></td>
                    <td><?= e($receipt['supplier_name']) ?></td>
                    <td><?= e(format_datetime($receipt['received_at'] ?? null)) ?></td>
                    <td><?= e((string) ($receipt['total_accepted'] ?? 0)) ?></td>
                    <td><?= e((string) ($receipt['total_rejected'] ?? 0)) ?></td>
                    <td><span class="<?= e(status_badge_class($receipt['status'])) ?>"><?= e(goods_receipt_status_label($receipt['status'])) ?></span></td>
                    <td class="actions"><a href="/goods-receipts/<?= e((string) $receipt['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($receipts === []): ?>
                <tr><td colspan="8" class="empty">No hay recepciones registradas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
