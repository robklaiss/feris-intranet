<section class="page-head">
    <div>
        <p class="eyebrow">Producción textil</p>
        <h1>Control de calidad</h1>
    </div>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Número, producción, confección, retorno, cliente o contrato">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['draft' => 'Borrador', 'approved' => 'Aprobado', 'partially_approved' => 'Aprobado parcial', 'rejected' => 'Rechazado', 'rework_required' => 'Reproceso', 'closed' => 'Cerrado', 'cancelled' => 'Anulado'] as $value => $label): ?>
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
                <th>Producción</th>
                <th>Origen</th>
                <th>Cliente</th>
                <th>Contrato</th>
                <th>Estado</th>
                <th>Fecha control</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td><strong><?= e($check['qc_number']) ?></strong></td>
                    <td><a href="/production-orders/<?= e((string) $check['production_order_id']) ?>"><?= e($check['production_number']) ?></a></td>
                    <td>
                        <?php if (!empty($check['sewing_order_id'])): ?>
                            <a href="/sewing-orders/<?= e((string) $check['sewing_order_id']) ?>"><?= e($check['sewing_number']) ?></a>
                        <?php elseif (!empty($check['external_work_receipt_id'])): ?>
                            <?= e($check['receipt_number']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= e($check['client_name']) ?></td>
                    <td><?= e($check['contract_number']) ?></td>
                    <td><span class="<?= e(status_badge_class($check['status'])) ?>"><?= e(quality_control_status_label($check['status'])) ?></span></td>
                    <td><?= e(format_datetime($check['checked_at'])) ?></td>
                    <td><a href="/quality-control/<?= e((string) $check['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($checks === []): ?>
                <tr><td colspan="8" class="empty">No hay controles de calidad.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
