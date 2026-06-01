<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Support\Auth;

final class OperationalAuditService
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function logDocumentAction(
        string $documentType,
        int $documentId,
        string $documentNumber,
        string $action,
        ?string $previousState,
        ?string $newState,
        ?array $payload = null
    ): void {
        $user = Auth::user();

        (new AuditLogRepository())->log(
            $documentType,
            $documentId,
            $action,
            $payload,
            [
                'user_id' => $user['id'] ?? null,
                'username' => $user['username'] ?? ($user['name'] ?? null),
                'document_type' => $documentType,
                'document_id' => $documentId,
                'document_number' => $documentNumber,
                'previous_state' => $previousState,
                'new_state' => $newState,
                'payload_summary' => $this->summarize($payload),
            ]
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>|null
     */
    private function summarize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $summary = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $summary[$key] = [
                    'count' => count($value),
                    'preview' => array_slice($value, 0, 2),
                ];
                continue;
            }

            if (is_string($value)) {
                $summary[$key] = mb_strlen($value) > 140
                    ? mb_substr($value, 0, 137) . '...'
                    : $value;
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $summary[$key] = $value;
            }
        }

        return $summary;
    }
}
