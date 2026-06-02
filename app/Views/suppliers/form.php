<section class="page-head">
    <div>
        <p class="eyebrow">Proveedor</p>
        <h1><?= e($title) ?></h1>
    </div>
    <a href="/suppliers" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label>
            <span>Nombre</span>
            <input type="text" name="name" value="<?= e(old('name', $supplier['name'] ?? '')) ?>" required>
        </label>
        <label>
            <span>RUC</span>
            <input type="text" name="ruc" value="<?= e(old('ruc', $supplier['ruc'] ?? '')) ?>">
        </label>
        <label>
            <span>Contacto</span>
            <input type="text" name="contact_name" value="<?= e(old('contact_name', $supplier['contact_name'] ?? '')) ?>">
        </label>
        <label>
            <span>Teléfono</span>
            <input type="text" name="phone" value="<?= e(old('phone', $supplier['phone'] ?? '')) ?>">
        </label>
        <label>
            <span>Email</span>
            <input type="email" name="email" value="<?= e(old('email', $supplier['email'] ?? '')) ?>">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= old('status', $supplier['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Condiciones de pago</span>
            <input type="text" name="payment_terms" value="<?= e(old('payment_terms', $supplier['payment_terms'] ?? '')) ?>">
        </label>
        <label>
            <span>Condiciones de entrega</span>
            <input type="text" name="delivery_terms" value="<?= e(old('delivery_terms', $supplier['delivery_terms'] ?? '')) ?>">
        </label>
        <label class="span-2">
            <span>Dirección</span>
            <input type="text" name="address" value="<?= e(old('address', $supplier['address'] ?? '')) ?>">
        </label>
        <label class="span-2">
            <span>Observaciones</span>
            <textarea name="notes" rows="4"><?= e(old('notes', $supplier['notes'] ?? '')) ?></textarea>
        </label>
    </div>
    <div class="form-actions">
        <button type="submit" class="button">Guardar</button>
    </div>
</form>
