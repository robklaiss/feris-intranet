<?php $entries = $entries ?? []; ?>
<section class="panel">
    <div class="panel__header">
        <h2>Auditoría operativa</h2>
        <span class="muted">Usuario, acción, transición y resumen del cambio.</span>
    </div>

    <?php if ($entries === []): ?>
        <p class="empty">No hay eventos auditados para este documento todavía.</p>
    <?php else: ?>
        <div class="audit-list">
            <?php foreach ($entries as $entry): ?>
                <?php $payload = is_array($entry['payload_summary'] ?? null) ? $entry['payload_summary'] : []; ?>
                <?php $previousState = (string) ($entry['previous_state'] ?? ''); ?>
                <?php $newState = (string) ($entry['new_state'] ?? ''); ?>
                <article class="audit-card">
                    <div class="audit-card__head">
                        <div>
                            <strong><?= e(audit_action_label((string) $entry['action'])) ?></strong>
                            <span>
                                <?= e((string) ($entry['username'] ?: 'sistema')) ?>
                                · <?= e(format_datetime((string) ($entry['created_at'] ?? ''))) ?>
                            </span>
                        </div>
                        <span class="badge"><?= e((string) ($entry['document_number'] ?: '#' . ($entry['document_id'] ?? $entry['entity_id'] ?? ''))) ?></span>
                    </div>

                    <div class="audit-meta">
                        <span><strong>Documento:</strong> <?= e((string) ($entry['document_type'] ?? $entry['entity_type'])) ?></span>
                        <span><strong>ID:</strong> <?= e((string) ($entry['document_id'] ?? $entry['entity_id'])) ?></span>
                        <?php if ($previousState !== '' || $newState !== ''): ?>
                            <span>
                                <strong>Estado:</strong>
                                <?= e($previousState !== '' ? document_status_label($previousState) : '-') ?>
                                →
                                <?= e($newState !== '' ? document_status_label($newState) : '-') ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($payload !== []): ?>
                        <div class="audit-payload">
                            <?php foreach ($payload as $key => $value): ?>
                                <span class="badge badge--draft">
                                    <?= e((string) $key) ?>:
                                    <?php if (is_array($value)): ?>
                                        <?= e(json_encode($value, JSON_UNESCAPED_UNICODE)) ?>
                                    <?php else: ?>
                                        <?= e((string) $value) ?>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
