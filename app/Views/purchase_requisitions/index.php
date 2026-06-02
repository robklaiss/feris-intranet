<section class="page-head">
    <div>
        <p class="eyebrow">Compras</p>
        <h1>Pedidos de presupuesto</h1>
        <p class="muted">Faltantes de stock convertidos en solicitudes a proveedores.</p>
    </div>
    <a href="/supplier-purchase-orders" class="button button--secondary">OC proveedor</a>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Pedido, stock check, OP o cliente">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['requested', 'quoted', 'approved', 'cancelled', 'closed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(purchase_requisition_status_label($status)) ?></option>
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
                <th>Pedido</th>
                <th>Producción</th>
                <th>Faltantes</th>
                <th>Proveedores</th>
                <th>Presupuestos</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requisitions as $requisition): ?>
                <tr>
                    <td>
                        <strong><?= e($requisition['requisition_number']) ?></strong><br>
                        <span class="muted"><?= e($requisition['check_number']) ?></span>
                    </td>
                    <td>
                        <a href="/production-orders/<?= e((string) $requisition['production_order_id']) ?>"><?= e($requisition['production_number']) ?></a><br>
                        <span class="muted"><?= e($requisition['client_name'] ?? '') ?></span>
                    </td>
                    <td><?= e((string) $requisition['item_count']) ?></td>
                    <td><?= e((string) $requisition['supplier_request_count']) ?></td>
                    <td><?= e((string) $requisition['quote_count']) ?></td>
                    <td><span class="<?= e(status_badge_class($requisition['status'])) ?>"><?= e(purchase_requisition_status_label($requisition['status'])) ?></span></td>
                    <td class="actions"><a href="/purchase-requisitions/<?= e((string) $requisition['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($requisitions === []): ?>
                <tr><td colspan="7" class="empty">No hay pedidos de presupuesto.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
