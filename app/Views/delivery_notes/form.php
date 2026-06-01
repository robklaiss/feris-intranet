<?php
$selectedOrderIds = $note['purchase_order_ids'] ?? [];
$activeOrders = $orders;
if (!empty($note['contract_id'])) {
    $activeOrders = array_values(array_filter($orders, static fn (array $order): bool => (string) $order['contract_id'] === (string) $note['contract_id']));
}
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Notas de entrega</p>
        <h1>Nueva nota interna</h1>
        <p class="muted">Puede construirse desde una orden puntual o desde un contrato con varias órdenes abiertas.</p>
    </div>
    <a href="/delivery-notes" class="button button--secondary">Volver</a>
</section>

<form method="get" action="/delivery-notes/create" class="panel form-stack">
    <div class="form-grid compact">
        <label>
            <span>Contrato</span>
            <select name="contract_id" onchange="this.form.submit()">
                <option value="">Todas las órdenes abiertas</option>
                <?php foreach ($contracts as $contract): ?>
                    <option value="<?= e((string) $contract['id']) ?>" <?= (string) $note['contract_id'] === (string) $contract['id'] ? 'selected' : '' ?>>
                        <?= e($contract['contract_number']) ?> · <?= e($contract['client_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="selector-grid">
        <?php foreach ($activeOrders as $orderOption): ?>
            <label class="selector-card">
                <input type="checkbox" name="purchase_order_ids[]" value="<?= e((string) $orderOption['id']) ?>" <?= in_array((int) $orderOption['id'], $selectedOrderIds, true) ? 'checked' : '' ?>>
                <span>
                    <strong><?= e($orderOption['order_number']) ?></strong>
                    <small><?= e($orderOption['client_name']) ?> · ID <?= e($orderOption['identifier_number']) ?></small>
                </span>
            </label>
        <?php endforeach; ?>
        <?php if ($activeOrders === []): ?>
            <p class="empty">No hay órdenes abiertas para el filtro actual.</p>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="button button--secondary">Cargar contexto</button>
    </div>
</form>

<form method="post" action="/delivery-notes" class="form-stack" data-calc-totals>
    <?= csrf_field() ?>
    <input type="hidden" name="contract_id" value="<?= e((string) ($note['contract_id'] ?? '')) ?>">
    <input type="hidden" name="purchase_order_ids" value="<?= e(implode(',', $selectedOrderIds)) ?>">
    <section class="alert alert--info">
        La nota se guarda como borrador. Confirmala para que pueda consumirse en remisiones.
    </section>
    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Fecha</span>
                <input type="date" name="note_date" value="<?= e($note['note_date']) ?>">
            </label>
            <label>
                <span>Contrato</span>
                <input type="text" value="<?= e($context['document']['contract_number'] ?? $selectedOrder['contract_number'] ?? '') ?>" readonly tabindex="-1">
            </label>
            <label>
                <span>N° de ID</span>
                <input type="text" value="<?= e($context['document']['identifier_number'] ?? '') ?>" readonly tabindex="-1">
            </label>
            <label>
                <span>Modalidad</span>
                <input type="text" value="<?= e($context['document']['contract_type'] ?? '') ?>" readonly tabindex="-1">
            </label>
            <label>
                <span>Cliente</span>
                <input type="text" value="<?= e($context['document']['client_name'] ?? $selectedOrder['client_name'] ?? '') ?>" readonly tabindex="-1">
            </label>
            <label>
                <span>RUC</span>
                <input type="text" value="<?= e($context['document']['tax_id'] ?? '') ?>" readonly tabindex="-1">
            </label>
            <label class="span-2">
                <span>Dirección de entrega</span>
                <textarea name="delivery_address" rows="2"><?= e($note['delivery_address'] ?? '') ?></textarea>
            </label>
            <label>
                <span>Firma y aclaración del que recibe</span>
                <input type="text" name="receiver_name" value="<?= e($note['receiver_name'] ?? '') ?>">
            </label>
            <label>
                <span>Firma del que recibe</span>
                <input type="text" name="receiver_signature" value="<?= e($note['receiver_signature'] ?? '') ?>">
            </label>
            <label>
                <span>Firma y aclaración del que entrega</span>
                <input type="text" name="issuer_name" value="<?= e($note['issuer_name'] ?? '') ?>">
            </label>
            <label>
                <span>Firma del que entrega</span>
                <input type="text" name="issuer_signature" value="<?= e($note['issuer_signature'] ?? '') ?>">
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3"><?= e($note['notes']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Items disponibles</h2>
            <span class="muted">Se controlan saldos por item de orden en servidor.</span>
        </div>
        <div class="table-wrap">
            <table class="table table--form">
                <thead>
                <tr>
                    <th>Orden</th>
                    <th>Producto</th>
                    <th>Saldo orden</th>
                    <th>Cantidad a entregar</th>
                    <th>Precio</th>
                    <th>Total item</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($balances as $balance): ?>
                    <tr>
                        <td>
                            <?= e($balance['order_number'] ?? '') ?>
                            <input type="hidden" name="purchase_order_id[]" value="<?= e((string) ($balance['purchase_order_id'] ?? '')) ?>">
                            <input type="hidden" name="purchase_order_item_id[]" value="<?= e((string) $balance['id']) ?>">
                            <input type="hidden" name="product_name[]" value="<?= e($balance['product_name']) ?>">
                            <input type="hidden" name="unit_measure[]" value="<?= e($balance['unit_measure']) ?>">
                            <input type="hidden" name="unit_price[]" value="<?= e((string) $balance['unit_price']) ?>" data-price>
                        </td>
                        <td><?= e($balance['product_name']) ?></td>
                        <td><?= e((string) $balance['remaining_quantity']) ?> <?= e($balance['unit_measure']) ?></td>
                        <td><input type="number" step="0.0001" name="quantity[]" value="0" data-quantity></td>
                        <td>Gs. <?= e(money($balance['unit_price'])) ?></td>
                        <td>Gs. <span data-line-total>0,00</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($balances === []): ?>
                    <tr><td colspan="6" class="empty">Seleccione una o más órdenes para cargar items.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel__header totals-bar">
            <strong>Total de la nota</strong>
            <strong>Gs. <span data-form-total>0,00</span></strong>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar nota</button>
    </div>
</form>
