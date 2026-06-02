<section class="page-head">
    <div>
        <p class="eyebrow">Empaquetado</p>
        <h1><?= e($title) ?></h1>
        <p class="muted">
            Calidad <a href="/quality-control/<?= e((string) $source['id']) ?>"><?= e($source['qc_number']) ?></a>
            · Producción <a href="/production-orders/<?= e((string) $source['production_order_id']) ?>"><?= e($source['production_number']) ?></a>
        </p>
    </div>
    <a href="/quality-control/<?= e((string) $source['id']) ?>" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="form-stack">
    <?= csrf_field() ?>
    <section class="panel">
        <h2>Datos</h2>
        <div class="form-grid">
            <label>
                <span>Número</span>
                <input type="text" name="packaging_number" value="<?= e($order['packaging_number'] ?? '') ?>" placeholder="Automático si se deja vacío">
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <input type="text" name="notes" value="<?= e($order['notes'] ?? '') ?>">
            </label>
        </div>
    </section>

    <section class="panel">
        <h2>Ítems aprobados disponibles</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Código</th><th>Descripción</th><th>Talle</th><th>Color</th><th>Aprobado</th><th>Pendiente</th><th>A empaquetar</th><th>Etiqueta</th><th>Notas</th></tr></thead>
                <tbody>
                <?php foreach (($source['items'] ?? []) as $item): ?>
                    <tr>
                        <td><strong><?= e($item['item_code']) ?></strong></td>
                        <td><?= e($item['description']) ?></td>
                        <td><?= e($item['size']) ?></td>
                        <td><?= e($item['color']) ?></td>
                        <td><?= e((string) $item['quantity_approved']) ?></td>
                        <td><?= e((string) $item['quantity_available_to_pack']) ?></td>
                        <td>
                            <input type="hidden" name="quality_control_check_item_id[]" value="<?= e((string) $item['id']) ?>">
                            <input type="number" step="0.01" min="0" max="<?= e((string) $item['quantity_available_to_pack']) ?>" name="quantity_to_pack[]" value="<?= e((string) $item['quantity_available_to_pack']) ?>">
                        </td>
                        <td><input type="text" name="label[]" value="<?= e($item['label'] ?? '') ?>"></td>
                        <td><input type="text" name="notes_item[]" value=""></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar borrador</button>
        <a href="/quality-control/<?= e((string) $source['id']) ?>" class="button button--secondary">Cancelar</a>
    </div>
</form>
