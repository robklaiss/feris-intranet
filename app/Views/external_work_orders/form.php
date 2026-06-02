<section class="page-head">
    <div>
        <p class="eyebrow">Trabajo externo</p>
        <h1><?= e($title) ?></h1>
        <p class="muted">Corte <?= e($cuttingOrder['cutting_number']) ?> · Producción <?= e($cuttingOrder['production_number']) ?> · <?= e($cuttingOrder['client_name']) ?></p>
    </div>
</section>

<form method="post" action="<?= e($action) ?>" class="panel form-grid">
    <?= csrf_field() ?>
    <label>Proveedor
        <select name="supplier_id">
            <option value="">Sin proveedor asignado</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= e((string) $supplier['id']) ?>" <?= (string) ($order['supplier_id'] ?? '') === (string) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Tipo de trabajo
        <select name="work_type">
            <?php foreach (['embroidery', 'screen_printing', 'both', 'other'] as $type): ?>
                <option value="<?= e($type) ?>" <?= ($order['work_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(external_work_type_label($type)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Número
        <input type="text" name="external_work_number" value="<?= e($order['external_work_number']) ?>" placeholder="Automático">
    </label>
    <label>Nota de envío
        <input type="text" name="send_note_number" value="<?= e($order['send_note_number']) ?>" placeholder="Automática al enviar">
    </label>
    <label>Retorno esperado
        <input type="date" name="expected_return_date" value="<?= e($order['expected_return_date']) ?>">
    </label>
    <label>Próxima etapa
        <select name="next_stage">
            <?php foreach (['sewing', 'quality_control'] as $stage): ?>
                <option value="<?= e($stage) ?>" <?= ($order['next_stage'] ?? '') === $stage ? 'selected' : '' ?>><?= e(external_next_stage_label($stage)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="span-full">Observaciones
        <textarea name="notes" rows="3"><?= e($order['notes']) ?></textarea>
    </label>

    <div class="span-full table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Ítem cortado</th>
                <th>Especificación</th>
                <th>Disponible</th>
                <th>Cantidad a enviar</th>
                <th>Trabajo</th>
                <th>Override</th>
                <th>Notas</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($cuttingOrder['items'] as $index => $item): ?>
                <tr>
                    <td>
                        <input type="hidden" name="cutting_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <strong><?= e($item['item_code']) ?></strong><br>
                        <span class="muted"><?= e($item['product_type']) ?></span>
                    </td>
                    <td>
                        <?= e($item['description']) ?><br>
                        <span class="muted">Talle <?= e($item['size']) ?> · Color <?= e($item['color']) ?></span>
                    </td>
                    <td>
                        <?= e((string) $item['quantity_available_to_send']) ?> <?= e($item['unit']) ?><br>
                        <span class="muted"><?= !empty($item['requires_external_work']) ? 'Marcado técnico' : 'Manual' ?></span>
                    </td>
                    <td><input type="number" step="0.01" min="0.01" max="<?= e((string) $item['quantity_available_to_send']) ?>" name="quantity_sent[]" value="<?= !empty($item['requires_external_work']) ? e((string) $item['quantity_available_to_send']) : '' ?>"></td>
                    <td><input type="text" name="work_details[]" value="" placeholder="Ubicación, colores, logo"></td>
                    <td><input type="checkbox" name="manual_override[<?= e((string) $index) ?>]" value="1" <?= !empty($item['requires_external_work']) ? 'disabled' : '' ?>></td>
                    <td><input type="text" name="notes_item[]" value="" placeholder="<?= !empty($item['requires_external_work']) ? '' : 'Motivo obligatorio' ?>"></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($cuttingOrder['items'] === []): ?>
                <tr><td colspan="7" class="empty">Sin ítems cortados disponibles para envío externo.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions span-full">
        <a href="/cutting-orders/<?= e((string) $cuttingOrder['id']) ?>" class="button button--secondary">Cancelar</a>
        <button type="submit" class="button">Guardar borrador</button>
    </div>
</form>
