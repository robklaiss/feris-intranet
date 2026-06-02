<section class="page-head">
    <div>
        <p class="eyebrow">Control de calidad</p>
        <h1><?= e($title) ?></h1>
        <p class="muted">
            Producción <a href="/production-orders/<?= e((string) $source['production_order_id']) ?>"><?= e($source['production_number']) ?></a>
            <?php if (($sourceType ?? '') === 'sewing'): ?>
                · Confección <a href="/sewing-orders/<?= e((string) $source['id']) ?>"><?= e($source['sewing_number']) ?></a>
            <?php else: ?>
                · Retorno <?= e($source['receipt_number']) ?>
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= ($sourceType ?? '') === 'sewing' ? '/sewing-orders/' . e((string) $source['id']) : '/external-work-orders/' . e((string) $source['external_work_order_id']) ?>" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label>
            <span>Número</span>
            <input type="text" name="qc_number" value="<?= e(old('qc_number', $check['qc_number'] ?? '')) ?>" placeholder="Automático">
        </label>
        <label class="span-2">
            <span>Observaciones</span>
            <textarea name="notes" rows="3"><?= e(old('notes', $check['notes'] ?? '')) ?></textarea>
        </label>
    </div>

    <h2>Ítems disponibles</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Código</th><th>Descripción</th><th>Talle</th><th>Color</th><th>Disponible</th><th>A controlar</th><th>Notas</th></tr></thead>
            <tbody>
            <?php foreach (($source['items'] ?? []) as $item): ?>
                <tr>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e((string) $item['quantity_available_to_quality']) ?> <?= e($item['unit'] ?? 'unidad') ?></td>
                    <td>
                        <?php if (($sourceType ?? '') === 'external_receipt'): ?>
                            <input type="hidden" name="external_work_receipt_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="sewing_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <?php endif; ?>
                        <input type="number" step="0.01" min="0" max="<?= e((string) $item['quantity_available_to_quality']) ?>" name="quantity_received[]" value="<?= e((string) $item['quantity_available_to_quality']) ?>">
                    </td>
                    <td><input type="text" name="notes_item[]" value=""></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($source['items'] ?? []) === []): ?>
                <tr><td colspan="7" class="empty">No hay saldo disponible para control de calidad.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions">
        <button type="submit" class="button" <?= (($source['items'] ?? []) === []) ? 'disabled' : '' ?>>Guardar</button>
    </div>
</form>
