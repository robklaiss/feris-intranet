<section class="page-head">
    <div>
        <p class="eyebrow">Costurero</p>
        <h1><?= e($seamster['name']) ?></h1>
        <span class="<?= e(status_badge_class($seamster['status'])) ?>"><?= e(seamster_status_label($seamster['status'])) ?></span>
    </div>
    <div class="page-actions">
        <a href="/seamsters" class="button button--secondary">Volver</a>
        <?php if (can('documents.edit')): ?>
            <a href="/seamsters/<?= e((string) $seamster['id']) ?>/edit" class="button">Editar</a>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Datos</h2>
        <dl class="detail-list">
            <div><dt>Documento</dt><dd><?= e($seamster['document_number'] ?? '-') ?></dd></div>
            <div><dt>Teléfono</dt><dd><?= e($seamster['phone'] ?? '-') ?></dd></div>
            <div><dt>Email</dt><dd><?= e($seamster['email'] ?? '-') ?></dd></div>
            <div><dt>Dirección</dt><dd><?= e($seamster['address'] ?? '-') ?></dd></div>
            <div><dt>Creado</dt><dd><?= e(format_datetime($seamster['created_at'])) ?></dd></div>
            <div><dt>Actualizado</dt><dd><?= e(format_datetime($seamster['updated_at'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($seamster['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Timeline de auditoría</h2>
        <?= \App\Support\View::partial('partials/audit_trail', [
            'entries' => document_audit_entries('seamsters', (int) $seamster['id']),
        ]) ?>
    </article>
</section>
