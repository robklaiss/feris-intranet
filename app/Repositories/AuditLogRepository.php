<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Auth;

final class AuditLogRepository extends BaseRepository
{
    /**
     * @param array<string, mixed>|null $changes
     * @param array<string, mixed> $context
     */
    public function log(string $entityType, int $entityId, string $action, ?array $changes = null, array $context = []): void
    {
        $user = Auth::user();

        $this->execute(
            'INSERT INTO audit_log (
                entity_type,
                entity_id,
                document_id,
                action,
                changes,
                user_id,
                username,
                document_type,
                document_number,
                previous_state,
                new_state,
                payload_summary
            ) VALUES (
                :entity_type,
                :entity_id,
                :document_id,
                :action,
                :changes,
                :user_id,
                :username,
                :document_type,
                :document_number,
                :previous_state,
                :new_state,
                :payload_summary
            )',
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'document_id' => $context['document_id'] ?? $entityId,
                'action' => $action,
                'changes' => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
                'user_id' => $context['user_id'] ?? $user['id'] ?? null,
                'username' => $context['username'] ?? $user['username'] ?? ($user['name'] ?? null),
                'document_type' => $context['document_type'] ?? $entityType,
                'document_number' => $context['document_number'] ?? null,
                'previous_state' => $context['previous_state'] ?? null,
                'new_state' => $context['new_state'] ?? null,
                'payload_summary' => isset($context['payload_summary']) ? json_encode($context['payload_summary'], JSON_UNESCAPED_UNICODE) : null,
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentForDocument(string $documentType, int $documentId, int $limit = 20): array
    {
        $statement = $this->db()->prepare(
            'SELECT *
             FROM audit_log
             WHERE document_type = :document_type
               AND COALESCE(document_id, entity_id) = :document_id
             ORDER BY created_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':document_type', $documentType);
        $statement->bindValue(':document_id', $documentId, \PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map([$this, 'hydrateAuditEntry'], $statement->fetchAll() ?: []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentDocumentActivity(int $limit = 12): array
    {
        $statement = $this->db()->prepare(
            'SELECT *
             FROM audit_log
             WHERE document_type IS NOT NULL
               AND document_type != ""
               AND action != "bootstrap"
             ORDER BY created_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map([$this, 'hydrateAuditEntry'], $statement->fetchAll() ?: []);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function hydrateAuditEntry(array $entry): array
    {
        $entry['changes'] = $this->decodeJsonField($entry['changes'] ?? null);
        $entry['payload_summary'] = $this->decodeJsonField($entry['payload_summary'] ?? null);
        $entry['document_id'] = $entry['document_id'] ?? $entry['entity_id'] ?? null;

        return $entry;
    }

    private function decodeJsonField(mixed $value): mixed
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
