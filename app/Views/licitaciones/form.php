<?php
$existingMainFiles = array_values(array_filter(
    $licitacion['files'] ?? [],
    static fn (array $file): bool => ($file['category'] ?? '') === 'pliego'
));
$existingSupportFiles = array_values(array_filter(
    $licitacion['files'] ?? [],
    static fn (array $file): bool => ($file['category'] ?? '') === 'adjunto'
));
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Licitaciones</p>
        <h1><?= e($title) ?></h1>
    </div>
    <a href="/licitaciones" class="button button--secondary">Volver</a>
</section>

<form method="post" action="<?= e($action) ?>" class="form-stack" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <section class="alert alert--info">
        Cargá el llamado o pliego, definí la documentación requerida y luego usá el checklist para marcar qué ya quedó adjunto a la carpeta de la licitación.
    </section>

    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Número de llamado</span>
                <input type="text" name="call_number" value="<?= e($licitacion['call_number']) ?>" required>
            </label>
            <label>
                <span>Estado</span>
                <select name="status">
                    <?php foreach (licitacion_statuses() as $status => $label): ?>
                        <option value="<?= e($status) ?>" <?= ($licitacion['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                <span>Título de la licitación</span>
                <input type="text" name="title" value="<?= e($licitacion['title']) ?>" required>
            </label>
            <label>
                <span>Entidad convocante</span>
                <input type="text" name="institution" value="<?= e($licitacion['institution']) ?>" required>
            </label>
            <label>
                <span>Fecha de publicación</span>
                <input type="date" name="publish_date" value="<?= e($licitacion['publish_date']) ?>">
            </label>
            <label>
                <span>Fecha de apertura</span>
                <input type="date" name="opening_date" value="<?= e($licitacion['opening_date']) ?>">
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="4"><?= e($licitacion['notes']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Documentación base</h2>
            <span class="muted">Adjuntá el llamado o el pliego principal y, si querés, otros respaldos.</span>
        </div>
        <div class="form-grid">
            <label class="span-2">
                <span><?= $existingMainFiles === [] ? 'Llamado o pliego principal' : 'Reemplazar llamado o pliego principal' ?></span>
                <input type="file" name="pliego_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" <?= $existingMainFiles === [] ? 'required' : '' ?>>
            </label>
            <label class="span-2">
                <span>Adjuntos complementarios</span>
                <input type="file" name="support_documents[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" multiple>
            </label>
        </div>

        <?php if ($existingMainFiles !== [] || $existingSupportFiles !== []): ?>
            <div class="file-list">
                <?php foreach ($existingMainFiles as $file): ?>
                    <div class="file-item">
                        <div>
                            <strong>Pliego principal</strong>
                            <span><?= e($file['original_name']) ?> · <?= e(file_size_label($file['size_bytes'] ?? 0)) ?></span>
                        </div>
                        <span class="file-item__actions">
                            <a href="/licitaciones/<?= e((string) $licitacion['id']) ?>/documentos/<?= e((string) $file['id']) ?>/download">Descargar</a>
                            <span class="checkbox checkbox--inline">
                                <input type="checkbox" name="delete_file_ids[]" value="<?= e((string) $file['id']) ?>">
                                <span>Eliminar</span>
                            </span>
                        </span>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($existingSupportFiles as $file): ?>
                    <div class="file-item">
                        <div>
                            <strong>Adjunto</strong>
                            <span><?= e($file['original_name']) ?> · <?= e(file_size_label($file['size_bytes'] ?? 0)) ?></span>
                        </div>
                        <span class="file-item__actions">
                            <a href="/licitaciones/<?= e((string) $licitacion['id']) ?>/documentos/<?= e((string) $file['id']) ?>/download">Descargar</a>
                            <span class="checkbox checkbox--inline">
                                <input type="checkbox" name="delete_file_ids[]" value="<?= e((string) $file['id']) ?>">
                                <span>Eliminar</span>
                            </span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel__header">
            <h2>Checklist documental</h2>
            <button type="button" class="button button--secondary" data-add-item="#checklist-body">Agregar requisito</button>
        </div>
        <div class="table-wrap">
            <table class="table table--form">
                <thead>
                <tr>
                    <th>Documento o requisito</th>
                    <th>Adjuntado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="checklist-body">
                <?php foreach ($licitacion['checklists'] as $item): ?>
                    <tr data-item-row>
                        <td>
                            <input type="text" name="checklist_label[]" value="<?= e($item['label'] ?? '') ?>" placeholder="Ej. Constancia de RUC, patente municipal, oferta económica">
                        </td>
                        <td>
                            <label class="checkbox checkbox--inline">
                                <input type="hidden" name="checklist_attached[]" value="0">
                                <input type="checkbox" name="checklist_attached[]" value="1" <?= !empty($item['is_attached']) ? 'checked' : '' ?>>
                                <span>Ya adjuntado</span>
                            </label>
                        </td>
                        <td><button type="button" class="icon-button" data-remove-row>&times;</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button">Guardar licitación</button>
    </div>
</form>
