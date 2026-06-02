<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Confección</h1>
    </div>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Número, costurero, producción, cliente o contrato">
        </label>
        <label>
            <span>Costurero</span>
            <select name="seamster_id">
                <option value="">Todos</option>
                <?php foreach ($seamsters as $seamster): ?>
                    <option value="<?= e((string) $seamster['id']) ?>" <?= (string) ($filters['seamster_id'] ?? '') === (string) $seamster['id'] ? 'selected' : '' ?>><?= e($seamster['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['draft' => 'Borrador', 'confirmed' => 'Confirmada', 'in_progress' => 'En proceso', 'partially_completed' => 'Avance parcial', 'completed' => 'Completada', 'closed' => 'Cerrada', 'cancelled' => 'Anulada'] as $value => $label): ?>
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
                <th>Número</th>
                <th>Costurero</th>
                <th>Producción</th>
                <th>Cliente</th>
                <th>Contrato</th>
                <th>Estado</th>
                <th>Fecha asignación</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['sewing_number']) ?></strong></td>
                    <td><?= e($order['seamster_name']) ?></td>
                    <td><a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a></td>
                    <td><?= e($order['client_name']) ?></td>
                    <td><?= e($order['contract_number']) ?></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(sewing_order_status_label($order['status'])) ?></span></td>
                    <td><?= e(format_datetime($order['assigned_at'])) ?></td>
                    <td><a href="/sewing-orders/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="8" class="empty">No hay órdenes de confección.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
