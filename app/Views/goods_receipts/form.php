<section class="page-head">
    <div>
        <p class="eyebrow">Recepción de insumos</p>
        <h1>Registrar recepción</h1>
        <p class="muted">OC proveedor <a href="/supplier-purchase-orders/<?= e((string) $order['id']) ?>"><?= e($order['supplier_po_number']) ?></a> · <?= e($order['supplier_name']) ?></p>
    </div>
    <a href="/supplier-purchase-orders/<?= e((string) $order['id']) ?>" class="button button--secondary">Volver</a>
</section>

<form method="post" action="/supplier-purchase-orders/<?= e((string) $order['id']) ?>/goods-receipts" class="panel form-stack">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label>
            <span>Número de recepción</span>
            <input type="text" name="receipt_number" value="<?= e($receipt['receipt_number'] ?? '') ?>" placeholder="Se genera automáticamente si queda vacío">
        </label>
        <label>
            <span>Fecha</span>
            <input type="text" name="received_at" value="<?= e($receipt['received_at'] ?? '') ?>">
        </label>
        <label>
            <span>Remito proveedor</span>
            <input type="text" name="delivery_note_number" value="<?= e($receipt['delivery_note_number'] ?? '') ?>">
        </label>
        <label>
            <span>Factura proveedor</span>
            <input type="text" name="invoice_number" value="<?= e($receipt['invoice_number'] ?? '') ?>">
        </label>
    </div>
    <label>
        <span>Observaciones</span>
        <textarea name="notes" rows="3"><?= e($receipt['notes'] ?? '') ?></textarea>
    </label>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Ítem OC</th>
                <th>Ordenado</th>
                <th>Recibido previo</th>
                <th>Pendiente</th>
                <th>Recibido ahora</th>
                <th>Aceptado</th>
                <th>Rechazado</th>
                <th>Código interno</th>
                <th>Material</th>
                <th>Lote</th>
                <th>Ubicación</th>
                <th>Costo</th>
                <th>Calidad</th>
                <th>Obs.</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingItems as $index => $item): ?>
                <tr>
                    <td>
                        <input type="hidden" name="supplier_purchase_order_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <strong><?= e($item['description']) ?></strong><br>
                        <span class="muted"><?= e($item['unit']) ?></span>
                    </td>
                    <td><?= e((string) $item['quantity']) ?></td>
                    <td><?= e((string) $item['previously_received_quantity']) ?></td>
                    <td><?= e((string) $item['pending_quantity']) ?></td>
                    <td><input type="number" name="received_quantity[]" step="0.01" min="0" max="<?= e((string) $item['pending_quantity']) ?>" value="0"></td>
                    <td><input type="number" name="accepted_quantity[]" step="0.01" min="0" value="0"></td>
                    <td><input type="number" name="rejected_quantity[]" step="0.01" min="0" value="0"></td>
                    <td><input type="text" name="internal_code[]" placeholder="INT-<?= e((string) ($index + 1)) ?>"></td>
                    <td><input type="text" name="material_type[]" value="<?= e($item['required_material_type'] ?? $item['description']) ?>"></td>
                    <td><input type="text" name="lot_number[]"></td>
                    <td><input type="text" name="location[]"></td>
                    <td><input type="number" name="cost[]" step="0.01" min="0" value="<?= e((string) ($item['unit_price'] ?? '')) ?>"></td>
                    <td>
                        <select name="quality_status[]">
                            <option value="pending">Pendiente</option>
                            <option value="accepted">Aceptado</option>
                            <option value="rejected">Rechazado</option>
                        </select>
                    </td>
                    <td><input type="text" name="item_notes[]"></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pendingItems === []): ?>
                <tr><td colspan="14" class="empty">La OC proveedor no tiene saldo pendiente para recibir.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <button type="submit" class="button" <?= $pendingItems === [] ? 'disabled' : '' ?>>Guardar borrador</button>
        <a href="/supplier-purchase-orders/<?= e((string) $order['id']) ?>" class="button button--secondary">Cancelar</a>
    </div>
</form>
