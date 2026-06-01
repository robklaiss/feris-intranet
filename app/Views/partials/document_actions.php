<?php
$nextStep = null;
if (!empty($create_url) && !empty($create_label)) {
    $nextStep = ['url' => (string) $create_url, 'label' => (string) $create_label];
} else {
    $nextStep = document_next_step((string) $type, (int) $document['id']);
}
?>
<div class="actions-row">
    <?php if (is_array($nextStep) && can('documents.create') && (($meta['document']['status'] ?? '') === 'confirmed')): ?>
        <a href="<?= e((string) $nextStep['url']) ?>" class="button"><?= e((string) $nextStep['label']) ?></a>
    <?php endif; ?>

    <?php if (!empty($edit_url) && can('documents.edit') && !empty($meta['can_edit'])): ?>
        <a href="<?= e((string) $edit_url) ?>" class="button button--secondary">Editar</a>
    <?php endif; ?>

    <?php if (!empty($send_url) && can('documents.transition') && (($meta['document']['status'] ?? '') === 'confirmed')): ?>
        <form method="post" action="<?= e((string) $send_url) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button">Enviar placeholder</button>
        </form>
    <?php endif; ?>

    <?php if (can('documents.transition') && !empty($meta['can_confirm'])): ?>
        <form method="post" action="<?= e(document_transition_path((string) $type, (int) $document['id'], 'confirm')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button">Confirmar</button>
        </form>
    <?php endif; ?>

    <?php if (can('documents.transition') && !empty($meta['can_close'])): ?>
        <form method="post" action="<?= e(document_transition_path((string) $type, (int) $document['id'], 'close')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button button--secondary">Cerrar</button>
        </form>
    <?php endif; ?>

    <?php if (can('documents.transition') && !empty($meta['can_reopen'])): ?>
        <form method="post" action="<?= e(document_transition_path((string) $type, (int) $document['id'], 'reopen')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button button--secondary">Reabrir</button>
        </form>
    <?php endif; ?>

    <?php if (can('documents.transition') && !empty($meta['can_cancel'])): ?>
        <form method="post" action="<?= e(document_transition_path((string) $type, (int) $document['id'], 'cancel')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button button--danger">Anular</button>
        </form>
    <?php endif; ?>

    <?php if (!empty($print_url) && can('documents.print')): ?>
        <a href="<?= e((string) $print_url) ?>" class="button button--secondary">Imprimir</a>
    <?php endif; ?>

    <?php if (!empty($export_url) && can('documents.export')): ?>
        <a href="<?= e((string) $export_url) ?>" class="button button--secondary">Exportar CSV</a>
    <?php endif; ?>

    <?php if (!empty($delete_url) && can('documents.edit') && !empty($meta['can_edit']) && empty($meta['locks']['locked'])): ?>
        <form method="post" action="<?= e((string) $delete_url) ?>" data-confirm="Eliminar documento">
            <?= csrf_field() ?>
            <button type="submit" class="button button--danger">Eliminar</button>
        </form>
    <?php endif; ?>
</div>
