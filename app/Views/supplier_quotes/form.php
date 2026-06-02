<?php
$supplierRequest = null;
foreach ($requisition['quote_requests'] as $quoteRequest) {
    if ((int) $quoteRequest['supplier_id'] === (int) $supplierId) {
        $supplierRequest = $quoteRequest;
        break;
    }
}
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Presupuesto proveedor</p>
        <h1><?= e($supplierRequest['supplier_name'] ?? 'Proveedor') ?></h1>
        <p class="muted">Pedido <a href="/purchase-requisitions/<?= e((string) $requisition['id']) ?>"><?= e($requisition['requisition_number']) ?></a></p>
    </div>
    <a href="/purchase-requisitions/<?= e((string) $requisition['id']) ?>" class="button button--secondary">Volver</a>
</section>

<form method="post" action="/purchase-requisitions/<?= e((string) $requisition['id']) ?>/suppliers/<?= e((string) $supplierId) ?>/quotes" class="panel">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label>
            <span>Número presupuesto</span>
            <input type="text" name="quote_number" required>
        </label>
        <label>
            <span>Fecha presupuesto</span>
            <input type="date" name="quote_date" value="<?= e(date('Y-m-d')) ?>" required>
        </label>
        <label>
            <span>Moneda</span>
            <input type="text" name="currency" value="PYG" required>
        </label>
        <label>
            <span>IVA / impuestos</span>
            <input type="number" step="0.01" min="0" name="tax_amount" value="0">
        </label>
        <label>
            <span>Total manual</span>
            <input type="number" step="0.01" min="0" name="total_amount" placeholder="Opcional">
        </label>
        <label>
            <span>Plazo entrega días</span>
            <input type="number" min="0" name="delivery_days">
        </label>
        <label class="span-2">
            <span>Condiciones de pago</span>
            <input type="text" name="payment_terms" value="<?= e($supplierRequest['supplier_payment_terms'] ?? '') ?>">
        </label>
        <label class="span-2">
            <span>Adjunto / referencia</span>
            <input type="text" name="attachment_path">
        </label>
        <label class="span-2">
            <span>Observaciones</span>
            <textarea name="notes" rows="3"></textarea>
        </label>
    </div>

    <h2>Ítems presupuestados</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Ítem pedido</th>
                <th>Descripción proveedor</th>
                <th>Unidad</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requisition['items'] as $item): ?>
                <tr>
                    <td>
                        <input type="hidden" name="purchase_requisition_item_id[]" value="<?= e((string) $item['id']) ?>">
                        <strong><?= e($item['required_material_type']) ?></strong><br>
                        <span class="muted"><?= e($item['required_description']) ?></span>
                    </td>
                    <td><input type="text" name="description[]" value="<?= e($item['required_description']) ?>" required></td>
                    <td><input type="text" name="unit[]" value="<?= e($item['required_unit']) ?>" required></td>
                    <td><input type="number" step="0.01" min="0.01" name="quantity[]" value="<?= e((string) $item['requested_quantity']) ?>" required></td>
                    <td><input type="number" step="0.01" min="0" name="unit_price[]" required></td>
                    <td><input type="number" step="0.01" min="0" name="total_price[]" placeholder="Automático"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <button type="submit" class="button">Registrar presupuesto</button>
    </div>
</form>
