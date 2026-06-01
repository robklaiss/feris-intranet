<section class="page-head">
    <div>
        <p class="eyebrow">Contrato</p>
        <h1><?= e($contract['contract_number']) ?></h1>
        <p class="muted"><?= e($contract['client_name'] ?: 'Sin cliente') ?> · <?= e($contract['date']) ?></p>
        <span class="<?= e(status_badge_class($meta['document']['status'])) ?>"><?= e(document_status_label($meta['document']['status'])) ?></span>
    </div>
    <?= \App\Support\View::partial('partials/document_actions', [
        'type' => 'contracts',
        'document' => $contract,
        'meta' => $meta,
        'create_url' => '/customer-purchase-orders/create?contract_id=' . (int) $contract['id'],
        'create_label' => 'Crear orden de compra cliente',
        'edit_url' => '/contracts/' . (int) $contract['id'] . '/edit',
        'print_url' => '/contracts/' . (int) $contract['id'] . '/print',
        'export_url' => '/contracts/' . (int) $contract['id'] . '/export/csv',
        'delete_url' => '/contracts/' . (int) $contract['id'] . '/delete',
    ]) ?>
</section>

<?php if (!empty($meta['locks']['locked'])): ?>
    <section class="alert alert--warning">
        <?= e((string) $meta['locks']['reason']) ?>
    </section>
<?php endif; ?>
<?php if (!empty($meta['close_reason'])): ?>
    <section class="alert alert--info">
        <?= e((string) $meta['close_reason']) ?>
    </section>
<?php endif; ?>

<section class="grid-two">
    <article class="panel">
        <h2>Resumen</h2>
        <dl class="detail-list">
            <div><dt>Número ID</dt><dd><?= e($contract['reference_number']) ?></dd></div>
            <div><dt>Modalidad</dt><dd><?= e($contract['contract_type']) ?></dd></div>
            <div><dt>Estado</dt><dd><?= e(document_status_label($meta['document']['status'])) ?></dd></div>
            <div><dt>RUC</dt><dd><?= e($contract['tax_id']) ?></dd></div>
            <div><dt>Total</dt><dd>Gs. <?= e(money($contract['total_amount'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($contract['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Saldos</h2>
        <div class="list-stack">
            <?php foreach ($balances as $balance): ?>
                <div class="balance-item">
                    <strong><?= e($balance['product_name']) ?></strong>
                    <span>Contratado: <?= e((string) $balance['quantity']) ?> <?= e($balance['unit_measure']) ?></span>
                    <span>Consumido: <?= e((string) $balance['consumed_quantity']) ?></span>
                    <span class="<?= (float) $balance['remaining_quantity'] > 0.0001 ? 'text-warning' : 'text-success' ?>">Saldo: <?= e((string) $balance['remaining_quantity']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<?php $dncp = $contract['dncp_data'] ?? []; ?>
<?php if (!empty($dncp['id'])): ?>
    <section class="panel">
        <h2>DNCP / Licitación</h2>
        <dl class="detail-list">
            <div><dt>ID de licitación</dt><dd><?= e($dncp['tender_id'] ?? '') ?></dd></div>
            <div><dt>Número de contrato</dt><dd><?= e($dncp['contract_number'] ?? '') ?></dd></div>
            <div><dt>Orden de compra cliente</dt><dd><?= e($dncp['customer_purchase_order_number'] ?? '') ?></dd></div>
            <div><dt>Entidad convocante</dt><dd><?= e($dncp['public_entity'] ?? '') ?></dd></div>
            <div><dt>Dependencia solicitante</dt><dd><?= e($dncp['requesting_dependency'] ?? '') ?></dd></div>
            <div><dt>Modalidad</dt><dd><?= e($dncp['procurement_modality'] ?? '') ?></dd></div>
            <div><dt>Código</dt><dd><?= e($dncp['procurement_code'] ?? '') ?></dd></div>
            <div><dt>Fecha contrato</dt><dd><?= e($dncp['contract_date'] ?? '') ?></dd></div>
            <div><dt>Vigencia</dt><dd><?= e(trim((string) ($dncp['valid_from'] ?? '') . ' - ' . (string) ($dncp['valid_until'] ?? ''), ' -')) ?></dd></div>
            <div><dt>Moneda</dt><dd><?= e($dncp['currency'] ?? '') ?></dd></div>
            <div><dt>Razón social fiscal</dt><dd><?= e($dncp['fiscal_business_name'] ?? '') ?></dd></div>
            <div><dt>RUC fiscal</dt><dd><?= e($dncp['fiscal_ruc'] ?? '') ?></dd></div>
            <div><dt>Contacto facturación</dt><dd><?= e($dncp['billing_contact_name'] ?? '') ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($dncp['notes'] ?? '')) ?></dd></div>
        </dl>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Items</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Producto</th>
                <th>Unidad</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($contract['items'] as $item): ?>
                <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td><?= e($item['unit_measure']) ?></td>
                    <td><?= e((string) $item['quantity']) ?></td>
                    <td>Gs. <?= e(money($item['unit_price'])) ?></td>
                    <td>Gs. <?= e(money($item['total_item'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel__header">
        <div>
            <h2>Ítems técnicos / productos contratados</h2>
            <span class="muted">Especificaciones para textiles, consumo u otros productos</span>
        </div>
        <?php if (can('documents.create') && ($meta['can_edit'] ?? false)): ?>
            <a href="/contracts/<?= e((string) $contract['id']) ?>/item-specs/create" class="button">Agregar ítem técnico</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Código</th>
                <th>Tipo</th>
                <th>Categoria</th>
                <th>Descripción</th>
                <th>Talle/tamaño</th>
                <th>Color</th>
                <th>Cantidad</th>
                <th>Unidad</th>
                <th>Bordado</th>
                <th>Serigrafía</th>
                <th>Etiqueta</th>
                <th>Dependencia destino</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($contract['item_specs'] ?? []) as $spec): ?>
                <tr>
                    <td><strong><?= e($spec['item_code']) ?></strong></td>
                    <td><?= e($spec['product_type']) ?></td>
                    <td><?= e($spec['product_category']) ?></td>
                    <td><?= e($spec['description']) ?></td>
                    <td><?= e($spec['size']) ?></td>
                    <td><?= e($spec['color']) ?></td>
                    <td><?= e((string) $spec['quantity']) ?></td>
                    <td><?= e($spec['unit']) ?></td>
                    <td><?= !empty($spec['has_embroidery']) ? 'Sí' : 'No' ?></td>
                    <td><?= !empty($spec['has_screen_printing']) ? 'Sí' : 'No' ?></td>
                    <td><?= e($spec['label']) ?></td>
                    <td><?= e($spec['destination_dependency_name'] ?? '') ?></td>
                    <td><span class="<?= e(status_badge_class($spec['status'])) ?>"><?= e(document_status_label($spec['status'])) ?></span></td>
                    <td>
                        <div class="table-actions">
                            <?php if (can('documents.edit') && ($meta['can_edit'] ?? false) && ($spec['status'] ?? '') !== 'confirmed'): ?>
                                <a href="/contracts/<?= e((string) $contract['id']) ?>/item-specs/<?= e((string) $spec['id']) ?>/edit">Editar</a>
                            <?php endif; ?>
                            <?php if (can('documents.transition') && ($meta['can_edit'] ?? false) && ($spec['status'] ?? '') === 'draft'): ?>
                                <form method="post" action="/contracts/<?= e((string) $contract['id']) ?>/item-specs/<?= e((string) $spec['id']) ?>/confirm">
                                    <?= csrf_field() ?>
                                    <button type="submit">Confirmar</button>
                                </form>
                            <?php endif; ?>
                            <?php if (can('documents.transition') && ($meta['can_edit'] ?? false) && ($spec['status'] ?? '') !== 'cancelled'): ?>
                                <form method="post" action="/contracts/<?= e((string) $contract['id']) ?>/item-specs/<?= e((string) $spec['id']) ?>/cancel" data-confirm="Anular este ítem técnico?">
                                    <?= csrf_field() ?>
                                    <button type="submit">Anular</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($contract['item_specs'])): ?>
                <tr><td colspan="14" class="empty">No hay ítems técnicos cargados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel__header">
        <div>
            <h2>Órdenes de compra cliente</h2>
            <span class="muted">OC manuales asociadas a este contrato</span>
        </div>
        <?php if (can('documents.create') && ($contract['status'] ?? '') === 'confirmed'): ?>
            <a href="/customer-purchase-orders/create?contract_id=<?= e((string) $contract['id']) ?>" class="button">Crear orden de compra cliente</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Número OC</th>
                <th>Fecha</th>
                <th>Dependencia</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($customerPurchaseOrders ?? []) as $customerOrder): ?>
                <tr>
                    <td><strong><?= e($customerOrder['po_number']) ?></strong></td>
                    <td><?= e($customerOrder['po_date']) ?></td>
                    <td><?= e($customerOrder['dependency_name']) ?></td>
                    <td><span class="<?= e(status_badge_class($customerOrder['status'])) ?>"><?= e(document_status_label($customerOrder['status'])) ?></span></td>
                    <td><a href="/customer-purchase-orders/<?= e((string) $customerOrder['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($customerPurchaseOrders)): ?>
                <tr><td colspan="5" class="empty">No hay órdenes de compra cliente asociadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?= \App\Support\View::partial('partials/audit_trail', [
    'entries' => document_audit_entries('contracts', (int) $contract['id']),
]) ?>

<section class="panel">
    <div class="panel__header">
        <h2>Trazabilidad</h2>
        <span class="muted">Contrato -> orden -> nota -> remisión -> factura</span>
    </div>
    <div class="trace-list">
        <?php foreach ($traceability as $item): ?>
            <article class="trace-card">
                <strong><?= e($item['product_name']) ?></strong>
                <span>Contrato: <?= e((string) $item['quantity']) ?> <?= e($item['unit_measure']) ?></span>
                <?php foreach ($item['purchase_order_items'] as $orderItem): ?>
                    <span>Orden <?= e($orderItem['order_number']) ?>: <?= e((string) $orderItem['quantity']) ?></span>
                    <?php foreach ($orderItem['delivery_note_items'] as $deliveryItem): ?>
                        <span>Nota #<?= e($deliveryItem['note_number']) ?>: <?= e((string) $deliveryItem['quantity']) ?></span>
                        <?php foreach ($deliveryItem['remission_items'] as $remissionItem): ?>
                            <span>Remisión <?= e($remissionItem['remission_number']) ?>: <?= e((string) $remissionItem['quantity']) ?></span>
                            <?php foreach ($remissionItem['invoice_items'] as $invoiceItem): ?>
                                <span>Factura <?= e($invoiceItem['invoice_number']) ?>: <?= e((string) $invoiceItem['quantity']) ?></span>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
