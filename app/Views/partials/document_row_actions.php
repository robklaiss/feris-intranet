<?php
$nextStep = $create_url ?? null;
if ($nextStep === null) {
    $nextStep = document_next_step((string) $type, (int) $document['id']);
}
?>
<div class="table-actions">
    <a href="<?= e((string) $show_url) ?>">Ver</a>

    <?php if (!empty($edit_url) && can('documents.edit') && !empty($meta['can_edit'])): ?>
        <a href="<?= e((string) $edit_url) ?>">Editar</a>
    <?php endif; ?>

    <?php if (is_array($nextStep) && can('documents.create') && (($meta['document']['status'] ?? '') === 'confirmed')): ?>
        <a href="<?= e((string) $nextStep['url']) ?>"><?= e((string) $nextStep['label']) ?></a>
    <?php endif; ?>

    <?php if (can('documents.transition') && !empty($meta['can_confirm'])): ?>
        <form method="post" action="<?= e(document_transition_path((string) $type, (int) $document['id'], 'confirm')) ?>">
            <?= csrf_field() ?>
            <button type="submit">Confirmar</button>
        </form>
    <?php endif; ?>

    <?php if (can('documents.transition') && !empty($meta['can_close'])): ?>
        <form method="post" action="<?= e(document_transition_path((string) $type, (int) $document['id'], 'close')) ?>">
            <?= csrf_field() ?>
            <button type="submit">Cerrar</button>
        </form>
    <?php endif; ?>

    <?php if (can('documents.transition') && !empty($meta['can_reopen'])): ?>
        <form method="post" action="<?= e(document_transition_path((string) $type, (int) $document['id'], 'reopen')) ?>">
            <?= csrf_field() ?>
            <button type="submit">Reabrir</button>
        </form>
    <?php endif; ?>

    <?php if (can('documents.transition') && !empty($meta['can_cancel'])): ?>
        <form method="post" action="<?= e(document_transition_path((string) $type, (int) $document['id'], 'cancel')) ?>">
            <?= csrf_field() ?>
            <button type="submit">Anular</button>
        </form>
    <?php endif; ?>

    <?php if (!empty($print_url) && can('documents.print')): ?>
        <a href="<?= e((string) $print_url) ?>">Imprimir</a>
    <?php endif; ?>

    <?php if (!empty($export_url) && can('documents.export')): ?>
        <a href="<?= e((string) $export_url) ?>">Exportar</a>
    <?php endif; ?>
</div>
