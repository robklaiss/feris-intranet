<section class="page-head">
    <div>
        <p class="eyebrow">Contrato <?= e($contract['contract_number']) ?></p>
        <h1><?= e($title) ?></h1>
    </div>
    <a href="/contracts/<?= e((string) $contract['id']) ?>" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="form-stack">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="panel__header">
            <h2>Datos del producto</h2>
            <span class="muted">Ítem técnico asociado al contrato</span>
        </div>
        <div class="form-grid">
            <label>
                <span>Código de ítem</span>
                <input type="text" name="item_code" value="<?= e($spec['item_code']) ?>" required>
            </label>
            <label>
                <span>Categoria</span>
                <select name="product_category" required>
                    <?php foreach (['textil' => 'Textil', 'consumo' => 'Consumo', 'otro' => 'Otro'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= (string) ($spec['product_category'] ?? 'textil') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Tipo de producto</span>
                <input type="text" name="product_type" value="<?= e($spec['product_type']) ?>">
            </label>
            <label>
                <span>Cantidad</span>
                <input type="number" step="0.0001" min="0.0001" name="quantity" value="<?= e((string) $spec['quantity']) ?>" required>
            </label>
            <label>
                <span>Unidad</span>
                <input type="text" name="unit" value="<?= e($spec['unit'] ?: 'unidad') ?>">
            </label>
            <label>
                <span>Etiqueta</span>
                <input type="text" name="label" value="<?= e($spec['label']) ?>">
            </label>
            <label class="span-2">
                <span>Descripción</span>
                <textarea name="description" rows="3"><?= e($spec['description']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Especificacion textil</h2>
            <span class="muted">Campos opcionales para confeccion</span>
        </div>
        <div class="form-grid">
            <label>
                <span>Talle/tamaño</span>
                <input type="text" name="size" value="<?= e($spec['size']) ?>">
            </label>
            <label>
                <span>Color</span>
                <input type="text" name="color" value="<?= e($spec['color']) ?>">
            </label>
            <label>
                <span>Tela</span>
                <input type="text" name="fabric" value="<?= e($spec['fabric']) ?>">
            </label>
            <label>
                <span>Gramaje</span>
                <input type="text" name="grammage" value="<?= e($spec['grammage']) ?>">
            </label>
            <label>
                <span>Medidas</span>
                <input type="text" name="measurements" value="<?= e($spec['measurements']) ?>">
            </label>
            <label>
                <span>Terminacion</span>
                <input type="text" name="finishing" value="<?= e($spec['finishing']) ?>">
            </label>
            <label class="checkbox">
                <input type="checkbox" name="has_embroidery" value="1" <?= !empty($spec['has_embroidery']) ? 'checked' : '' ?>>
                <span>Bordado</span>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="has_screen_printing" value="1" <?= !empty($spec['has_screen_printing']) ? 'checked' : '' ?>>
                <span>Serigrafía</span>
            </label>
            <label>
                <span>Ubicación de logo</span>
                <input type="text" name="logo_position" value="<?= e($spec['logo_position']) ?>">
            </label>
            <label>
                <span>Dependencia destino</span>
                <select name="destination_dependency_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($dependencies as $dependency): ?>
                        <option value="<?= e((string) $dependency['id']) ?>" <?= (string) ($spec['destination_dependency_id'] ?? '') === (string) $dependency['id'] ? 'selected' : '' ?>>
                            <?= e($dependency['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                <span>Detalles de bordado</span>
                <textarea name="embroidery_details" rows="3"><?= e($spec['embroidery_details']) ?></textarea>
            </label>
            <label class="span-2">
                <span>Detalles de serigrafía</span>
                <textarea name="screen_printing_details" rows="3"><?= e($spec['screen_printing_details']) ?></textarea>
            </label>
            <label class="span-2">
                <span>Observaciones tecnicas</span>
                <textarea name="technical_notes" rows="4"><?= e($spec['technical_notes']) ?></textarea>
            </label>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar ítem técnico</button>
    </div>
</form>
