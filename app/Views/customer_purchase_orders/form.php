<?php
$currentBySpec = [];
foreach (($order['items'] ?? []) as $item) {
    $currentBySpec[(int) $item['contract_item_spec_id']] = $item;
}
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Orden de compra cliente</p>
        <h1><?= e($title) ?></h1>
        <p class="muted"><?= e($contract['client_name'] ?? '') ?> · Contrato <?= e($contract['contract_number'] ?? '') ?></p>
    </div>
</section>

<form method="post" action="<?= e($action) ?>" class="panel form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="contract_id" value="<?= e((string) ($order['contract_id'] ?? $contract['id'] ?? '')) ?>">

    <label>Número OC
        <input type="text" name="po_number" value="<?= e($order['po_number'] ?? '') ?>" required>
    </label>
    <label>Fecha OC
        <input type="date" name="po_date" value="<?= e($order['po_date'] ?? date('Y-m-d')) ?>" required>
    </label>
    <label>Fecha recepción
        <input type="date" name="received_date" value="<?= e($order['received_date'] ?? date('Y-m-d')) ?>" required>
    </label>
    <label>Dependencia
        <select name="dependency_id">
            <option value="">Sin dependencia</option>
            <?php foreach ($dependencies as $dependency): ?>
                <option value="<?= e((string) $dependency['id']) ?>" <?= (string) ($order['dependency_id'] ?? '') === (string) $dependency['id'] ? 'selected' : '' ?>><?= e($dependency['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Contacto facturación
        <select name="billing_contact_id">
            <option value="">Sin contacto</option>
            <?php foreach ($billingContacts as $contact): ?>
                <option value="<?= e((string) $contact['id']) ?>" <?= (string) ($order['billing_contact_id'] ?? '') === (string) $contact['id'] ? 'selected' : '' ?>><?= e($contact['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Adjunto
        <input type="text" name="attachment_path" value="<?= e($order['attachment_path'] ?? '') ?>">
    </label>
    <label class="span-full">Observaciones
        <textarea name="notes" rows="3"><?= e($order['notes'] ?? '') ?></textarea>
    </label>

    <div class="span-full table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Item técnico</th>
                <th>Descripción</th>
                <th>Saldo contrato</th>
                <th>Cantidad OC</th>
                <th>Notas</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($availableItems as $item): ?>
                <?php
                $current = $currentBySpec[(int) $item['id']] ?? null;
                $available = (float) ($item['available_quantity'] ?? 0) + (float) ($current['quantity'] ?? 0);
                ?>
                <tr>
                    <td>
                        <input type="hidden" name="contract_item_spec_id[]" value="<?= e((string) $item['id']) ?>">
                        <strong><?= e($item['item_code']) ?></strong><br>
                        <span class="muted"><?= e($item['product_type']) ?></span>
                    </td>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e((string) $available) ?> <?= e($item['unit']) ?></td>
                    <td><input type="number" step="0.01" min="0" max="<?= e((string) $available) ?>" name="quantity[]" value="<?= e((string) ($current['quantity'] ?? '')) ?>"></td>
                    <td><input type="text" name="notes_item[]" value="<?= e($current['notes'] ?? '') ?>"></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($availableItems === []): ?>
                <tr><td colspan="5" class="empty">No hay ítems técnicos confirmados con saldo disponible.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions span-full">
        <a href="/contracts/<?= e((string) ($contract['id'] ?? $order['contract_id'] ?? '')) ?>" class="button button--secondary">Cancelar</a>
        <button type="submit" class="button">Guardar</button>
    </div>
</form>
