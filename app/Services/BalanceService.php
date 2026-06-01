<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;

final class BalanceService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function contractItemBalances(int $contractId): array
    {
        return $this->hydrateRemainingValues($this->fetchAll(
            'SELECT
                contract_items.*,
                COALESCE(SUM(purchase_order_items.quantity), 0) AS consumed_quantity
             FROM contract_items
             LEFT JOIN purchase_order_items ON purchase_order_items.contract_item_id = contract_items.id
             WHERE contract_items.contract_id = :contract_id
             GROUP BY contract_items.id
             ORDER BY contract_items.id',
            ['contract_id' => $contractId]
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function purchaseOrderItemBalances(int $purchaseOrderId): array
    {
        return $this->purchaseOrderItemBalancesForOrders([$purchaseOrderId]);
    }

    /**
     * @param array<int, int> $purchaseOrderIds
     * @return array<int, array<string, mixed>>
     */
    public function purchaseOrderItemBalancesForOrders(array $purchaseOrderIds): array
    {
        if ($purchaseOrderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($purchaseOrderIds), '?'));

        return $this->hydrateRemainingValues($this->fetchAll(
            "SELECT
                purchase_order_items.*,
                purchase_order_items.purchase_order_id,
                COALESCE(SUM(delivery_note_items.quantity), 0) AS consumed_quantity
             FROM purchase_order_items
             LEFT JOIN delivery_note_items ON delivery_note_items.purchase_order_item_id = purchase_order_items.id
             WHERE purchase_order_items.purchase_order_id IN ($placeholders)
             GROUP BY purchase_order_items.id
             ORDER BY purchase_order_items.purchase_order_id, purchase_order_items.id",
            $purchaseOrderIds
        ));
    }

    /**
     * @param array<int, int> $noteIds
     * @return array<int, array<string, mixed>>
     */
    public function deliveryNoteItemBalancesForNotes(array $noteIds): array
    {
        if ($noteIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($noteIds), '?'));
        return $this->hydrateRemainingValues($this->fetchAll(
            "SELECT
                delivery_note_items.*,
                delivery_note_items.delivery_note_id,
                COALESCE(SUM(remission_items.quantity), 0) AS consumed_quantity
             FROM delivery_note_items
             LEFT JOIN remission_items ON remission_items.delivery_note_item_id = delivery_note_items.id
             WHERE delivery_note_items.delivery_note_id IN ($placeholders)
             GROUP BY delivery_note_items.id
             ORDER BY delivery_note_items.delivery_note_id, delivery_note_items.id",
            $noteIds
        ));
    }

    /**
     * @param array<int, int> $remissionIds
     * @return array<int, array<string, mixed>>
     */
    public function remissionItemBalances(array $remissionIds): array
    {
        if ($remissionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($remissionIds), '?'));
        return $this->hydrateRemainingValues($this->fetchAll(
            "SELECT
                remission_items.*,
                COALESCE(SUM(invoice_items.quantity), 0) AS consumed_quantity
             FROM remission_items
             LEFT JOIN invoice_items ON invoice_items.remission_item_id = remission_items.id
             WHERE remission_items.remission_id IN ($placeholders)
             GROUP BY remission_items.id
             ORDER BY remission_items.remission_id, remission_items.id",
            $remissionIds
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contractItemBalance(int $contractItemId): ?array
    {
        foreach ($this->hydrateRemainingValues($this->fetchAll(
            'SELECT
                contract_items.*,
                COALESCE(SUM(purchase_order_items.quantity), 0) AS consumed_quantity
             FROM contract_items
             LEFT JOIN purchase_order_items ON purchase_order_items.contract_item_id = contract_items.id
             WHERE contract_items.id = :id
             GROUP BY contract_items.id',
            ['id' => $contractItemId]
        )) as $item) {
            return $item;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function purchaseOrderItemBalance(int $purchaseOrderItemId): ?array
    {
        foreach ($this->hydrateRemainingValues($this->fetchAll(
            'SELECT
                purchase_order_items.*,
                COALESCE(SUM(delivery_note_items.quantity), 0) AS consumed_quantity
             FROM purchase_order_items
             LEFT JOIN delivery_note_items ON delivery_note_items.purchase_order_item_id = purchase_order_items.id
             WHERE purchase_order_items.id = :id
             GROUP BY purchase_order_items.id',
            ['id' => $purchaseOrderItemId]
        )) as $item) {
            return $item;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $sql, array $params): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll() ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function hydrateRemainingValues(array $items): array
    {
        foreach ($items as &$item) {
            $item['remaining_quantity'] = (float) $item['quantity'] - (float) $item['consumed_quantity'];
            $item['remaining_total'] = $item['remaining_quantity'] * (float) $item['unit_price'];
        }

        return $items;
    }
}
