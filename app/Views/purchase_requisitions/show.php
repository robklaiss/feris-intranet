<?php
$requestedSupplierIds = array_map(static fn (array $request): int => (int) $request['supplier_id'], $requisition['quote_requests']);
$approvedQuote = null;
foreach ($requisition['quotes'] as $quote) {
    if (($quote['status'] ?? '') === 'approved') {
        $approvedQuote = $quote;
        break;
    }
}
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Pedido de presupuesto</p>
        <h1><?= e($requisition['requisition_number']) ?></h1>
        <p class="muted">Stock check <a href="/stock-checks/<?= e((string) $requisition['stock_check_id']) ?>"><?= e($requisition['check_number']) ?></a> · OP <a href="/production-orders/<?= e((string) $requisition['production_order_id']) ?>"><?= e($requisition['production_number']) ?></a></p>
        <span class="<?= e(status_badge_class($requisition['status'])) ?>"><?= e(purchase_requisition_status_label($requisition['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/purchase-requisitions" class="button button--secondary">Volver</a>
        <a href="/supplier-purchase-orders" class="button button--secondary">OC proveedor</a>
        <?php if ($approvedQuote && empty($requisition['purchase_order']) && can('documents.create')): ?>
            <a href="/supplier-purchase-orders/from-quote/<?= e((string) $approvedQuote['id']) ?>/create" class="button">Generar OC ISO 9001</a>
        <?php elseif (!empty($requisition['purchase_order'])): ?>
            <a href="/supplier-purchase-orders/<?= e((string) $requisition['purchase_order']['id']) ?>" class="button">Ver OC proveedor</a>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($requisition['client_name'] ?? '-') ?></dd></div>
            <div><dt>Producción</dt><dd><?= e($requisition['production_number']) ?></dd></div>
            <div><dt>Verificación</dt><dd><?= e($requisition['check_number']) ?></dd></div>
            <div><dt>Solicitado</dt><dd><?= e(format_datetime($requisition['requested_at'])) ?></dd></div>
            <div><dt>Notas</dt><dd><?= nl2br(e($requisition['notes'] ?? '-')) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('purchase_requisitions', (int) $requisition['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Faltantes solicitados</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Insumo requerido</th>
                <th>Faltante</th>
                <th>Solicitado</th>
                <th>Notas</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requisition['items'] as $item): ?>
                <tr>
                    <td>
                        <strong><?= e($item['required_material_type']) ?></strong><br>
                        <span class="muted"><?= e($item['required_description']) ?></span>
                    </td>
                    <td><?= e((string) $item['missing_quantity']) ?> <?= e($item['required_unit']) ?></td>
                    <td><?= e((string) $item['requested_quantity']) ?> <?= e($item['required_unit']) ?></td>
                    <td><?= e($item['notes'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (can('documents.create')): ?>
    <section class="panel">
        <h2>Solicitar a proveedores</h2>
        <form method="post" action="/purchase-requisitions/<?= e((string) $requisition['id']) ?>/suppliers">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label>
                    <span>Fecha límite respuesta</span>
                    <input type="date" name="response_due_date">
                </label>
                <label class="span-2">
                    <span>Observaciones</span>
                    <textarea name="notes" rows="2"></textarea>
                </label>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Proveedor</th>
                        <th>RUC</th>
                        <th>Condiciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><input type="checkbox" name="supplier_id[]" value="<?= e((string) $supplier['id']) ?>" <?= in_array((int) $supplier['id'], $requestedSupplierIds, true) ? 'checked disabled' : '' ?>></td>
                            <td><?= e($supplier['name']) ?></td>
                            <td><?= e($supplier['ruc'] ?? '-') ?></td>
                            <td><?= e($supplier['payment_terms'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($suppliers === []): ?>
                        <tr><td colspan="4" class="empty">No hay proveedores activos.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button type="submit" class="button">Registrar solicitudes</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Proveedores solicitados</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Proveedor</th>
                <th>Enviado</th>
                <th>Vence</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requisition['quote_requests'] as $quoteRequest): ?>
                <tr>
                    <td>
                        <strong><?= e($quoteRequest['supplier_name']) ?></strong><br>
                        <span class="muted"><?= e($quoteRequest['supplier_ruc'] ?? '') ?></span>
                    </td>
                    <td><?= e(format_datetime($quoteRequest['sent_at'])) ?></td>
                    <td><?= e($quoteRequest['response_due_date'] ?? '-') ?></td>
                    <td><span class="<?= e(status_badge_class($quoteRequest['status'])) ?>"><?= e($quoteRequest['status']) ?></span></td>
                    <td class="actions">
                        <?php if (can('documents.create')): ?>
                            <a href="/purchase-requisitions/<?= e((string) $requisition['id']) ?>/suppliers/<?= e((string) $quoteRequest['supplier_id']) ?>/quotes/create">Registrar presupuesto</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($requisition['quote_requests'] === []): ?>
                <tr><td colspan="5" class="empty">No hay proveedores solicitados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Comparación de presupuestos</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Proveedor</th>
                <th>Presupuesto</th>
                <th>Total</th>
                <th>Entrega</th>
                <th>Pago</th>
                <th>Observaciones</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requisition['quotes'] as $quote): ?>
                <tr id="quote-<?= e((string) $quote['id']) ?>">
                    <td><?= e($quote['supplier_name']) ?></td>
                    <td><?= e($quote['quote_number']) ?><br><span class="muted"><?= e($quote['quote_date']) ?></span></td>
                    <td><strong><?= e($quote['currency']) ?> <?= e(money($quote['total_amount'])) ?></strong></td>
                    <td><?= e((string) ($quote['delivery_days'] ?? '-')) ?> días</td>
                    <td><?= e($quote['payment_terms'] ?? '-') ?></td>
                    <td><?= e($quote['notes'] ?? '-') ?></td>
                    <td><span class="<?= e(status_badge_class($quote['status'])) ?>"><?= e(supplier_quote_status_label($quote['status'])) ?></span></td>
                    <td class="actions">
                        <?php if (can('documents.transition') && !in_array($quote['status'], ['approved', 'rejected', 'cancelled'], true)): ?>
                            <form method="post" action="/supplier-quotes/<?= e((string) $quote['id']) ?>/approve">
                                <?= csrf_field() ?>
                                <?php if (count($requisition['quote_requests']) < 3): ?>
                                    <input type="text" name="override_reason" placeholder="Motivo override admin">
                                <?php endif; ?>
                                <button type="submit" class="button">Aprobar</button>
                            </form>
                        <?php endif; ?>
                        <?php if (($quote['status'] ?? '') === 'approved' && empty($requisition['purchase_order']) && can('documents.create')): ?>
                            <a href="/supplier-purchase-orders/from-quote/<?= e((string) $quote['id']) ?>/create">Generar OC</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($requisition['quotes'] === []): ?>
                <tr><td colspan="8" class="empty">No hay presupuestos recibidos.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
