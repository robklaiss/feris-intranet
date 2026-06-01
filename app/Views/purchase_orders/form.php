<section class="page-head">
    <div>
        <p class="eyebrow">Órdenes</p>
        <h1>Nueva orden de compra</h1>
        <p class="muted">Puede nacer desde contrato o en carga manual.</p>
    </div>
    <a href="/purchase-orders" class="button button--secondary">Volver</a>
</section>

<form method="get" action="/purchase-orders/create" class="panel">
    <div class="form-grid compact">
        <label>
            <span>Contrato base</span>
            <select name="contract_id" onchange="this.form.submit()">
                <option value="">Carga manual</option>
                <?php foreach ($contracts as $contractOption): ?>
                    <option value="<?= e((string) $contractOption['id']) ?>" <?= (string) $order['contract_id'] === (string) $contractOption['id'] ? 'selected' : '' ?>>
                        <?= e($contractOption['contract_number']) ?> · <?= e($contractOption['client_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</form>

<form method="post" action="/purchase-orders" class="form-stack" data-calc-totals>
    <?= csrf_field() ?>
    <section class="alert alert--info">
        La orden se guarda como borrador. Solo una orden confirmada puede alimentar notas internas.
    </section>
    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Fecha</span>
                <input type="date" name="order_date" value="<?= e($order['order_date']) ?>" required>
            </label>
            <label>
                <span>Número orden</span>
                <input type="text" name="order_number" value="<?= e($order['order_number']) ?>" placeholder="Se autogenera si queda vacío">
            </label>
            <label>
                <span>Número ID / referencia</span>
                <input type="text" name="identifier_number" value="<?= e($order['identifier_number']) ?>">
            </label>
            <label>
                <span>Cliente</span>
                <select name="client_id">
                    <option value="">Seleccionar</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= e((string) $client['id']) ?>" <?= (string) $order['client_id'] === (string) $client['id'] ? 'selected' : '' ?>>
                            <?= e($client['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <input type="hidden" name="contract_id" value="<?= e((string) $order['contract_id']) ?>">
            <label>
                <span>Tipo o modalidad de contrato</span>
                <input type="text" name="contract_type" value="<?= e($order['contract_type'] ?? '') ?>">
            </label>
            <label>
                <span>RUC</span>
                <input type="text" name="tax_id" value="<?= e($order['tax_id'] ?? '') ?>">
            </label>
            <label class="checkbox">
                <input type="checkbox" name="is_provisional" value="1">
                <span>Datos provisorios</span>
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3"><?= e($order['notes']) ?></textarea>
            </label>
            <label class="span-2">
                <span>Detalle provisorio</span>
                <textarea name="provisional_data" rows="3"><?= e($order['provisional_data']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Items</h2>
            <?php if (!$selectedContract): ?>
                <button type="button" class="button button--secondary" data-add-item="#manual-order-items">Agregar item</button>
            <?php endif; ?>
        </div>
        <?php if ($selectedContract): ?>
            <div class="stack-sm">
                <div class="notice">Contrato base: <?= e($selectedContract['contract_number']) ?>. Solo se permiten cantidades dentro del saldo disponible.</div>
                <?php if (!empty($selectedContract['is_provisional'])): ?>
                    <div class="notice">Contrato provisorio: cliente, Nro. ID, modalidad y RUC quedarán vinculados en vivo hasta que el contrato deje de ser provisorio. Los ítems y precios de la orden quedan congelados.</div>
                <?php endif; ?>
            </div>
            <div class="table-wrap">
                <table class="table table--form">
                    <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Contratado</th>
                        <th>Consumido</th>
                        <th>Saldo</th>
                        <th>Cantidad a ordenar</th>
                        <th>Precio</th>
                        <th>Total item</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contractBalances as $balance): ?>
                        <tr>
                            <td>
                                <?= e($balance['product_name']) ?>
                                <input type="hidden" name="contract_item_id[]" value="<?= e((string) $balance['id']) ?>">
                                <input type="hidden" name="product_name[]" value="<?= e($balance['product_name']) ?>">
                                <input type="hidden" name="unit_measure[]" value="<?= e($balance['unit_measure']) ?>">
                                <input type="hidden" name="unit_price[]" value="<?= e((string) $balance['unit_price']) ?>" data-price>
                            </td>
                            <td><?= e((string) $balance['quantity']) ?> <?= e($balance['unit_measure']) ?></td>
                            <td><?= e((string) $balance['consumed_quantity']) ?> <?= e($balance['unit_measure']) ?></td>
                            <td><?= e((string) $balance['remaining_quantity']) ?> <?= e($balance['unit_measure']) ?></td>
                            <td><input type="number" step="0.0001" name="quantity[]" value="0" data-quantity></td>
                            <td>Gs. <?= e(money($balance['unit_price'])) ?></td>
                            <td>Gs. <span data-line-total>0,00</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($contractBalances === []): ?>
                        <tr><td colspan="7" class="empty">El contrato seleccionado no tiene saldo disponible para ordenar.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--form">
                    <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Unidad</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Total item</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="manual-order-items">
                    <tr data-item-row>
                        <td><input type="hidden" name="contract_item_id[]" value=""><input type="text" name="product_name[]" value=""></td>
                        <td><input type="text" name="unit_measure[]" value=""></td>
                        <td><input type="number" step="0.0001" name="quantity[]" value="" data-quantity></td>
                        <td><input type="number" step="0.01" name="unit_price[]" value="" data-price></td>
                        <td><input type="text" value="0,00" data-line-total readonly tabindex="-1"></td>
                        <td><button type="button" class="icon-button" data-remove-row>&times;</button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="panel__header">
            <strong>Total de la orden</strong>
            <strong>Gs. <span data-form-total>0,00</span></strong>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar orden</button>
    </div>
</form>
