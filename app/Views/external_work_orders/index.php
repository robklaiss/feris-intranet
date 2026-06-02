<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Trabajos externos</h1>
        <p class="muted">Serigrafía y bordado con nota de envío, retorno y trazabilidad.</p>
    </div>
</section>

<section class="panel">
    <form method="get" class="filters">
        <label>Buscar
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Trabajo, nota, producción, corte, proveedor">
        </label>
        <label>Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['draft', 'sent', 'partially_returned', 'returned', 'cancelled', 'closed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(external_work_order_status_label($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="work_type">
                <option value="">Todos</option>
                <?php foreach (['embroidery', 'screen_printing', 'both', 'other'] as $type): ?>
                    <option value="<?= e($type) ?>" <?= ($filters['work_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(external_work_type_label($type)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button">Filtrar</button>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Número</th>
                <th>Tipo</th>
                <th>Proveedor</th>
                <th>Producción</th>
                <th>Corte</th>
                <th>Estado</th>
                <th>Envío</th>
                <th>Retorno esperado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['external_work_number']) ?></strong></td>
                    <td><?= e(external_work_type_label($order['work_type'])) ?></td>
                    <td><?= e($order['supplier_name'] ?? '-') ?></td>
                    <td><a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a></td>
                    <td><a href="/cutting-orders/<?= e((string) $order['cutting_order_id']) ?>"><?= e($order['cutting_number']) ?></a></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(external_work_order_status_label($order['status'])) ?></span></td>
                    <td><?= e(format_datetime($order['sent_at'])) ?></td>
                    <td><?= e($order['expected_return_date'] ?? '-') ?></td>
                    <td><a href="/external-work-orders/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="9" class="empty">No hay trabajos externos.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
