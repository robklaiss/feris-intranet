<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;

final class ProvisionalContractSyncService
{
    /**
     * @return array<int, string>
     */
    public static function defaultLiveFields(): array
    {
        return ['client_id', 'identifier_number', 'contract_type', 'tax_id'];
    }

    /**
     * @param array<string, mixed>|null $contract
     * @return array<string, mixed>
     */
    public function enrichOrderData(array $data, ?array $contract): array
    {
        if (!$contract) {
            $data['linked_contract_snapshot'] = null;
            $data['live_sync_fields'] = json_encode([], JSON_UNESCAPED_UNICODE);
            return $data;
        }

        $data['is_provisional'] = (int) ($data['is_provisional'] ?? $contract['is_provisional'] ?? 0);
        $data['linked_contract_snapshot'] = json_encode($this->snapshotFromContract($contract), JSON_UNESCAPED_UNICODE);
        $data['live_sync_fields'] = json_encode(
            (int) ($contract['is_provisional'] ?? 0) === 1 ? self::defaultLiveFields() : [],
            JSON_UNESCAPED_UNICODE
        );

        return $data;
    }

    public function syncOrdersForContract(int $contractId, ?array $previousContract, array $currentContract): void
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM purchase_orders WHERE contract_id = :contract_id AND linked_contract_snapshot IS NOT NULL'
        );
        $statement->execute(['contract_id' => $contractId]);
        $orders = $statement->fetchAll() ?: [];

        if ($orders === []) {
            return;
        }

        $currentSnapshot = $this->snapshotFromContract($currentContract);
        $isStillProvisional = (int) ($currentContract['is_provisional'] ?? 0) === 1;

        foreach ($orders as $order) {
            $previousSnapshot = $this->decodeJsonObject((string) ($order['linked_contract_snapshot'] ?? ''));
            $liveFields = $this->decodeJsonList((string) ($order['live_sync_fields'] ?? ''));

            if ($liveFields === []) {
                continue;
            }

            $updates = ['id' => (int) $order['id']];
            $setParts = [];

            foreach ($liveFields as $field) {
                $oldValue = $previousSnapshot[$field] ?? ($previousContract[$this->contractColumnFor($field)] ?? null);
                $currentValue = $order[$field] ?? null;

                if ((string) $currentValue !== (string) $oldValue && $oldValue !== null) {
                    continue;
                }

                $setParts[] = $field . ' = :' . $field;
                $updates[$field] = $currentSnapshot[$field] ?? null;
            }

            $setParts[] = 'linked_contract_snapshot = :linked_contract_snapshot';
            $updates['linked_contract_snapshot'] = json_encode($currentSnapshot, JSON_UNESCAPED_UNICODE);

            if (!$isStillProvisional) {
                $setParts[] = 'live_sync_fields = :live_sync_fields';
                $setParts[] = 'is_provisional = 0';
                $updates['live_sync_fields'] = json_encode([], JSON_UNESCAPED_UNICODE);
            }

            $setParts[] = 'updated_at = CURRENT_TIMESTAMP';

            Database::connection()->prepare(
                'UPDATE purchase_orders SET ' . implode(', ', $setParts) . ' WHERE id = :id'
            )->execute($updates);
        }
    }

    /**
     * @param array<string, mixed> $contract
     * @return array<string, mixed>
     */
    private function snapshotFromContract(array $contract): array
    {
        return [
            'client_id' => $contract['client_id'] ?? null,
            'identifier_number' => $contract['reference_number'] ?? null,
            'contract_type' => $contract['contract_type'] ?? null,
            'tax_id' => $contract['tax_id'] ?? null,
        ];
    }

    private function contractColumnFor(string $orderField): string
    {
        return match ($orderField) {
            'identifier_number' => 'reference_number',
            default => $orderField,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, string>
     */
    private function decodeJsonList(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }
}
