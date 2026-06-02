<section class="page-head">
    <div>
        <p class="eyebrow">Confección</p>
        <h1><?= e($title) ?></h1>
        <p class="muted">
            Producción <a href="/production-orders/<?= e((string) $source['production_order_id']) ?>"><?= e($source['production_number']) ?></a>
            · Corte <a href="/cutting-orders/<?= e((string) $source['cutting_order_id']) ?>"><?= e($source['cutting_number']) ?></a>
            <?php if (($sourceType ?? '') === 'external_receipt'): ?>
                · Retorno <?= e($source['receipt_number']) ?>
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= ($sourceType ?? '') === 'external_receipt' ? '/external-work-orders/' . e((string) $source['external_work_order_id']) : '/cutting-orders/' . e((string) $source['cutting_order_id']) ?>" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label>
            <span>Costurero</span>
            <select name="seamster_id" required>
                <option value="">Seleccionar</option>
                <?php foreach ($seamsters as $seamster): ?>
                    <option value="<?= e((string) $seamster['id']) ?>" <?= old('seamster_id', $order['seamster_id'] ?? '') == $seamster['id'] ? 'selected' : '' ?>><?= e($seamster['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Número</span>
            <input type="text" name="sewing_number" value="<?= e(old('sewing_number', $order['sewing_number'] ?? '')) ?>" placeholder="Automático">
        </label>
        <label>
            <span>Fecha asignación</span>
            <input type="date" name="assigned_at" value="<?= e(old('assigned_at', $order['assigned_at'] ?? '')) ?>">
        </label>
        <label>
            <span>Finalización esperada</span>
            <input type="date" name="expected_completion_date" value="<?= e(old('expected_completion_date', $order['expected_completion_date'] ?? '')) ?>">
        </label>
        <label class="span-2">
            <span>Observaciones</span>
            <textarea name="notes" rows="3"><?= e(old('notes', $order['notes'] ?? '')) ?></textarea>
        </label>
    </div>

    <h2>Ítems disponibles</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Código</th><th>Descripción</th><th>Talle</th><th>Color</th><th>Disponible</th><th>Asignar</th><th>Notas</th></tr></thead>
            <tbody>
            <?php foreach (($source['items'] ?? []) as $item): ?>
                <tr>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e((string) $item['quantity_available_to_sew']) ?> <?= e($item['unit'] ?? 'unidad') ?></td>
                    <td>
                        <?php if (($sourceType ?? '') === 'external_receipt'): ?>
                            <input type="hidden" name="external_work_receipt_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="cutting_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <?php endif; ?>
                        <input type="number" step="0.01" min="0" max="<?= e((string) $item['quantity_available_to_sew']) ?>" name="quantity_assigned[]" value="<?= e((string) $item['quantity_available_to_sew']) ?>">
                    </td>
                    <td><input type="text" name="notes_item[]" value=""></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($source['items'] ?? []) === []): ?>
                <tr><td colspan="7" class="empty">No hay saldo disponible para confección.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions">
        <button type="submit" class="button" <?= (($source['items'] ?? []) === []) ? 'disabled' : '' ?>>Guardar</button>
    </div>
</form>
