<section class="page-head">
    <div>
        <p class="eyebrow">Orden de corte</p>
        <h1><?= e($title) ?></h1>
        <p class="muted">Producción <?= e($productionOrder['production_number']) ?> · <?= e($productionOrder['client_name']) ?> · Contrato <?= e($productionOrder['contract_number']) ?></p>
    </div>
</section>

<form method="post" action="<?= e($action) ?>" class="panel form-grid">
    <?= csrf_field() ?>
    <label>Fecha planificada
        <input type="date" name="planned_date" value="<?= e($order['planned_date']) ?>">
    </label>
    <label>Responsable de corte
        <input type="text" name="cut_by" value="<?= e($order['cut_by']) ?>">
    </label>
    <label class="span-full">Observaciones
        <textarea name="notes" rows="3"><?= e($order['notes']) ?></textarea>
    </label>

    <div class="span-full table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Ítem técnico</th>
                <th>Especificación</th>
                <th>Saldo corte</th>
                <th>Cantidad a cortar</th>
                <th>Notas</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($productionOrder['items'] as $item): ?>
                <?php if ((float) $item['pending_cut_quantity'] <= 0.0001) { continue; } ?>
                <tr>
                    <td>
                        <input type="hidden" name="production_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <strong><?= e($item['item_code']) ?></strong><br>
                        <span class="muted"><?= e($item['product_type']) ?></span>
                    </td>
                    <td>
                        <?= e($item['description']) ?><br>
                        <span class="muted">
                            Talle <?= e($item['size']) ?> · Color <?= e($item['color']) ?> · Tela <?= e($item['fabric'] ?? '') ?>
                        </span><br>
                        <span class="muted"><?= e($item['measurements'] ?? '') ?></span>
                    </td>
                    <td><?= e((string) $item['pending_cut_quantity']) ?> <?= e($item['unit']) ?></td>
                    <td><input type="number" step="0.01" min="0.01" max="<?= e((string) $item['pending_cut_quantity']) ?>" name="quantity_to_cut[]" value="<?= e((string) $item['pending_cut_quantity']) ?>"></td>
                    <td><input type="text" name="notes_item[]" value=""></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="span-full">
        <h2>Materiales reservados</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Insumo</th><th>Ítem</th><th>Tipo</th><th>Cantidad reservada</th></tr></thead>
                <tbody>
                <?php foreach ($productionOrder['reservations'] as $reservation): ?>
                    <tr>
                        <td><a href="/raw-materials/<?= e((string) $reservation['raw_material_inventory_id']) ?>"><?= e($reservation['internal_code']) ?></a></td>
                        <td><?= e($reservation['item_code']) ?></td>
                        <td><?= e($reservation['material_type']) ?></td>
                        <td><?= e((string) $reservation['reserved_quantity']) ?> <?= e($reservation['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($productionOrder['reservations'] === []): ?>
                    <tr><td colspan="4" class="empty">Sin reservas activas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-actions span-full">
        <a href="/production-orders/<?= e((string) $productionOrder['id']) ?>" class="button button--secondary">Cancelar</a>
        <button type="submit" class="button">Guardar</button>
    </div>
</form>
