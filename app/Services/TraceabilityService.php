<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;

final class TraceabilityService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function contractTrace(int $contractId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM contract_items WHERE contract_id = :contract_id ORDER BY id'
        );
        $statement->execute(['contract_id' => $contractId]);
        $items = $statement->fetchAll() ?: [];

        foreach ($items as &$item) {
            $item['purchase_order_items'] = $this->purchaseOrderChildren((int) $item['id']);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function purchaseOrderTrace(int $purchaseOrderId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM purchase_order_items WHERE purchase_order_id = :purchase_order_id ORDER BY id'
        );
        $statement->execute(['purchase_order_id' => $purchaseOrderId]);
        $items = $statement->fetchAll() ?: [];

        foreach ($items as &$item) {
            $item['delivery_note_items'] = $this->deliveryNoteChildren((int) $item['id']);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deliveryNoteTrace(int $deliveryNoteId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM delivery_note_items WHERE delivery_note_id = :delivery_note_id ORDER BY id'
        );
        $statement->execute(['delivery_note_id' => $deliveryNoteId]);
        $items = $statement->fetchAll() ?: [];

        foreach ($items as &$item) {
            $item['remission_items'] = $this->remissionChildren((int) $item['id']);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function remissionTrace(int $remissionId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                remission_items.*,
                finished_goods_inventory.internal_code AS finished_goods_internal_code,
                finished_goods_inventory.package_code AS finished_goods_package_code,
                finished_goods_inventory.location AS finished_goods_location,
                packaging_orders.packaging_number,
                quality_control_checks.id AS quality_control_check_id,
                quality_control_checks.qc_number,
                production_orders.production_number,
                contracts.contract_number
             FROM remission_items
             LEFT JOIN finished_goods_inventory ON finished_goods_inventory.id = remission_items.finished_goods_inventory_id
             LEFT JOIN packaging_orders ON packaging_orders.id = remission_items.packaging_order_id
             LEFT JOIN quality_control_checks ON quality_control_checks.id = packaging_orders.quality_control_check_id
             LEFT JOIN production_orders ON production_orders.id = remission_items.production_order_id
             LEFT JOIN contracts ON contracts.id = finished_goods_inventory.contract_id
             WHERE remission_items.remission_id = :remission_id
             ORDER BY remission_items.id'
        );
        $statement->execute(['remission_id' => $remissionId]);
        $items = $statement->fetchAll() ?: [];

        foreach ($items as &$item) {
            $item['invoice_items'] = $this->invoiceChildren((int) $item['id']);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function invoiceTrace(int $invoiceId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT invoice_items.*, remission_items.remission_id, remissions.remission_number
             FROM invoice_items
             INNER JOIN remission_items ON remission_items.id = invoice_items.remission_item_id
             INNER JOIN remissions ON remissions.id = remission_items.remission_id
             WHERE invoice_items.invoice_id = :invoice_id
             ORDER BY invoice_items.id'
        );
        $statement->execute(['invoice_id' => $invoiceId]);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function purchaseOrderChildren(int $contractItemId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT purchase_order_items.*, purchase_orders.order_number
             FROM purchase_order_items
             INNER JOIN purchase_orders ON purchase_orders.id = purchase_order_items.purchase_order_id
             WHERE purchase_order_items.contract_item_id = :contract_item_id
             ORDER BY purchase_order_items.id'
        );
        $statement->execute(['contract_item_id' => $contractItemId]);
        $items = $statement->fetchAll() ?: [];

        foreach ($items as &$item) {
            $item['delivery_note_items'] = $this->deliveryNoteChildren((int) $item['id']);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deliveryNoteChildren(int $purchaseOrderItemId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT delivery_note_items.*, delivery_notes.note_number
             FROM delivery_note_items
             INNER JOIN delivery_notes ON delivery_notes.id = delivery_note_items.delivery_note_id
             WHERE delivery_note_items.purchase_order_item_id = :purchase_order_item_id
             ORDER BY delivery_note_items.id'
        );
        $statement->execute(['purchase_order_item_id' => $purchaseOrderItemId]);
        $items = $statement->fetchAll() ?: [];

        foreach ($items as &$item) {
            $item['remission_items'] = $this->remissionChildren((int) $item['id']);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function remissionChildren(int $deliveryNoteItemId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT remission_items.*, remissions.remission_number
             FROM remission_items
             INNER JOIN remissions ON remissions.id = remission_items.remission_id
             WHERE remission_items.delivery_note_item_id = :delivery_note_item_id
             ORDER BY remission_items.id'
        );
        $statement->execute(['delivery_note_item_id' => $deliveryNoteItemId]);
        $items = $statement->fetchAll() ?: [];

        foreach ($items as &$item) {
            $item['invoice_items'] = $this->invoiceChildren((int) $item['id']);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invoiceChildren(int $remissionItemId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT invoice_items.*, invoices.invoice_number
             FROM invoice_items
             INNER JOIN invoices ON invoices.id = invoice_items.invoice_id
             WHERE invoice_items.remission_item_id = :remission_item_id
             ORDER BY invoice_items.id'
        );
        $statement->execute(['remission_item_id' => $remissionItemId]);

        return $statement->fetchAll() ?: [];
    }
}
