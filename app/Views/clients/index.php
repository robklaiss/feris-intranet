<section class="page-head">
    <div>
        <p class="eyebrow">Módulo base</p>
        <h1>Clientes</h1>
    </div>
    <?php if (can('clients.manage')): ?>
        <a href="/clients/create" class="button">Nuevo cliente</a>
    <?php endif; ?>
</section>

<form class="panel filters" method="get">
    <div class="form-grid compact">
        <label>
            <span>Buscar</span>
            <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Nombre o RUC">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
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
                <th>Cliente</th>
                <th>RUC</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><a href="/clients/<?= e((string) $client['id']) ?>"><?= e($client['name']) ?></a></td>
                    <td><?= e($client['tax_id']) ?></td>
                    <td><span class="badge"><?= e($client['status']) ?></span></td>
                    <td class="actions">
                        <?php if (can('clients.manage')): ?>
                            <a href="/clients/<?= e((string) $client['id']) ?>/edit">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($clients === []): ?>
                <tr><td colspan="4" class="empty">No hay clientes cargados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
