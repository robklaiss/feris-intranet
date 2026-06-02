<section class="page-head">
    <div>
        <p class="eyebrow">Costurero</p>
        <h1><?= e($title) ?></h1>
    </div>
    <a href="/seamsters" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label>
            <span>Nombre</span>
            <input type="text" name="name" value="<?= e(old('name', $seamster['name'] ?? '')) ?>" required>
        </label>
        <label>
            <span>Documento</span>
            <input type="text" name="document_number" value="<?= e(old('document_number', $seamster['document_number'] ?? '')) ?>">
        </label>
        <label>
            <span>Teléfono</span>
            <input type="text" name="phone" value="<?= e(old('phone', $seamster['phone'] ?? '')) ?>">
        </label>
        <label>
            <span>Email</span>
            <input type="email" name="email" value="<?= e(old('email', $seamster['email'] ?? '')) ?>">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= old('status', $seamster['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="span-2">
            <span>Dirección</span>
            <input type="text" name="address" value="<?= e(old('address', $seamster['address'] ?? '')) ?>">
        </label>
        <label class="span-2">
            <span>Observaciones</span>
            <textarea name="notes" rows="4"><?= e(old('notes', $seamster['notes'] ?? '')) ?></textarea>
        </label>
    </div>
    <div class="form-actions">
        <button type="submit" class="button">Guardar</button>
    </div>
</form>
