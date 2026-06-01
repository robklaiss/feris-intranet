<section class="page-head">
    <div>
        <p class="eyebrow">Contratos</p>
        <h1><?= e($title) ?></h1>
    </div>
    <a href="/contracts" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="form-stack" data-calc-totals>
    <?= csrf_field() ?>
    <?php $dncp = $contract['dncp_data'] ?? []; ?>
    <section class="alert alert--info">
        El contrato se guarda como borrador. Confirmalo desde el detalle cuando quede listo para operar.
    </section>
    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Fecha</span>
                <input type="date" name="date" value="<?= e($contract['date']) ?>" required>
            </label>
            <label>
                <span>Número de contrato</span>
                <input type="text" name="contract_number" value="<?= e($contract['contract_number']) ?>" required>
            </label>
            <label>
                <span>Número ID</span>
                <input type="text" name="reference_number" value="<?= e($contract['reference_number']) ?>">
            </label>
            <label>
                <span>Modalidad</span>
                <input type="text" name="contract_type" value="<?= e($contract['contract_type']) ?>">
            </label>
            <label>
                <span>Cliente</span>
                <select name="client_id">
                    <option value="">Seleccionar</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= e((string) $client['id']) ?>" <?= (string) $contract['client_id'] === (string) $client['id'] ? 'selected' : '' ?>>
                            <?= e($client['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>RUC</span>
                <input type="text" name="tax_id" value="<?= e($contract['tax_id']) ?>">
            </label>
            <label class="checkbox">
                <input type="checkbox" name="is_provisional" value="1" <?= !empty($contract['is_provisional']) ? 'checked' : '' ?>>
                <span>Datos provisorios</span>
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="4"><?= e($contract['notes']) ?></textarea>
            </label>
            <label class="span-2">
                <span>Detalle provisorio</span>
                <textarea name="provisional_data" rows="4"><?= e($contract['provisional_data']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>DNCP / Licitación</h2>
            <span class="muted">Datos administrativos asociados al contrato</span>
        </div>
        <div class="form-grid">
            <label>
                <span>ID de licitación</span>
                <input type="text" name="dncp_tender_id" value="<?= e($dncp['tender_id'] ?? '') ?>">
            </label>
            <label>
                <span>Número de contrato</span>
                <input type="text" name="dncp_contract_number" value="<?= e($dncp['contract_number'] ?? '') ?>">
            </label>
            <label>
                <span>Orden de compra del cliente</span>
                <input type="text" name="dncp_customer_purchase_order_number" value="<?= e($dncp['customer_purchase_order_number'] ?? '') ?>">
            </label>
            <label>
                <span>Entidad convocante</span>
                <input type="text" name="dncp_public_entity" value="<?= e($dncp['public_entity'] ?? '') ?>">
            </label>
            <label>
                <span>Dependencia solicitante</span>
                <input type="text" name="dncp_requesting_dependency" value="<?= e($dncp['requesting_dependency'] ?? '') ?>">
            </label>
            <label>
                <span>Modalidad</span>
                <input type="text" name="dncp_procurement_modality" value="<?= e($dncp['procurement_modality'] ?? '') ?>">
            </label>
            <label>
                <span>Código de contratación</span>
                <input type="text" name="dncp_procurement_code" value="<?= e($dncp['procurement_code'] ?? '') ?>">
            </label>
            <label>
                <span>Fecha de contrato</span>
                <input type="date" name="dncp_contract_date" value="<?= e($dncp['contract_date'] ?? '') ?>">
            </label>
            <label>
                <span>Vigencia desde</span>
                <input type="date" name="dncp_valid_from" value="<?= e($dncp['valid_from'] ?? '') ?>">
            </label>
            <label>
                <span>Vigencia hasta</span>
                <input type="date" name="dncp_valid_until" value="<?= e($dncp['valid_until'] ?? '') ?>">
            </label>
            <label>
                <span>Moneda</span>
                <input type="text" name="dncp_currency" value="<?= e($dncp['currency'] ?? 'PYG') ?>">
            </label>
            <label>
                <span>Razón social fiscal</span>
                <input type="text" name="dncp_fiscal_business_name" value="<?= e($dncp['fiscal_business_name'] ?? '') ?>">
            </label>
            <label>
                <span>RUC fiscal</span>
                <input type="text" name="dncp_fiscal_ruc" value="<?= e($dncp['fiscal_ruc'] ?? '') ?>">
            </label>
            <label>
                <span>Contacto de facturación</span>
                <select name="dncp_billing_contact_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($billingContacts as $contact): ?>
                        <?php $label = $contact['client_name'] . ' · ' . $contact['name'] . (!empty($contact['dependency_name']) ? ' · ' . $contact['dependency_name'] : ''); ?>
                        <option value="<?= e((string) $contact['id']) ?>" <?= (string) ($dncp['billing_contact_id'] ?? '') === (string) $contact['id'] ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="dncp_notes" rows="4"><?= e($dncp['notes'] ?? '') ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Items del contrato</h2>
            <button type="button" class="button button--secondary" data-add-item="#contract-items-body">Agregar item</button>
        </div>
        <div class="table-wrap">
            <table class="table table--form">
                <thead>
                <tr>
                    <th>Producto</th>
                    <th>Unidad</th>
                    <th>Cantidad</th>
                    <th>Precio unitario</th>
                    <th>Total item</th>
                    <th>Observación</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="contract-items-body">
                <?php foreach ($contract['items'] as $item): ?>
                    <tr data-item-row>
                        <td><input type="text" name="product_name[]" value="<?= e($item['product_name']) ?>"></td>
                        <td><input type="text" name="unit_measure[]" value="<?= e($item['unit_measure']) ?>"></td>
                        <td><input type="number" step="0.0001" name="quantity[]" value="<?= e((string) $item['quantity']) ?>" data-quantity></td>
                        <td><input type="number" step="0.01" name="unit_price[]" value="<?= e((string) $item['unit_price']) ?>" data-price></td>
                        <td><input type="text" value="<?= e(money($item['total_item'] ?? 0)) ?>" data-line-total readonly tabindex="-1"></td>
                        <td><input type="text" name="notes[]" value="<?= e($item['notes'] ?? '') ?>"></td>
                        <td><button type="button" class="icon-button" data-remove-row>&times;</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panel__header">
            <strong>Total del contrato</strong>
            <strong>Gs. <span data-form-total><?= e(money($contract['total_amount'] ?? 0)) ?></span></strong>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar contrato</button>
    </div>
</form>
