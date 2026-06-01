<section class="page-head">
    <div>
        <p class="eyebrow">Orden de producción</p>
        <h1><?= e($title) ?></h1>
        <p class="muted">OC cliente <?= e($customerOrder['po_number']) ?> · <?= e($customerOrder['client_name']) ?></p>
    </div>
</section>

<form method="post" action="<?= e($action) ?>" class="panel form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="customer_purchase_order_id" value="<?= e((string) $order['customer_purchase_order_id']) ?>">
    <label>Número producción
        <input type="text" name="production_number" value="<?= e($order['production_number']) ?>" required>
    </label>
    <label>Inicio planificado
        <input type="date" name="planned_start_date" value="<?= e($order['planned_start_date']) ?>">
    </label>
    <label>Fin planificado
        <input type="date" name="planned_end_date" value="<?= e($order['planned_end_date']) ?>">
    </label>
    <label class="span-full">Observaciones
        <textarea name="notes" rows="3"><?= e($order['notes']) ?></textarea>
    </label>

    <div class="span-full table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Item OC</th>
                <th>Descripción</th>
                <th>Saldo OC</th>
                <th>Cantidad producción</th>
                <th>Notas</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingItems as $item): ?>
                <tr>
                    <td>
                        <input type="hidden" name="customer_purchase_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <strong><?= e($item['item_code']) ?></strong><br>
                        <span class="muted"><?= e($item['product_type']) ?></span>
                    </td>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e((string) $item['balance_quantity']) ?> <?= e($item['unit']) ?></td>
                    <td><input type="number" step="0.01" min="0" max="<?= e((string) $item['balance_quantity']) ?>" name="quantity[]" value="<?= e((string) $item['balance_quantity']) ?>"></td>
                    <td><input type="text" name="notes_item[]" value=""></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions span-full">
        <a href="/customer-purchase-orders/<?= e((string) $customerOrder['id']) ?>" class="button button--secondary">Cancelar</a>
        <button type="submit" class="button">Guardar</button>
    </div>
</form>
