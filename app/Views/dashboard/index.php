<section class="page-head">
    <div>
        <p class="eyebrow">Operación diaria</p>
        <h1>Dashboard</h1>
        <p class="muted">Accesos rápidos y visibilidad general del flujo contrato → factura.</p>
    </div>
</section>

<section class="stats-grid">
    <?php foreach ($stats as $stat): ?>
        <a href="<?= e($stat['href']) ?>" class="stat-card">
            <span><?= e($stat['label']) ?></span>
            <strong><?= e((string) $stat['value']) ?></strong>
        </a>
    <?php endforeach; ?>
</section>

<section class="grid-two">
    <article class="panel">
        <div class="panel__header">
            <h2>Contratos recientes</h2>
            <a href="/contracts" class="link-arrow">Ver todos</a>
        </div>
        <div class="list-stack">
            <?php foreach ($recentContracts as $contract): ?>
                <a class="list-item" href="/contracts/<?= e((string) $contract['id']) ?>">
                    <strong><?= e($contract['contract_number']) ?></strong>
                    <span><?= e($contract['client_name'] ?: 'Sin cliente') ?></span>
                    <span class="<?= e(status_badge_class($contract['status'])) ?>"><?= e(document_status_label($contract['status'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="panel">
        <div class="panel__header">
            <h2>Órdenes recientes</h2>
            <a href="/purchase-orders" class="link-arrow">Ver todas</a>
        </div>
        <div class="list-stack">
            <?php foreach ($recentOrders as $order): ?>
                <a class="list-item" href="/purchase-orders/<?= e((string) $order['id']) ?>">
                    <strong><?= e($order['order_number']) ?></strong>
                    <span><?= e($order['client_name'] ?: 'Sin cliente') ?></span>
                    <span class="<?= e(status_badge_class($order['status'])) ?>"><?= e(document_status_label($order['status'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>
 </section>

<section class="panel">
    <div class="panel__header">
        <h2>Facturas recientes</h2>
        <a href="/invoices" class="link-arrow">Ver todas</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recentInvoices as $invoice): ?>
                <tr>
                    <td><a href="/invoices/<?= e((string) $invoice['id']) ?>"><?= e($invoice['invoice_number']) ?></a></td>
                    <td><?= e($invoice['invoice_date']) ?></td>
                    <td>
                        <span class="<?= e(status_badge_class($invoice['status'])) ?>"><?= e(document_status_label($invoice['status'])) ?></span>
                        <span class="<?= e(status_badge_class($invoice['billing_status'])) ?>"><?= e($invoice['billing_status']) ?></span>
                    </td>
                    <td>Gs. <?= e(money($invoice['total_amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel__header">
        <h2>Actividad reciente</h2>
        <span class="muted">Últimos eventos operativos auditados.</span>
    </div>
    <?php if ($recentActivity === []): ?>
        <p class="empty">Todavía no hay eventos operativos registrados.</p>
    <?php else: ?>
        <div class="audit-list">
            <?php foreach ($recentActivity as $entry): ?>
                <article class="audit-card">
                    <div class="audit-card__head">
                        <div>
                            <strong><?= e(audit_action_label((string) $entry['action'])) ?></strong>
                            <span><?= e((string) ($entry['username'] ?: 'sistema')) ?> · <?= e(format_datetime((string) ($entry['created_at'] ?? ''))) ?></span>
                        </div>
                        <span class="badge"><?= e((string) ($entry['document_number'] ?: '#' . ($entry['document_id'] ?? $entry['entity_id'] ?? ''))) ?></span>
                    </div>
                    <div class="audit-meta">
                        <span><strong>Documento:</strong> <?= e((string) ($entry['document_type'] ?? $entry['entity_type'])) ?></span>
                        <?php if (!empty($entry['previous_state']) || !empty($entry['new_state'])): ?>
                            <span>
                                <strong>Estado:</strong>
                                <?= e(!empty($entry['previous_state']) ? document_status_label((string) $entry['previous_state']) : '-') ?>
                                →
                                <?= e(!empty($entry['new_state']) ? document_status_label((string) $entry['new_state']) : '-') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
