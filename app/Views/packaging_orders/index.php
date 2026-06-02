<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Empaquetado</h1>
        <p class="muted">Órdenes creadas desde controles de calidad aprobados.</p>
    </div>
</section>

<section class="panel">
    <form method="get" class="filters">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Número, calidad, producción, cliente">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['draft', 'confirmed', 'packed', 'cancelled', 'closed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(packaging_order_status_label($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Calidad</th><th>Producción</th><th>Cliente</th><th>Estado</th><th>Empacado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['packaging_number']) ?></strong></td>
                    <td><a href="/quality-control/<?= e((string) $order['quality_control_check_id']) ?>"><?= e($order['qc_number']) ?></a></td>
                    <td><a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a></td>
                    <td><?= e($order['client_name']) ?></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(packaging_order_status_label($order['status'])) ?></span></td>
                    <td><?= e(format_datetime($order['packed_at'])) ?></td>
                    <td><a href="/packaging-orders/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="7" class="empty">Sin órdenes de empaquetado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
