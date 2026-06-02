<section class="page-head">
    <div>
        <p class="eyebrow">Orden de producción <?= e($order['production_number']) ?></p>
        <h1>Verificar stock</h1>
        <p class="muted"><?= e($order['client_name']) ?> · <?= e(production_stage_label($order['production_stage'])) ?></p>
    </div>
    <a href="/production-orders/<?= e((string) $order['id']) ?>" class="button button--secondary">Volver</a>
</section>

<form method="post" action="/production-orders/<?= e((string) $order['id']) ?>/stock-checks" class="form-stack">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Número de verificación</span>
                <input type="text" name="check_number" value="">
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3"></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2>Ítems e insumos</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Item</th>
                    <th>Tipo</th>
                    <th>Cantidad requerida</th>
                    <th>Insumo</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['item_code']) ?></strong><br>
                            <span class="muted"><?= e($item['description']) ?></span>
                            <input type="hidden" name="production_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                        </td>
                        <td><?= e($item['product_type']) ?></td>
                        <td><?= e((string) $item['quantity']) ?> <?= e($item['unit']) ?></td>
                        <td>
                            <select name="raw_material_inventory_id[]">
                                <option value="">Auto / sin selección manual</option>
                                <?php foreach ($materials as $material): ?>
                                    <option value="<?= e((string) $material['id']) ?>">
                                        <?= e($material['internal_code']) ?> · <?= e($material['material_type']) ?> · libre <?= e((string) $material['available_to_reserve']) ?> <?= e($material['unit']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Crear verificación</button>
    </div>
</form>
