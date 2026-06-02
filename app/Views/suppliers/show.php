<section class="page-head">
    <div>
        <p class="eyebrow">Proveedor</p>
        <h1><?= e($supplier['name']) ?></h1>
        <span class="<?= e(status_badge_class($supplier['status'])) ?>"><?= e(supplier_status_label($supplier['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/suppliers" class="button button--secondary">Volver</a>
        <?php if (can('documents.edit')): ?>
            <a href="/suppliers/<?= e((string) $supplier['id']) ?>/edit" class="button">Editar</a>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos comerciales</h2>
        <dl class="detail-list">
            <div><dt>RUC</dt><dd><?= e($supplier['ruc'] ?? '-') ?></dd></div>
            <div><dt>Contacto</dt><dd><?= e($supplier['contact_name'] ?? '-') ?></dd></div>
            <div><dt>Teléfono</dt><dd><?= e($supplier['phone'] ?? '-') ?></dd></div>
            <div><dt>Email</dt><dd><?= e($supplier['email'] ?? '-') ?></dd></div>
            <div><dt>Dirección</dt><dd><?= e($supplier['address'] ?? '-') ?></dd></div>
            <div><dt>Pago</dt><dd><?= e($supplier['payment_terms'] ?? '-') ?></dd></div>
            <div><dt>Entrega</dt><dd><?= e($supplier['delivery_terms'] ?? '-') ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($supplier['notes'] ?? '-')) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('suppliers', (int) $supplier['id']),
        ]) ?>
    </article>
</section>
