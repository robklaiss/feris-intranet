<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;

final class DocumentContextService
{
    public function __construct(
        private readonly BalanceService $balances = new BalanceService()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseOrderContext(int $contractId): array
    {
        $contract = $this->fetchContract($contractId);

        return [
            'document' => $this->contractDocumentData($contract),
            'items' => $this->normalizeItems($this->balances->contractItemBalances($contractId), 'contract_item_id'),
        ];
    }

    /**
     * @param array<int, int> $purchaseOrderIds
     * @return array<string, mixed>
     */
    public function deliveryNoteContext(?int $contractId, array $purchaseOrderIds = []): array
    {
        $orders = $purchaseOrderIds !== [] ? $this->fetchOrdersByIds($purchaseOrderIds) : [];

        if ($orders === [] && $contractId) {
            $orders = $this->openOrdersByContract($contractId);
            $purchaseOrderIds = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        }

        $primaryOrder = $orders[0] ?? null;
        $resolvedContractId = $contractId ?: (int) ($primaryOrder['contract_id'] ?? 0);
        $contract = $resolvedContractId > 0 ? $this->fetchContract($resolvedContractId) : null;
        $client = $primaryOrder ?: $contract;

        return [
            'document' => [
                'client_id' => $client['client_id'] ?? $client['id'] ?? null,
                'client_name' => $primaryOrder['client_name'] ?? $contract['client_name'] ?? '',
                'contract_id' => $resolvedContractId ?: null,
                'contract_number' => $contract['contract_number'] ?? '',
                'identifier_number' => $primaryOrder['identifier_number'] ?? $contract['reference_number'] ?? '',
                'contract_type' => $primaryOrder['contract_type'] ?? $contract['contract_type'] ?? '',
                'tax_id' => $primaryOrder['tax_id'] ?? $contract['tax_id'] ?? '',
                'purchase_order_ids' => $purchaseOrderIds,
                'orders' => $orders,
            ],
            'items' => $this->normalizeItems(
                $this->annotateWithOrderMetadata($this->balances->purchaseOrderItemBalancesForOrders($purchaseOrderIds), $orders),
                'purchase_order_item_id'
            ),
        ];
    }

    /**
     * @param array<int, int> $deliveryNoteIds
     * @return array<string, mixed>
     */
    public function remissionContext(array $deliveryNoteIds): array
    {
        $notes = $this->fetchDeliveryNotesByIds($deliveryNoteIds);
        $primary = $notes[0] ?? null;

        return [
            'document' => [
                'client_id' => $primary['client_id'] ?? null,
                'client_name' => $primary['client_name'] ?? '',
                'contract_id' => $primary['contract_id'] ?? null,
                'contract_number' => $primary['contract_number'] ?? '',
                'reference_number' => $primary['identifier_number'] ?? '',
                'contract_type' => $primary['contract_type'] ?? '',
                'tax_id' => $primary['tax_id'] ?? '',
                'origin_address' => '',
                'destination_address' => $primary['delivery_address'] ?? '',
                'source_notes' => $notes,
            ],
            'items' => $this->normalizeItems(
                $this->annotateDeliveryItems($this->balances->deliveryNoteItemBalancesForNotes($deliveryNoteIds), $notes),
                'delivery_note_item_id'
            ),
        ];
    }

    /**
     * @param array<int, int> $remissionIds
     * @return array<string, mixed>
     */
    public function invoiceContext(array $remissionIds): array
    {
        $remissions = $this->fetchRemissionsByIds($remissionIds);
        $primary = $remissions[0] ?? null;

        return [
            'document' => [
                'client_id' => $primary['effective_client_id'] ?? $primary['client_id'] ?? null,
                'client_name' => $primary['client_name'] ?? '',
                'contract_id' => $primary['effective_contract_id'] ?? $primary['contract_id'] ?? null,
                'contract_number' => $primary['contract_number'] ?? '',
                'reference_number' => $primary['effective_reference_number'] ?? $primary['reference_number'] ?? '',
                'contract_type' => $primary['effective_contract_type'] ?? $primary['contract_type'] ?? '',
                'tax_id' => $primary['effective_tax_id'] ?? $primary['tax_id'] ?? '',
                'billing_address' => $primary['client_addresses'] ?? '',
                'source_remissions' => $remissions,
            ],
            'items' => $this->normalizeItems(
                $this->annotateRemissionItems($this->balances->remissionItemBalances($remissionIds), $remissions),
                'remission_item_id'
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openOrdersByContract(int $contractId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                purchase_orders.id,
                purchase_orders.order_number,
                purchase_orders.contract_id,
                purchase_orders.client_id,
                COALESCE(purchase_orders.identifier_number, contracts.reference_number) AS identifier_number,
                COALESCE(purchase_orders.contract_type, contracts.contract_type) AS contract_type,
                COALESCE(purchase_orders.tax_id, contracts.tax_id, clients.tax_id) AS tax_id,
                clients.name AS client_name
             FROM purchase_orders
             LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
             LEFT JOIN clients ON clients.id = purchase_orders.client_id
             WHERE purchase_orders.contract_id = :contract_id
               AND purchase_orders.status = 'confirmed'
             ORDER BY purchase_orders.order_date DESC, purchase_orders.id DESC"
        );
        $statement->execute(['contract_id' => $contractId]);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrdersByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare(
            "SELECT
                purchase_orders.id,
                purchase_orders.order_number,
                purchase_orders.contract_id,
                purchase_orders.client_id,
                COALESCE(purchase_orders.identifier_number, contracts.reference_number) AS identifier_number,
                COALESCE(purchase_orders.contract_type, contracts.contract_type) AS contract_type,
                COALESCE(purchase_orders.tax_id, contracts.tax_id, clients.tax_id) AS tax_id,
                clients.name AS client_name
             FROM purchase_orders
             LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
             LEFT JOIN clients ON clients.id = purchase_orders.client_id
             WHERE purchase_orders.id IN ($placeholders)
               AND purchase_orders.status = 'confirmed'
             ORDER BY purchase_orders.order_date DESC, purchase_orders.id DESC"
        );
        $statement->execute($ids);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchContract(int $contractId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT contracts.*, clients.name AS client_name
             FROM contracts
             LEFT JOIN clients ON clients.id = contracts.client_id
             WHERE contracts.id = :id'
        );
        $statement->execute(['id' => $contractId]);

        $contract = $statement->fetch();
        return $contract ?: null;
    }

    /**
     * @param array<string, mixed>|null $contract
     * @return array<string, mixed>
     */
    private function contractDocumentData(?array $contract): array
    {
        return [
            'client_id' => $contract['client_id'] ?? null,
            'client_name' => $contract['client_name'] ?? '',
            'contract_id' => $contract['id'] ?? null,
            'contract_number' => $contract['contract_number'] ?? '',
            'identifier_number' => $contract['reference_number'] ?? '',
            'contract_type' => $contract['contract_type'] ?? '',
            'tax_id' => $contract['tax_id'] ?? '',
            'is_provisional' => (int) ($contract['is_provisional'] ?? 0),
            'live_sync_fields' => ProvisionalContractSyncService::defaultLiveFields(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items, string $sourceKey): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if ((float) ($item['remaining_quantity'] ?? 0) <= 0.0001) {
                continue;
            }

            $item['source_key'] = $sourceKey;
            $item['suggested_quantity'] = (float) $item['remaining_quantity'];
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    private function annotateWithOrderMetadata(array $items, array $orders): array
    {
        $index = [];
        foreach ($orders as $order) {
            $index[(int) $order['id']] = $order;
        }

        foreach ($items as &$item) {
            $order = $index[(int) $item['purchase_order_id']] ?? null;
            $item['order_number'] = $order['order_number'] ?? '';
        }

        return $items;
    }

    /**
     * @param array<int, int> $deliveryNoteIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchDeliveryNotesByIds(array $deliveryNoteIds): array
    {
        if ($deliveryNoteIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($deliveryNoteIds), '?'));
        $statement = Database::connection()->prepare(
            "SELECT
                delivery_notes.*,
                clients.name AS client_name,
                clients.addresses AS client_addresses,
                contracts.contract_number
             FROM delivery_notes
             LEFT JOIN clients ON clients.id = delivery_notes.client_id
             LEFT JOIN contracts ON contracts.id = delivery_notes.contract_id
             WHERE delivery_notes.id IN ($placeholders)
               AND delivery_notes.status = 'confirmed'
             ORDER BY delivery_notes.note_date DESC, delivery_notes.id DESC"
        );
        $statement->execute($deliveryNoteIds);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $notes
     * @return array<int, array<string, mixed>>
     */
    private function annotateDeliveryItems(array $items, array $notes): array
    {
        $index = [];
        foreach ($notes as $note) {
            $index[(int) $note['id']] = $note;
        }

        foreach ($items as &$item) {
            $note = $index[(int) $item['delivery_note_id']] ?? null;
            $item['note_number'] = $note['note_number'] ?? '';
        }

        return $items;
    }

    /**
     * @param array<int, int> $remissionIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchRemissionsByIds(array $remissionIds): array
    {
        if ($remissionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($remissionIds), '?'));
        $statement = Database::connection()->prepare(
            "SELECT
                remissions.*,
                clients.name AS client_name,
                clients.addresses AS client_addresses,
                contracts.contract_number,
                COALESCE(remissions.client_id, delivery_notes.client_id) AS effective_client_id,
                COALESCE(remissions.contract_id, delivery_notes.contract_id) AS effective_contract_id,
                COALESCE(remissions.reference_number, delivery_notes.identifier_number) AS effective_reference_number,
                COALESCE(remissions.contract_type, delivery_notes.contract_type) AS effective_contract_type,
                COALESCE(remissions.tax_id, delivery_notes.tax_id) AS effective_tax_id
             FROM remissions
             LEFT JOIN remission_source_notes ON remission_source_notes.remission_id = remissions.id
             LEFT JOIN delivery_notes ON delivery_notes.id = remission_source_notes.delivery_note_id
             LEFT JOIN clients ON clients.id = COALESCE(remissions.client_id, delivery_notes.client_id)
             LEFT JOIN contracts ON contracts.id = COALESCE(remissions.contract_id, delivery_notes.contract_id)
             WHERE remissions.id IN ($placeholders)
               AND remissions.status = 'confirmed'
             GROUP BY remissions.id
             ORDER BY remissions.remission_date DESC, remissions.id DESC"
        );
        $statement->execute($remissionIds);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $remissions
     * @return array<int, array<string, mixed>>
     */
    private function annotateRemissionItems(array $items, array $remissions): array
    {
        $index = [];
        foreach ($remissions as $remission) {
            $index[(int) $remission['id']] = $remission;
        }

        foreach ($items as &$item) {
            $remission = $index[(int) $item['remission_id']] ?? null;
            $item['remission_number'] = $remission['remission_number'] ?? '';
        }

        return $items;
    }
}
