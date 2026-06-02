<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Órdenes de corte</h1>
        <p class="muted">Corte trazado desde producción con stock reservado.</p>
    </div>
</section>

<section class="panel">
    <form method="get" class="filters">
        <label>Buscar
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Corte, producción, cliente o contrato">
        </label>
        <label>Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['draft', 'confirmed', 'in_progress', 'completed', 'cancelled', 'closed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(cutting_order_status_label($status)) ?></option>
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
                <th>Producción</th>
                <th>Cliente</th>
                <th>Contrato</th>
                <th>Estado</th>
                <th>Fecha planificada</th>
                <th>Responsable</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['cutting_number']) ?></strong></td>
                    <td><a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a></td>
                    <td><?= e($order['client_name']) ?></td>
                    <td><?= e($order['contract_number']) ?></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(cutting_order_status_label($order['status'])) ?></span></td>
                    <td><?= e($order['planned_date']) ?></td>
                    <td><?= e($order['cut_by']) ?></td>
                    <td><a href="/cutting-orders/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="8" class="empty">No hay órdenes de corte.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
