<section class="page-head">
    <div>
        <p class="eyebrow">Orden de compra proveedor</p>
        <h1>Generar OC ISO 9001</h1>
        <p class="muted">Presupuesto aprobado <?= e($quote['quote_number']) ?> · <?= e($quote['supplier_name']) ?></p>
    </div>
    <a href="/purchase-requisitions/<?= e((string) $quote['purchase_requisition_id']) ?>" class="button button--secondary">Volver</a>
</section>

<form method="post" action="/supplier-purchase-orders/from-quote/<?= e((string) $quote['id']) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="form-grid">
        <label>
            <span>Número OC proveedor</span>
            <input type="text" name="supplier_po_number" placeholder="Automático">
        </label>
        <label>
            <span>Formulario ISO</span>
            <input type="text" name="iso_form_number" placeholder="ISO-COM-">
        </label>
        <label>
            <span>Fecha OC</span>
            <input type="date" name="order_date" value="<?= e(date('Y-m-d')) ?>" required>
        </label>
        <label>
            <span>Entrega esperada</span>
            <input type="date" name="expected_delivery_date">
        </label>
        <label class="span-2">
            <span>Condiciones de pago</span>
            <input type="text" name="payment_terms" value="<?= e($quote['payment_terms'] ?? '') ?>">
        </label>
        <label class="span-2">
            <span>Condiciones de entrega</span>
            <input type="text" name="delivery_terms" value="<?= e($quote['supplier_delivery_terms'] ?? '') ?>">
        </label>
        <label class="span-2">
            <span>Motivo de compra</span>
            <textarea name="purchase_reason" rows="3">Faltantes detectados en verificación de stock <?= e((string) $quote['stock_check_id']) ?> para orden de producción <?= e((string) $quote['production_order_id']) ?>.</textarea>
        </label>
        <label class="span-2">
            <span>Comparación de proveedores</span>
            <textarea name="supplier_comparison_summary" rows="4"></textarea>
        </label>
        <label class="span-2">
            <span>Especificaciones producto / insumo</span>
            <textarea name="product_specifications" rows="4"><?php foreach ($quote['items'] as $item): ?><?= e($item['description']) ?> - <?= e((string) $item['quantity']) ?> <?= e($item['unit']) ?>; <?php endforeach; ?></textarea>
        </label>
        <label class="span-2">
            <span>Requisitos de calidad</span>
            <textarea name="quality_requirements" rows="4">Cumplir especificaciones técnicas aprobadas, condiciones del presupuesto y verificación documental al recibir.</textarea>
        </label>
        <label class="span-2">
            <span>Observaciones</span>
            <textarea name="notes" rows="3"></textarea>
        </label>
    </div>

    <h2>Ítems copiados del presupuesto aprobado</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Descripción</th>
                <th>Unidad</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($quote['items'] as $item): ?>
                <tr>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e($item['unit']) ?></td>
                    <td><?= e((string) $item['quantity']) ?></td>
                    <td><?= e($quote['currency']) ?> <?= e(money($item['unit_price'])) ?></td>
                    <td><?= e($quote['currency']) ?> <?= e(money($item['total_price'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="summary-row">
        <span>Subtotal: <?= e($quote['currency']) ?> <?= e(money($quote['subtotal'])) ?></span>
        <span>Impuestos: <?= e($quote['currency']) ?> <?= e(money($quote['tax_amount'])) ?></span>
        <strong>Total: <?= e($quote['currency']) ?> <?= e(money($quote['total_amount'])) ?></strong>
    </div>
    <div class="form-actions">
        <button type="submit" class="button">Generar orden</button>
    </div>
</form>
