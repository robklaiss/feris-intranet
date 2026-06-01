<section class="page-head">
    <div>
        <p class="eyebrow">Seguimiento</p>
        <h1>Reportes</h1>
        <p class="muted">Primera versión operativa con filtros por cliente, contrato e ID y exportación CSV/PDF.</p>
    </div>
</section>

<section class="stats-grid">
    <?php foreach ($summary as $label => $value): ?>
        <article class="stat-card stat-card--static">
            <span><?= e(ucfirst($label)) ?></span>
            <strong><?= e((string) $value) ?></strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel__header">
        <h2>Consulta</h2>
        <div class="actions-row">
            <a href="/reports/export?<?= e(http_build_query(['type' => $selectedType] + $filters + ['format' => 'csv'])) ?>" class="button button--secondary">Exportar CSV</a>
            <a href="/reports/export?<?= e(http_build_query(['type' => $selectedType] + $filters + ['format' => 'pdf'])) ?>" class="button button--secondary">Exportar PDF</a>
        </div>
    </div>

    <form method="get" action="/reports" class="form-grid">
        <label>
            <span>Reporte</span>
            <select name="type">
                <?php foreach ($types as $type => $label): ?>
                    <option value="<?= e($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Cliente</span>
            <select name="client_id">
                <option value="">Todos</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= e((string) $client['id']) ?>" <?= (string) $filters['client_id'] === (string) $client['id'] ? 'selected' : '' ?>><?= e($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Número de contrato</span>
            <input type="text" name="contract_number" value="<?= e($filters['contract_number']) ?>">
        </label>
        <label>
            <span>Número de ID</span>
            <input type="text" name="identifier_number" value="<?= e($filters['identifier_number']) ?>">
        </label>
        <div class="form-actions">
            <button type="submit" class="button">Aplicar filtros</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel__header">
        <h2><?= e($report['title']) ?></h2>
        <span class="muted"><?= e((string) count($report['rows'])) ?> registros</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <?php foreach ($report['columns'] as $column): ?>
                    <th><?= e($column['label']) ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <?php foreach ($report['columns'] as $column): ?>
                        <td>
                            <?php if (!empty($column['money'])): ?>
                                Gs. <?= e(money($row[$column['key']] ?? 0)) ?>
                            <?php else: ?>
                                <?= e((string) ($row[$column['key']] ?? '')) ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($report['rows'] === []): ?>
                <tr><td colspan="<?= e((string) count($report['columns'])) ?>" class="empty">No hay resultados para los filtros indicados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel__header">
        <h2>Audit log</h2>
        <span class="muted">Últimos eventos con usuario, estado y resumen operativo</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Documento</th>
                <th>Acción</th>
                <th>Transición</th>
                <th>Resumen</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($audit as $entry): ?>
                <tr>
                    <td><?= e($entry['created_at']) ?></td>
                    <td><?= e($entry['username'] ?: 'Sistema') ?></td>
                    <td>
                        <?= e($entry['document_type'] ?: $entry['entity_type']) ?>
                        #<?= e((string) $entry['entity_id']) ?>
                        <?php if (!empty($entry['document_number'])): ?>
                            <div class="muted"><?= e($entry['document_number']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e($entry['action']) ?></td>
                    <td><?= e(($entry['previous_state'] ?: '-') . ' -> ' . ($entry['new_state'] ?: '-')) ?></td>
                    <td><code><?= e((string) ($entry['payload_summary'] ?: $entry['changes'])) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
