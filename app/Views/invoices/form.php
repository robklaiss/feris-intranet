<?php $selectedRemissionIds = $invoice['remission_ids'] ?? []; ?>

<section class="page-head">
    <div>
        <p class="eyebrow">Facturas</p>
        <h1>Nueva factura</h1>
        <p class="muted">Base local preparada para futura integración SIFEN.</p>
    </div>
    <a href="/invoices" class="button button--secondary">Volver</a>
</section>

<form method="get" action="/invoices/create" class="panel form-stack">
    <div class="form-grid compact">
        <label>
            <span>Contrato base</span>
            <select name="contract_id" onchange="this.form.submit()">
                <option value="">Sin filtro</option>
                <?php foreach ($contracts as $contractOption): ?>
                    <option value="<?= e((string) $contractOption['id']) ?>" <?= (string) $invoice['contract_id'] === (string) $contractOption['id'] ? 'selected' : '' ?>>
                        <?= e($contractOption['contract_number']) ?> · <?= e($contractOption['client_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="selector-grid">
        <?php foreach ($remissions as $remissionItem): ?>
            <?php if (!empty($invoice['contract_id']) && (string) $remissionItem['contract_id'] !== (string) $invoice['contract_id']) {
                continue;
            } ?>
            <label class="selector-card">
                <input type="checkbox" name="remission_ids[]" value="<?= e((string) $remissionItem['id']) ?>" <?= in_array((int) $remissionItem['id'], $selectedRemissionIds, true) ? 'checked' : '' ?>>
                <span>
                    <strong><?= e($remissionItem['remission_number']) ?></strong>
                    <small><?= e($remissionItem['client_name']) ?> · Contrato <?= e($remissionItem['contract_number']) ?></small>
                </span>
            </label>
        <?php endforeach; ?>
        <?php if ($remissions === []): ?>
            <p class="empty">No hay remisiones abiertas.</p>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="button button--secondary">Construir factura</button>
    </div>
</form>

<form method="post" action="/invoices" class="form-stack" data-calc-totals>
    <?= csrf_field() ?>
    <input type="hidden" name="contract_id" value="<?= e((string) ($invoice['contract_id'] ?? '')) ?>">
    <input type="hidden" name="remission_ids" value="<?= e(implode(',', $selectedRemissionIds)) ?>">
    <section class="alert alert--info">
        La factura nace en borrador. Confirmala antes de enviarla al placeholder SIFEN.
    </section>
    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Fecha</span>
                <input type="date" name="invoice_date" value="<?= e($invoice['invoice_date']) ?>">
            </label>
            <label>
                <span>Número</span>
                <input type="text" name="invoice_number" value="" placeholder="Se autogenera si queda vacío">
            </label>
            <label>
                <span>Condición de venta</span>
                <select name="sale_condition">
                    <option value="contado" <?= ($invoice['sale_condition'] ?? 'contado') === 'contado' ? 'selected' : '' ?>>Contado</option>
                    <option value="credito" <?= ($invoice['sale_condition'] ?? '') === 'credito' ? 'selected' : '' ?>>Crédito</option>
                </select>
            </label>
            <label>
                <span>Cliente</span>
                <select name="client_id">
                    <option value="">Seleccionar</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= e((string) $client['id']) ?>" <?= (string) $invoice['client_id'] === (string) $client['id'] ? 'selected' : '' ?>>
                            <?= e($client['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Número de contrato</span>
                <input type="text" value="<?= e($context['document']['contract_number'] ?? $selectedContract['contract_number'] ?? '') ?>" readonly tabindex="-1">
            </label>
            <label>
                <span>N° de ID</span>
                <input type="text" name="reference_number" value="<?= e($invoice['reference_number'] ?? '') ?>">
            </label>
            <label>
                <span>Tipo o modalidad de contrato</span>
                <input type="text" name="contract_type" value="<?= e($invoice['contract_type'] ?? '') ?>">
            </label>
            <label>
                <span>RUC</span>
                <input type="text" name="tax_id" value="<?= e($invoice['tax_id'] ?? '') ?>">
            </label>
            <label class="span-2">
                <span>Dirección</span>
                <textarea name="billing_address" rows="2"><?= e($invoice['billing_address'] ?? '') ?></textarea>
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3"><?= e($invoice['notes']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Items facturables desde remisiones</h2>
            <span class="muted">Los ítems se generan desde las remisiones seleccionadas.</span>
        </div>
        <div class="table-wrap">
            <table class="table table--form">
                <thead>
                <tr>
                    <th>Remisión</th>
                    <th>Producto</th>
                    <th>Saldo</th>
                    <th>Cantidad a facturar</th>
                    <th>Precio</th>
                    <th>Total item</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($balances as $balance): ?>
                    <tr>
                        <td>
                            <?= e($balance['remission_number'] ?? '') ?>
                            <input type="hidden" name="remission_id[]" value="<?= e((string) ($balance['remission_id'] ?? '')) ?>">
                            <input type="hidden" name="remission_item_id[]" value="<?= e((string) $balance['id']) ?>">
                            <input type="hidden" name="product_name[]" value="<?= e($balance['product_name']) ?>">
                            <input type="hidden" name="unit_measure[]" value="<?= e($balance['unit_measure']) ?>">
                            <input type="hidden" name="unit_price[]" value="<?= e((string) $balance['unit_price']) ?>" data-price>
                        </td>
                        <td><?= e($balance['product_name']) ?></td>
                        <td><?= e((string) $balance['remaining_quantity']) ?> <?= e($balance['unit_measure']) ?></td>
                        <td><input type="number" step="0.0001" name="quantity[]" value="<?= e((string) $balance['suggested_quantity']) ?>" data-quantity></td>
                        <td>Gs. <?= e(money($balance['unit_price'])) ?></td>
                        <td>Gs. <span data-line-total>0,00</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($balances === []): ?>
                    <tr><td colspan="6" class="empty">Seleccione remisiones para construir los ítems.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel__header totals-bar">
            <strong>Total de la factura</strong>
            <strong>Gs. <span data-form-total>0,00</span></strong>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar factura</button>
    </div>
</form>
