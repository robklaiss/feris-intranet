<section class="page-head">
    <div>
        <p class="eyebrow">Recepción externa</p>
        <h1><?= e($order['external_work_number']) ?></h1>
        <p class="muted">Proveedor <?= e($order['supplier_name'] ?? '-') ?> · Nota <?= e($order['send_note_number'] ?? '-') ?> · Corte <?= e($order['cutting_number']) ?></p>
    </div>
</section>

<form method="post" action="<?= e($action) ?>" class="panel form-grid">
    <?= csrf_field() ?>
    <label>Número de recepción
        <input type="text" name="receipt_number" value="" placeholder="Automático">
    </label>
    <label>Próxima etapa
        <select name="next_stage">
            <?php foreach (['sewing', 'quality_control'] as $stage): ?>
                <option value="<?= e($stage) ?>" <?= ($order['next_stage'] ?? '') === $stage ? 'selected' : '' ?>><?= e(external_next_stage_label($stage)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="span-full">Observaciones
        <textarea name="notes" rows="3"></textarea>
    </label>

    <div class="span-full table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Ítem</th>
                <th>Pendiente</th>
                <th>Recibido</th>
                <th>Aceptado</th>
                <th>Rechazado</th>
                <th>Próxima etapa</th>
                <th>Notas calidad</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order['items'] as $item): ?>
                <?php $pending = max(0.0, (float) $item['quantity_sent'] - (float) $item['quantity_returned']); ?>
                <?php if ($pending <= 0.0001) { continue; } ?>
                <tr>
                    <td>
                        <input type="hidden" name="external_work_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <strong><?= e($item['item_code']) ?></strong><br>
                        <span class="muted"><?= e($item['description']) ?> · <?= e($item['size']) ?> · <?= e($item['color']) ?></span>
                    </td>
                    <td><?= e((string) $pending) ?></td>
                    <td><input type="number" step="0.01" min="0.01" max="<?= e((string) $pending) ?>" name="quantity_received[]" value="<?= e((string) $pending) ?>"></td>
                    <td><input type="number" step="0.01" min="0" max="<?= e((string) $pending) ?>" name="quantity_accepted[]" value="<?= e((string) $pending) ?>"></td>
                    <td><input type="number" step="0.01" min="0" max="<?= e((string) $pending) ?>" name="quantity_rejected[]" value="0"></td>
                    <td>
                        <select name="next_stage_item[]">
                            <?php foreach (['sewing', 'quality_control'] as $stage): ?>
                                <option value="<?= e($stage) ?>" <?= ($order['next_stage'] ?? '') === $stage ? 'selected' : '' ?>><?= e(external_next_stage_label($stage)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="quality_notes[]" value=""></td>
                    <input type="hidden" name="notes_item[]" value="">
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions span-full">
        <a href="/external-work-orders/<?= e((string) $order['id']) ?>" class="button button--secondary">Cancelar</a>
        <button type="submit" class="button">Guardar recepción</button>
    </div>
</form>
