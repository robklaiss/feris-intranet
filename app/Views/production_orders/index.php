<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Órdenes de producción</h1>
        <p class="muted">Órdenes generadas desde OC cliente confirmadas.</p>
    </div>
</section>

<section class="panel">
    <form method="get" class="filters">
        <label>Buscar
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Producción, OC, cliente o contrato">
        </label>
        <label>Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (document_statuses() as $status => $label): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Etapa
            <select name="production_stage">
                <option value="">Todas</option>
                <?php foreach (['pending', 'stock_pending', 'ready_for_stock_check', 'ready_for_cutting'] as $stage): ?>
                    <option value="<?= e($stage) ?>" <?= ($filters['production_stage'] ?? '') === $stage ? 'selected' : '' ?>><?= e(production_stage_label($stage)) ?></option>
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
                <th>OC cliente</th>
                <th>Cliente</th>
                <th>Contrato</th>
                <th>Etapa</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['production_number']) ?></strong></td>
                    <td><?= e($order['po_number']) ?></td>
                    <td><?= e($order['client_name']) ?></td>
                    <td><?= e($order['contract_number']) ?></td>
                    <td><?= e(production_stage_label($order['production_stage'])) ?></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(document_status_label($order['status'])) ?></span></td>
                    <td><a href="/production-orders/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="7" class="empty">No hay órdenes de producción.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
