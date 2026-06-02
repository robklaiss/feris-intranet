<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Reprocesos de calidad</h1>
    </div>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Número, control, producción, costurero, cliente o contrato">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['draft' => 'Borrador', 'assigned' => 'Asignado', 'completed' => 'Completado', 'closed' => 'Cerrado', 'cancelled' => 'Anulado'] as $value => $label): ?>
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
            <thead><tr><th>Número</th><th>Control</th><th>Producción</th><th>Ítem</th><th>Cantidad</th><th>Costurero</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['rework_number']) ?></strong></td>
                    <td><a href="/quality-control/<?= e((string) $order['quality_control_check_id']) ?>"><?= e($order['qc_number']) ?></a></td>
                    <td><a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a></td>
                    <td><?= e($order['item_code']) ?></td>
                    <td><?= e((string) $order['quantity']) ?></td>
                    <td><?= e($order['seamster_name'] ?? '-') ?></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(quality_rework_status_label($order['status'])) ?></span></td>
                    <td><a href="/quality-reworks/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="8" class="empty">No hay reprocesos.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
