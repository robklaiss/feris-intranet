<?php

declare(strict_types=1);

namespace App\Repositories;

final class FinishedGoodsInventoryRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                    finished_goods_inventory.*,
                    packaging_orders.packaging_number,
                    production_orders.production_number,
                    clients.name AS client_name,
                    contracts.contract_number,
                    client_dependencies.name AS dependency_name
                FROM finished_goods_inventory
                INNER JOIN packaging_orders ON packaging_orders.id = finished_goods_inventory.packaging_order_id
                INNER JOIN production_orders ON production_orders.id = finished_goods_inventory.production_order_id
                INNER JOIN clients ON clients.id = finished_goods_inventory.client_id
                INNER JOIN contracts ON contracts.id = finished_goods_inventory.contract_id
                LEFT JOIN client_dependencies ON client_dependencies.id = finished_goods_inventory.dependency_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                finished_goods_inventory.internal_code LIKE :q
                OR finished_goods_inventory.package_code LIKE :q
                OR finished_goods_inventory.item_code LIKE :q
                OR finished_goods_inventory.description LIKE :q
                OR finished_goods_inventory.label LIKE :q
                OR clients.name LIKE :q
                OR contracts.contract_number LIKE :q
                OR production_orders.production_number LIKE :q
                OR packaging_orders.packaging_number LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        foreach (['status', 'client_id', 'contract_id', 'dependency_id', 'packaging_order_id', 'item_code', 'size', 'label'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND finished_goods_inventory.{$field} = :{$field}";
                $params[$field] = $filters[$field];
            }
        }

        $sql .= ' ORDER BY finished_goods_inventory.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT
                finished_goods_inventory.*,
                packaging_orders.packaging_number,
                quality_control_checks.id AS quality_control_check_id,
                quality_control_checks.qc_number,
                production_orders.production_number,
                production_orders.customer_purchase_order_id,
                customer_purchase_orders.po_number,
                clients.name AS client_name,
                contracts.contract_number,
                client_dependencies.name AS dependency_name
             FROM finished_goods_inventory
             INNER JOIN packaging_orders ON packaging_orders.id = finished_goods_inventory.packaging_order_id
             INNER JOIN quality_control_checks ON quality_control_checks.id = packaging_orders.quality_control_check_id
             INNER JOIN production_orders ON production_orders.id = finished_goods_inventory.production_order_id
             LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
             INNER JOIN clients ON clients.id = finished_goods_inventory.client_id
             INNER JOIN contracts ON contracts.id = finished_goods_inventory.contract_id
             LEFT JOIN client_dependencies ON client_dependencies.id = finished_goods_inventory.dependency_id
             WHERE finished_goods_inventory.id = :id',
            ['id' => $id]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byPackagingOrder(int $packagingOrderId): array
    {
        return $this->search(['packaging_order_id' => $packagingOrderId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byProductionOrder(int $productionOrderId): array
    {
        return $this->fetchAll(
            'SELECT finished_goods_inventory.*, packaging_orders.packaging_number
             FROM finished_goods_inventory
             INNER JOIN packaging_orders ON packaging_orders.id = finished_goods_inventory.packaging_order_id
             WHERE finished_goods_inventory.production_order_id = :id
             ORDER BY finished_goods_inventory.id DESC',
            ['id' => $productionOrderId]
        );
    }
}
