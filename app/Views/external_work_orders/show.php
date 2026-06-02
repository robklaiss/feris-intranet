<section class="page-head">
    <div>
        <p class="eyebrow">Trabajo externo</p>
        <h1><?= e($order['external_work_number']) ?></h1>
        <p class="muted">
            Producción <a href="/production-orders/<?= e((string) $order['production_order_id']) ?>"><?= e($order['production_number']) ?></a>
            · Corte <a href="/cutting-orders/<?= e((string) $order['cutting_order_id']) ?>"><?= e($order['cutting_number']) ?></a>
            · <?= e($order['client_name']) ?>
            · Contrato <?= e($order['contract_number']) ?>
        </p>
        <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(external_work_order_status_label($order['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/external-work-orders" class="button button--secondary">Volver</a>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'draft'): ?>
            <form method="post" action="/external-work-orders/<?= e((string) $order['id']) ?>/send">
                <?= csrf_field() ?>
                <input type="hidden" name="send_note_number" value="<?= e($order['send_note_number']) ?>">
                <button type="submit" class="button">Enviar</button>
            </form>
            <form method="post" action="/external-work-orders/<?= e((string) $order['id']) ?>/cancel" data-confirm="Anular este trabajo externo en borrador?">
                <?= csrf_field() ?>
                <button type="submit" class="button button--secondary">Anular</button>
            </form>
        <?php endif; ?>
        <?php if (can('documents.create') && in_array(($order['status'] ?? ''), ['sent', 'partially_returned'], true)): ?>
            <a href="/external-work-orders/<?= e((string) $order['id']) ?>/receipts/create" class="button">Registrar retorno</a>
        <?php endif; ?>
        <?php if (can('documents.transition') && ($order['status'] ?? '') === 'returned'): ?>
            <form method="post" action="/external-work-orders/<?= e((string) $order['id']) ?>/close">
                <?= csrf_field() ?>
                <button type="submit" class="button button--secondary">Cerrar</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Proveedor</dt><dd><?= e($order['supplier_name'] ?? '-') ?></dd></div>
            <div><dt>Tipo</dt><dd><?= e(external_work_type_label($order['work_type'])) ?></dd></div>
            <div><dt>Nota de envío</dt><dd><?= e($order['send_note_number'] ?? '-') ?></dd></div>
            <div><dt>Enviado</dt><dd><?= e(format_datetime($order['sent_at'])) ?></dd></div>
            <div><dt>Retorno esperado</dt><dd><?= e($order['expected_return_date'] ?? '-') ?></dd></div>
            <div><dt>Retornado</dt><dd><?= e(format_datetime($order['returned_at'])) ?></dd></div>
            <div><dt>Próxima etapa</dt><dd><?= e(external_next_stage_label($order['next_stage'])) ?></dd></div>
            <div><dt>Etapa producción</dt><dd><?= e(production_stage_label($order['production_stage'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($order['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('external_work_orders', (int) $order['id']),
        ]) ?>
    </article>
</section>

<section class="panel">
    <h2>Ítems enviados</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>Talle</th>
                <th>Color</th>
                <th>Enviado</th>
                <th>Retornado</th>
                <th>Rechazado</th>
                <th>Trabajo</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td><strong><?= e($item['item_code']) ?></strong></td>
                    <td><?= e($item['description']) ?><br><span class="muted"><?= e($item['notes']) ?></span></td>
                    <td><?= e($item['size']) ?></td>
                    <td><?= e($item['color']) ?></td>
                    <td><?= e((string) $item['quantity_sent']) ?></td>
                    <td><?= e((string) $item['quantity_returned']) ?></td>
                    <td><?= e((string) $item['quantity_rejected']) ?></td>
                    <td><?= e($item['work_details']) ?></td>
                    <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(external_work_item_status_label($item['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Recepciones</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Estado</th><th>Recibido</th><th>Próxima etapa</th><th>Observaciones</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($order['receipts'] as $receipt): ?>
                <tr>
                    <td><strong><?= e($receipt['receipt_number']) ?></strong></td>
                    <td><span class="<?= e(status_badge_class($receipt['status'])) ?>"><?= e(external_work_receipt_status_label($receipt['status'])) ?></span></td>
                    <td><?= e(format_datetime($receipt['received_at'])) ?></td>
                    <td><?= e(external_next_stage_label($receipt['next_stage'])) ?></td>
                    <td><?= e($receipt['notes']) ?></td>
                    <td>
                        <?php if (can('documents.transition') && ($receipt['status'] ?? '') === 'draft'): ?>
                            <form method="post" action="/external-work-orders/<?= e((string) $order['id']) ?>/receipts/<?= e((string) $receipt['id']) ?>/confirm" data-confirm="Confirmar esta recepción externa?">
                                <?= csrf_field() ?>
                                <button type="submit" class="button">Confirmar</button>
                            </form>
                        <?php elseif (can('documents.create') && ($sewingAvailableByReceipt[(int) $receipt['id']] ?? 0) > 0): ?>
                            <a href="/external-work-orders/<?= e((string) $order['id']) ?>/receipts/<?= e((string) $receipt['id']) ?>/sewing-orders/create" class="button">Crear confección</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php foreach (($receipt['items'] ?? []) as $receiptItem): ?>
                    <tr>
                        <td colspan="2" class="muted"><?= e($receiptItem['item_code']) ?> · <?= e($receiptItem['description']) ?></td>
                        <td colspan="4">
                            Recibido <?= e((string) $receiptItem['quantity_received']) ?> ·
                            Aceptado <?= e((string) $receiptItem['quantity_accepted']) ?> ·
                            Rechazado <?= e((string) $receiptItem['quantity_rejected']) ?> ·
                            <?= e($receiptItem['quality_notes']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if ($order['receipts'] === []): ?>
                <tr><td colspan="6" class="empty">Sin recepciones registradas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Órdenes de confección asociadas</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Número</th><th>Costurero</th><th>Recepción</th><th>Estado</th><th>Asignación</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach (($sewingOrders ?? []) as $sewingOrder): ?>
                <tr>
                    <td><strong><?= e($sewingOrder['sewing_number']) ?></strong></td>
                    <td><?= e($sewingOrder['seamster_name']) ?></td>
                    <td><?= e($sewingOrder['receipt_number'] ?? '-') ?></td>
                    <td><span class="<?= e(status_badge_class($sewingOrder['status'])) ?>"><?= e(sewing_order_status_label($sewingOrder['status'])) ?></span></td>
                    <td><?= e(format_datetime($sewingOrder['assigned_at'])) ?></td>
                    <td><a href="/sewing-orders/<?= e((string) $sewingOrder['id']) ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($sewingOrders ?? []) === []): ?>
                <tr><td colspan="6" class="empty">Sin órdenes de confección asociadas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
