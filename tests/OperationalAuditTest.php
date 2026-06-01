<?php

declare(strict_types=1);

use App\Services\DocumentWorkflowService;
use App\Support\Auth;
use App\Support\Database;

$db = Database::connection();
$db->beginTransaction();

try {
    Auth::logout();
    if (!Auth::attempt('admin', 'admin123')) {
        throw new RuntimeException('No se pudo autenticar el usuario admin demo.');
    }

    (new DocumentWorkflowService())->transition('invoices', 1, 'confirm');

    $entry = $db->query(
        "SELECT *
         FROM audit_log
         WHERE entity_type = 'invoices'
           AND entity_id = 1
           AND action = 'confirm'
         ORDER BY id DESC
         LIMIT 1"
    )->fetch();

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $assert(is_array($entry), 'Debe registrarse una auditoría para la confirmación.');
    $assert(($entry['username'] ?? null) === 'admin', 'La auditoría debe guardar el usuario que ejecutó la acción.');
    $assert(($entry['document_type'] ?? null) === 'invoices', 'La auditoría debe guardar el documento afectado.');
    $assert((int) ($entry['document_id'] ?? 0) === 1, 'La auditoría debe guardar el id del documento afectado.');
    $assert(($entry['previous_state'] ?? null) === 'draft', 'La auditoría debe guardar el estado anterior.');
    $assert(($entry['new_state'] ?? null) === 'confirmed', 'La auditoría debe guardar el estado nuevo.');
    $assert(!empty($entry['payload_summary']), 'La auditoría debe guardar un payload resumido.');

    $payloadSummary = json_decode((string) $entry['payload_summary'], true);
    $assert(is_array($payloadSummary), 'El payload resumido debe estar serializado como JSON.');
    $assert(($payloadSummary['action'] ?? null) === 'confirm', 'El payload resumido debe incluir la acción ejecutada.');
} finally {
    Auth::logout();
    $db->rollBack();
}

return true;
