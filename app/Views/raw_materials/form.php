<section class="page-head">
    <div>
        <p class="eyebrow">Inventario de insumos</p>
        <h1><?= e($title) ?></h1>
    </div>
    <a href="/raw-materials" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="form-stack">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="panel__header">
            <h2>Datos del insumo</h2>
            <span class="muted">Código interno y disponibilidad para producción.</span>
        </div>
        <div class="form-grid">
            <label>
                <span>Código interno</span>
                <input type="text" name="internal_code" value="<?= e(old('internal_code', $material['internal_code'])) ?>" required>
            </label>
            <label>
                <span>Tipo de insumo</span>
                <input type="text" name="material_type" value="<?= e(old('material_type', $material['material_type'])) ?>" required>
            </label>
            <label>
                <span>Unidad</span>
                <input type="text" name="unit" value="<?= e(old('unit', $material['unit'])) ?>" required>
            </label>
            <label>
                <span>Estado</span>
                <select name="status">
                    <?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo', 'depleted' => 'Agotado'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= (string) old('status', $material['status']) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                <span>Descripción</span>
                <textarea name="description" rows="3" required><?= e(old('description', $material['description'])) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Cantidades</h2>
            <span class="muted">La reserva aumenta sin descontar existencia física.</span>
        </div>
        <div class="form-grid">
            <label>
                <span>Cantidad disponible</span>
                <input type="number" step="0.0001" min="0" name="quantity_available" value="<?= e((string) old('quantity_available', $material['quantity_available'])) ?>" required>
            </label>
            <label>
                <span>Cantidad reservada</span>
                <input type="number" step="0.0001" min="0" name="quantity_reserved" value="<?= e((string) old('quantity_reserved', $material['quantity_reserved'])) ?>" required>
            </label>
            <label>
                <span>Stock mínimo</span>
                <input type="number" step="0.0001" min="0" name="minimum_stock" value="<?= e((string) old('minimum_stock', $material['minimum_stock'])) ?>">
            </label>
            <label>
                <span>Costo</span>
                <input type="number" step="0.0001" min="0" name="cost" value="<?= e((string) old('cost', $material['cost'])) ?>">
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Relaciones y origen</h2>
            <span class="muted">Campos usados para sugerir insumos en verificaciones.</span>
        </div>
        <div class="form-grid">
            <label>
                <span>Código de ítem relacionado</span>
                <input type="text" name="related_item_code" value="<?= e(old('related_item_code', $material['related_item_code'])) ?>">
            </label>
            <label>
                <span>Tipo de producto relacionado</span>
                <input type="text" name="related_product_type" value="<?= e(old('related_product_type', $material['related_product_type'])) ?>">
            </label>
            <label>
                <span>Proveedor</span>
                <input type="text" name="supplier_name" value="<?= e(old('supplier_name', $material['supplier_name'])) ?>">
            </label>
            <label>
                <span>RUC proveedor</span>
                <input type="text" name="supplier_ruc" value="<?= e(old('supplier_ruc', $material['supplier_ruc'])) ?>">
            </label>
            <label>
                <span>Lote</span>
                <input type="text" name="lot_number" value="<?= e(old('lot_number', $material['lot_number'])) ?>">
            </label>
            <label>
                <span>Ubicación</span>
                <input type="text" name="location" value="<?= e(old('location', $material['location'])) ?>">
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3"><?= e(old('notes', $material['notes'])) ?></textarea>
            </label>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar insumo</button>
    </div>
</form>
