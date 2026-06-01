<section class="page-head">
    <div>
        <p class="eyebrow">Clientes</p>
        <h1><?= e($title) ?></h1>
    </div>
    <a href="/clients" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="panel form-stack">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label>
            <span>Nombre / Razón social</span>
            <input type="text" name="name" value="<?= e($client['name']) ?>" required>
        </label>
        <label>
            <span>RUC</span>
            <input type="text" name="tax_id" value="<?= e($client['tax_id']) ?>">
        </label>
        <label class="span-2">
            <span>Direcciones</span>
            <textarea name="addresses" rows="4"><?= e($client['addresses']) ?></textarea>
        </label>
        <label class="span-2">
            <span>Contactos</span>
            <textarea name="contacts" rows="4"><?= e($client['contacts']) ?></textarea>
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="button">Guardar</button>
    </div>
</form>

