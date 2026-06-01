<?php $selectedNoteIds = $remission['delivery_note_ids'] ?? []; ?>

<section class="page-head">
    <div>
        <p class="eyebrow">Remisiones</p>
        <h1>Nueva remisión</h1>
        <p class="muted">Seleccione una o varias notas y el sistema construye los ítems remisionables.</p>
    </div>
    <a href="/remissions" class="button button--secondary">Volver</a>
</section>

<form method="get" action="/remissions/create" class="panel form-stack">
    <div class="form-grid compact">
        <label>
            <span>Contrato base</span>
            <select name="contract_id" onchange="this.form.submit()">
                <option value="">Sin filtro</option>
                <?php foreach ($contracts as $contractOption): ?>
                    <option value="<?= e((string) $contractOption['id']) ?>" <?= (string) $remission['contract_id'] === (string) $contractOption['id'] ? 'selected' : '' ?>>
                        <?= e($contractOption['contract_number']) ?> · <?= e($contractOption['client_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="selector-grid">
        <?php foreach ($notesList as $noteItem): ?>
            <?php if (!empty($remission['contract_id']) && (string) $noteItem['contract_id'] !== (string) $remission['contract_id']) {
                continue;
            } ?>
            <label class="selector-card">
                <input type="checkbox" name="delivery_note_ids[]" value="<?= e((string) $noteItem['id']) ?>" <?= in_array((int) $noteItem['id'], $selectedNoteIds, true) ? 'checked' : '' ?>>
                <span>
                    <strong><?= e($noteItem['note_number']) ?></strong>
                    <small><?= e($noteItem['client_name']) ?> · Contrato <?= e($noteItem['contract_number']) ?></small>
                </span>
            </label>
        <?php endforeach; ?>
        <?php if ($notesList === []): ?>
            <p class="empty">No hay notas internas abiertas.</p>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="button button--secondary">Construir remisión</button>
    </div>
</form>

<form method="post" action="/remissions" class="form-stack" data-calc-totals>
    <?= csrf_field() ?>
    <input type="hidden" name="contract_id" value="<?= e((string) ($remission['contract_id'] ?? '')) ?>">
    <input type="hidden" name="delivery_note_ids" value="<?= e(implode(',', $selectedNoteIds)) ?>">
    <section class="alert alert--info">
        La remisión se guarda como borrador. Confirmala para habilitar la facturación placeholder.
    </section>
    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Fecha</span>
                <input type="date" name="remission_date" value="<?= e($remission['remission_date']) ?>">
            </label>
            <label>
                <span>Cliente</span>
                <select name="client_id">
                    <option value="">Seleccionar</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= e((string) $client['id']) ?>" <?= (string) $remission['client_id'] === (string) $client['id'] ? 'selected' : '' ?>>
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
                <input type="text" name="reference_number" value="<?= e($remission['reference_number'] ?? '') ?>">
            </label>
            <label>
                <span>Tipo o modalidad de contrato</span>
                <input type="text" name="contract_type" value="<?= e($remission['contract_type'] ?? '') ?>">
            </label>
            <label>
                <span>RUC</span>
                <input type="text" name="tax_id" value="<?= e($remission['tax_id'] ?? '') ?>">
            </label>
            <label class="span-2">
                <span>Dirección del punto de partida</span>
                <textarea name="origin_address" rows="2"><?= e($remission['origin_address'] ?? '') ?></textarea>
            </label>
            <label class="span-2">
                <span>Dirección del punto de llegada</span>
                <textarea name="destination_address" rows="2"><?= e($remission['destination_address'] ?? '') ?></textarea>
            </label>
            <label>
                <span>Fecha del inicio del traslado</span>
                <input type="date" name="transfer_start_date" value="<?= e($remission['transfer_start_date'] ?? '') ?>">
            </label>
            <label>
                <span>Fecha del término del traslado</span>
                <input type="date" name="transfer_end_date" value="<?= e($remission['transfer_end_date'] ?? '') ?>">
            </label>
            <label>
                <span>Marca del vehículo</span>
                <input type="text" name="vehicle_brand" value="<?= e($remission['vehicle_brand'] ?? '') ?>">
            </label>
            <label>
                <span>Número de chapa</span>
                <input type="text" name="vehicle_plate" value="<?= e($remission['vehicle_plate'] ?? '') ?>">
            </label>
            <label>
                <span>Transportista</span>
                <input type="text" name="carrier_name" value="<?= e($remission['carrier_name'] ?? '') ?>">
            </label>
            <label>
                <span>RUC del transportista</span>
                <input type="text" name="carrier_tax_id" value="<?= e($remission['carrier_tax_id'] ?? '') ?>">
            </label>
            <label>
                <span>Nombre del conductor</span>
                <input type="text" name="driver_name" value="<?= e($remission['driver_name'] ?? '') ?>">
            </label>
            <label>
                <span>C.I. del conductor</span>
                <input type="text" name="driver_document" value="<?= e($remission['driver_document'] ?? '') ?>">
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3"><?= e($remission['notes']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Items a remitir</h2>
            <span class="muted">Los ítems se generan desde las notas seleccionadas.</span>
        </div>
        <div class="table-wrap">
            <table class="table table--form">
                <thead>
                <tr>
                    <th>Nota</th>
                    <th>Producto</th>
                    <th>Saldo</th>
                    <th>Cantidad a remitir</th>
                    <th>Precio</th>
                    <th>Total item</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($balances as $balance): ?>
                    <tr>
                        <td>
                            <?= e($balance['note_number'] ?? '') ?>
                            <input type="hidden" name="delivery_note_id[]" value="<?= e((string) ($balance['delivery_note_id'] ?? '')) ?>">
                            <input type="hidden" name="delivery_note_item_id[]" value="<?= e((string) $balance['id']) ?>">
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
                    <tr><td colspan="6" class="empty">Seleccione notas internas para construir los ítems.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel__header totals-bar">
            <strong>Total de la remisión</strong>
            <strong>Gs. <span data-form-total>0,00</span></strong>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar remisión</button>
    </div>
</form>
