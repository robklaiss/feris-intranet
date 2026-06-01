<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Órdenes de compra cliente</h1>
        <p class="muted">OC manuales vinculadas a contratos confirmados e ítems técnicos confirmados.</p>
    </div>
</section>

<section class="panel">
    <form method="get" class="filters">
        <label>Buscar
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="OC, cliente, contrato o dependencia">
        </label>
        <label>Estado
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (document_statuses() as $status => $label): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Cliente
            <select name="client_id">
                <option value="">Todos</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= e((string) $client['id']) ?>" <?= (string) ($filters['client_id'] ?? '') === (string) $client['id'] ? 'selected' : '' ?>><?= e($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Contrato
            <select name="contract_id">
                <option value="">Todos</option>
                <?php foreach ($contracts as $contract): ?>
                    <option value="<?= e((string) $contract['id']) ?>" <?= (string) ($filters['contract_id'] ?? '') === (string) $contract['id'] ? 'selected' : '' ?>><?= e($contract['contract_number']) ?></option>
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
                <th>Número OC</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Contrato</th>
                <th>Dependencia</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['po_number']) ?></strong></td>
                    <td><?= e($order['po_date']) ?></td>
                    <td><?= e($order['client_name']) ?></td>
                    <td><?= e($order['contract_number']) ?></td>
                    <td><?= e($order['dependency_name']) ?></td>
                    <td><span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(document_status_label($order['status'])) ?></span></td>
                    <td><a href="/customer-purchase-orders/<?= e((string) $order['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="7" class="empty">No hay órdenes de compra cliente.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
