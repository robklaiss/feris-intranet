<section class="page-head">
    <div>
        <p class="eyebrow">Compras</p>
        <h1>Órdenes de compra proveedor</h1>
        <p class="muted">Órdenes generadas desde presupuestos aprobados, listas para recepción futura.</p>
    </div>
    <a href="/purchase-requisitions" class="button button--secondary">Pedidos</a>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="OC, ISO, proveedor, pedido u OP">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['draft', 'confirmed', 'sent', 'partially_received', 'received', 'cancelled', 'closed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(supplier_purchase_order_status_label($status)) ?></option>
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
                <th>OC proveedor</th>
                <th>Proveedor</th>
                <th>Pedido</th>
                <th>Fecha</th>
                <th>Total</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <strong><?= e($order['supplier_po_number']) ?></strong><br>
                        <span class="muted"><?= e($order['iso_form_number'] ?? '') ?></span>
                    </td>
                    <td><?= e($order['supplier_name']) ?></td>
                    <td>
                        <a href="/purchase-requisitions/<?= e((string) $order['purchase_requisition_id']) ?>"><?= e($order['requisition_number']) ?></a><br>
                        <span class="muted"><?= e($order['production_number']) ?></span>
                    </td>
                    <td><?= e($order['order_date']) ?></td>
                    <td><?= e($order['currency']) ?> <?= e(money($order['total_amount'])) ?></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(supplier_purchase_order_status_label($order['status'])) ?></span></td>
                    <td class="actions"><a href="/supplier-purchase-orders/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="7" class="empty">No hay órdenes de compra proveedor.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
