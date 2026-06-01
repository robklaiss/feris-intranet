<section class="page-head">
    <div>
        <p class="eyebrow">Licitación</p>
        <h1><?= e($licitacion['call_number']) ?></h1>
        <p class="muted"><?= e($licitacion['title']) ?> · <?= e($licitacion['institution']) ?></p>
        <span class="<?= e(licitacion_status_badge_class($licitacion['status'])) ?>"><?= e(licitacion_status_label($licitacion['status'])) ?></span>
    </div>
    <div class="actions-row">
        <?php if (can('documents.edit')): ?>
            <a href="/licitaciones/<?= e((string) $licitacion['id']) ?>/edit" class="button button--secondary">Editar</a>
            <form method="post" action="/licitaciones/<?= e((string) $licitacion['id']) ?>/delete" data-confirm="Eliminar licitación">
                <?= csrf_field() ?>
                <button type="submit" class="button button--danger">Eliminar</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Resumen</h2>
        <dl class="detail-list">
            <div><dt>Título</dt><dd><?= e($licitacion['title']) ?></dd></div>
            <div><dt>Entidad convocante</dt><dd><?= e($licitacion['institution']) ?></dd></div>
            <div><dt>Publicación</dt><dd><?= e($licitacion['publish_date'] ?: '-') ?></dd></div>
            <div><dt>Apertura</dt><dd><?= e($licitacion['opening_date'] ?: '-') ?></dd></div>
            <div><dt>Estado</dt><dd><?= e(licitacion_status_label($licitacion['status'])) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($licitacion['notes'])) ?></dd></div>
        </dl>
    </article>

    <article class="panel">
        <div class="panel__header">
            <h2>Avance documental</h2>
            <span class="notice"><?= e((string) count(array_filter($licitacion['checklists'], static fn (array $item): bool => !empty($item['is_attached'])))) ?> / <?= e((string) count($licitacion['checklists'])) ?> adjuntos</span>
        </div>
        <div class="checklist-stack">
            <?php foreach ($licitacion['checklists'] as $item): ?>
                <label class="selector-card selector-card--check">
                    <input type="checkbox" <?= !empty($item['is_attached']) ? 'checked' : '' ?> disabled>
                    <div>
                        <strong><?= e($item['label']) ?></strong>
                        <span><?= !empty($item['is_attached']) ? 'Marcado como adjuntado en carpeta' : 'Pendiente de adjuntar' ?></span>
                    </div>
                </label>
            <?php endforeach; ?>
            <?php if ($licitacion['checklists'] === []): ?>
                <div class="empty">No hay checklist documental definido.</div>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="grid-two">
    <article class="panel">
        <div class="panel__header">
            <h2>Llamado o pliego</h2>
            <span class="muted"><?= e((string) count($mainFiles)) ?> archivo(s)</span>
        </div>
        <div class="file-list">
            <?php foreach ($mainFiles as $file): ?>
                <div class="file-item">
                    <div>
                        <strong><?= e($file['original_name']) ?></strong>
                        <span><?= e(file_size_label($file['size_bytes'] ?? 0)) ?></span>
                    </div>
                    <a href="/licitaciones/<?= e((string) $licitacion['id']) ?>/documentos/<?= e((string) $file['id']) ?>/download">Descargar</a>
                </div>
            <?php endforeach; ?>
            <?php if ($mainFiles === []): ?>
                <div class="empty">No hay pliego principal adjunto.</div>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel">
        <div class="panel__header">
            <h2>Adjuntos complementarios</h2>
            <span class="muted"><?= e((string) count($supportFiles)) ?> archivo(s)</span>
        </div>
        <div class="file-list">
            <?php foreach ($supportFiles as $file): ?>
                <div class="file-item">
                    <div>
                        <strong><?= e($file['original_name']) ?></strong>
                        <span><?= e(file_size_label($file['size_bytes'] ?? 0)) ?></span>
                    </div>
                    <a href="/licitaciones/<?= e((string) $licitacion['id']) ?>/documentos/<?= e((string) $file['id']) ?>/download">Descargar</a>
                </div>
            <?php endforeach; ?>
            <?php if ($supportFiles === []): ?>
                <div class="empty">No hay adjuntos complementarios cargados.</div>
            <?php endif; ?>
        </div>
    </article>
</section>
