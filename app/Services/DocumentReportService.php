<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Support\Database;
use RuntimeException;

final class DocumentReportService
{
    /**
     * @return array<string, string>
     */
    public function availableTypes(): array
    {
        return [
            'contracts' => 'Contratos',
            'purchase_orders' => 'Órdenes de compra',
            'delivery_notes' => 'Notas internas de entrega',
            'remissions' => 'Remisiones',
            'invoices' => 'Facturas',
            'textile_production_by_status' => 'Textil: producción por estado',
            'textile_production_by_client' => 'Textil: producción por cliente',
            'textile_production_by_contract' => 'Textil: producción por contrato',
            'textile_production_by_dependency' => 'Textil: producción por dependencia',
            'textile_stock_pending' => 'Textil: órdenes con stock pendiente',
            'textile_raw_material_shortages' => 'Textil: faltantes de insumos',
            'textile_pending_purchases' => 'Textil: compras pendientes',
            'textile_supplier_pos_pending_receipt' => 'Textil: OC proveedor pendientes de recepción',
            'textile_partial_receipts' => 'Textil: recepciones parciales',
            'textile_pending_cutting' => 'Textil: cortes pendientes',
            'textile_external_work_pending_return' => 'Textil: trabajos externos pendientes de retorno',
            'textile_sewing_by_seamster' => 'Textil: confección por costurero',
            'textile_quality_summary' => 'Textil: calidad',
            'textile_finished_goods_available' => 'Textil: inventario terminado disponible',
            'textile_finished_goods_remitted' => 'Textil: inventario terminado remitido',
            'textile_remissions_from_finished_goods' => 'Textil: remisiones desde inventario terminado',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function report(string $type, array $filters): array
    {
        $definition = $this->definition($type);
        $params = [];
        $where = $definition['base_where'] ?? [];

        if (!empty($filters['client_id']) && !empty($definition['client_field'])) {
            $where[] = ($definition['client_field']) . ' = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }

        if (!empty($filters['contract_number']) && !empty($definition['contract_number_field'])) {
            $where[] = ($definition['contract_number_field']) . ' LIKE :contract_number';
            $params['contract_number'] = '%' . trim((string) $filters['contract_number']) . '%';
        }

        if (!empty($filters['identifier_number']) && !empty($definition['identifier_field'])) {
            $where[] = ($definition['identifier_field']) . ' LIKE :identifier_number';
            $params['identifier_number'] = '%' . trim((string) $filters['identifier_number']) . '%';
        }

        if (!empty($filters['q']) && !empty($definition['q_fields'])) {
            $qParts = [];
            foreach ($definition['q_fields'] as $field) {
                $qParts[] = $field . ' LIKE :q';
            }
            $where[] = '(' . implode(' OR ', $qParts) . ')';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status']) && !empty($definition['status_field'])) {
            $where[] = ($definition['status_field']) . ' = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        if (!empty($filters['date_from']) && !empty($definition['date_field'])) {
            $where[] = ($definition['date_field']) . ' >= :date_from';
            $params['date_from'] = trim((string) $filters['date_from']);
        }

        if (!empty($filters['date_to']) && !empty($definition['date_field'])) {
            $where[] = ($definition['date_field']) . ' <= :date_to';
            $params['date_to'] = trim((string) $filters['date_to']);
        }

        if (!empty($filters['available_only']) && !empty($definition['available_only_condition'])) {
            $where[] = $definition['available_only_condition'];
        }

        $sql = $definition['sql'];
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if (!empty($definition['group_by'])) {
            $sql .= ' ' . $definition['group_by'];
        }
        $sql .= ' ' . $definition['order_by'];

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll() ?: [];

        return [
            'type' => $type,
            'title' => $definition['title'],
            'columns' => $definition['columns'],
            'rows' => $rows,
            'filters' => $filters,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function clients(): array
    {
        return (new ClientRepository())->search(['status' => 'active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $type): array
    {
        $definitions = [
            'contracts' => [
                'title' => 'Reporte de contratos',
                'sql' => 'SELECT contracts.date AS date_label, contracts.contract_number, contracts.reference_number AS identifier_number,
                                 clients.name AS client_name, contracts.contract_type, contracts.status, contracts.total_amount
                          FROM contracts
                          LEFT JOIN clients ON clients.id = contracts.client_id',
                'client_field' => 'contracts.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'order_by' => 'ORDER BY contracts.date DESC, contracts.id DESC',
                'columns' => [
                    ['key' => 'date_label', 'label' => 'Fecha'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'identifier_number', 'label' => 'Nro. ID'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_type', 'label' => 'Modalidad'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'total_amount', 'label' => 'Total', 'money' => true],
                ],
            ],
            'purchase_orders' => [
                'title' => 'Reporte de órdenes de compra',
                'sql' => 'SELECT purchase_orders.order_number, purchase_orders.order_date AS date_label,
                                 contracts.contract_number,
                                 COALESCE(purchase_orders.identifier_number, contracts.reference_number) AS identifier_number,
                                 clients.name AS client_name,
                                 COALESCE(purchase_orders.contract_type, contracts.contract_type) AS contract_type,
                                 purchase_orders.status,
                                 purchase_orders.total_amount
                          FROM purchase_orders
                          LEFT JOIN contracts ON contracts.id = purchase_orders.contract_id
                          LEFT JOIN clients ON clients.id = purchase_orders.client_id',
                'client_field' => 'purchase_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'COALESCE(purchase_orders.identifier_number, contracts.reference_number)',
                'order_by' => 'ORDER BY purchase_orders.order_date DESC, purchase_orders.id DESC',
                'columns' => [
                    ['key' => 'date_label', 'label' => 'Fecha'],
                    ['key' => 'order_number', 'label' => 'Orden'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'identifier_number', 'label' => 'Nro. ID'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_type', 'label' => 'Modalidad'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'total_amount', 'label' => 'Total', 'money' => true],
                ],
            ],
            'delivery_notes' => [
                'title' => 'Reporte de notas internas de entrega',
                'sql' => 'SELECT delivery_notes.note_number, delivery_notes.note_date AS date_label,
                                 contracts.contract_number,
                                 delivery_notes.identifier_number,
                                 clients.name AS client_name,
                                 delivery_notes.contract_type,
                                 delivery_notes.status,
                                 delivery_notes.total_amount
                          FROM delivery_notes
                          LEFT JOIN contracts ON contracts.id = delivery_notes.contract_id
                          LEFT JOIN clients ON clients.id = delivery_notes.client_id',
                'client_field' => 'delivery_notes.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'delivery_notes.identifier_number',
                'order_by' => 'ORDER BY delivery_notes.note_date DESC, delivery_notes.id DESC',
                'columns' => [
                    ['key' => 'date_label', 'label' => 'Fecha'],
                    ['key' => 'note_number', 'label' => 'Nota'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'identifier_number', 'label' => 'Nro. ID'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_type', 'label' => 'Modalidad'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'total_amount', 'label' => 'Total', 'money' => true],
                ],
            ],
            'remissions' => [
                'title' => 'Reporte de remisiones',
                'sql' => 'SELECT remissions.remission_number, remissions.remission_date AS date_label,
                                 contracts.contract_number, remissions.reference_number AS identifier_number,
                                 clients.name AS client_name, remissions.contract_type,
                                 remissions.status, remissions.total_amount
                          FROM remissions
                          LEFT JOIN contracts ON contracts.id = remissions.contract_id
                          LEFT JOIN clients ON clients.id = remissions.client_id',
                'client_field' => 'remissions.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'remissions.reference_number',
                'order_by' => 'ORDER BY remissions.remission_date DESC, remissions.id DESC',
                'columns' => [
                    ['key' => 'date_label', 'label' => 'Fecha'],
                    ['key' => 'remission_number', 'label' => 'Remisión'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'identifier_number', 'label' => 'Nro. ID'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_type', 'label' => 'Modalidad'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'total_amount', 'label' => 'Total', 'money' => true],
                ],
            ],
            'invoices' => [
                'title' => 'Reporte de facturas',
                'sql' => 'SELECT invoices.invoice_number, invoices.invoice_date AS date_label,
                                 contracts.contract_number, invoices.reference_number AS identifier_number,
                                 clients.name AS client_name, invoices.contract_type,
                                 invoices.status, invoices.billing_status, invoices.total_amount
                          FROM invoices
                          LEFT JOIN contracts ON contracts.id = invoices.contract_id
                          LEFT JOIN clients ON clients.id = invoices.client_id',
                'client_field' => 'invoices.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'invoices.reference_number',
                'order_by' => 'ORDER BY invoices.invoice_date DESC, invoices.id DESC',
                'columns' => [
                    ['key' => 'date_label', 'label' => 'Fecha'],
                    ['key' => 'invoice_number', 'label' => 'Factura'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'identifier_number', 'label' => 'Nro. ID'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_type', 'label' => 'Modalidad'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'billing_status', 'label' => 'SIFEN local'],
                    ['key' => 'total_amount', 'label' => 'Total', 'money' => true],
                ],
            ],
            'textile_production_by_status' => [
                'title' => 'Producción textil por estado',
                'sql' => 'SELECT production_orders.status,
                                 production_orders.production_stage,
                                 COUNT(DISTINCT production_orders.id) AS order_count,
                                 COALESCE(SUM(production_order_items.quantity), 0) AS total_quantity
                          FROM production_orders
                          LEFT JOIN production_order_items ON production_order_items.production_order_id = production_orders.id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'production_orders.status',
                'q_fields' => ['production_orders.production_number', 'production_orders.production_stage', 'clients.name', 'clients.tax_id', 'contracts.contract_number', 'contracts.reference_number'],
                'group_by' => 'GROUP BY production_orders.status, production_orders.production_stage',
                'order_by' => 'ORDER BY production_orders.status, production_orders.production_stage',
                'columns' => [
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'production_stage', 'label' => 'Etapa'],
                    ['key' => 'order_count', 'label' => 'Órdenes'],
                    ['key' => 'total_quantity', 'label' => 'Cantidad'],
                ],
            ],
            'textile_production_by_client' => [
                'title' => 'Producción textil por cliente',
                'sql' => 'SELECT clients.name AS client_name,
                                 clients.tax_id,
                                 COUNT(DISTINCT production_orders.id) AS order_count,
                                 COALESCE(SUM(production_order_items.quantity), 0) AS total_quantity,
                                 COALESCE(SUM(production_order_items.balance_quantity), 0) AS pending_quantity
                          FROM production_orders
                          LEFT JOIN production_order_items ON production_order_items.production_order_id = production_orders.id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'production_orders.status',
                'q_fields' => ['clients.name', 'clients.tax_id', 'contracts.contract_number', 'contracts.reference_number', 'production_orders.production_number'],
                'group_by' => 'GROUP BY clients.id, clients.name, clients.tax_id',
                'order_by' => 'ORDER BY client_name',
                'columns' => [
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'tax_id', 'label' => 'RUC'],
                    ['key' => 'order_count', 'label' => 'Órdenes'],
                    ['key' => 'total_quantity', 'label' => 'Cantidad'],
                    ['key' => 'pending_quantity', 'label' => 'Saldo'],
                ],
            ],
            'textile_production_by_contract' => [
                'title' => 'Producción textil por contrato',
                'sql' => 'SELECT contracts.contract_number,
                                 contracts.reference_number AS identifier_number,
                                 clients.name AS client_name,
                                 COUNT(DISTINCT production_orders.id) AS order_count,
                                 COALESCE(SUM(production_order_items.quantity), 0) AS total_quantity,
                                 COALESCE(SUM(production_order_items.balance_quantity), 0) AS pending_quantity
                          FROM production_orders
                          LEFT JOIN production_order_items ON production_order_items.production_order_id = production_orders.id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id
                          LEFT JOIN clients ON clients.id = production_orders.client_id',
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'production_orders.status',
                'q_fields' => ['contracts.contract_number', 'contracts.reference_number', 'clients.name', 'clients.tax_id', 'production_orders.production_number'],
                'group_by' => 'GROUP BY contracts.id, contracts.contract_number, contracts.reference_number, clients.name',
                'order_by' => 'ORDER BY contracts.contract_number',
                'columns' => [
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'identifier_number', 'label' => 'ID licitación'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'order_count', 'label' => 'Órdenes'],
                    ['key' => 'total_quantity', 'label' => 'Cantidad'],
                    ['key' => 'pending_quantity', 'label' => 'Saldo'],
                ],
            ],
            'textile_production_by_dependency' => [
                'title' => 'Producción textil por dependencia',
                'sql' => 'SELECT COALESCE(client_dependencies.name, "Sin dependencia") AS dependency_name,
                                 clients.name AS client_name,
                                 COUNT(DISTINCT production_orders.id) AS order_count,
                                 COALESCE(SUM(production_order_items.quantity), 0) AS total_quantity
                          FROM production_orders
                          LEFT JOIN production_order_items ON production_order_items.production_order_id = production_orders.id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id
                          LEFT JOIN client_dependencies ON client_dependencies.id = production_orders.dependency_id',
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'production_orders.status',
                'q_fields' => ['client_dependencies.name', 'clients.name', 'clients.tax_id', 'contracts.contract_number', 'production_orders.production_number'],
                'group_by' => 'GROUP BY production_orders.dependency_id, dependency_name, clients.name',
                'order_by' => 'ORDER BY client_name, dependency_name',
                'columns' => [
                    ['key' => 'dependency_name', 'label' => 'Dependencia'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'order_count', 'label' => 'Órdenes'],
                    ['key' => 'total_quantity', 'label' => 'Cantidad'],
                ],
            ],
            'textile_stock_pending' => [
                'title' => 'Órdenes con stock pendiente',
                'sql' => 'SELECT production_orders.production_number,
                                 production_orders.production_stage,
                                 production_orders.status,
                                 customer_purchase_orders.po_number,
                                 clients.name AS client_name,
                                 clients.tax_id,
                                 contracts.contract_number,
                                 contracts.reference_number AS identifier_number
                          FROM production_orders
                          LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'base_where' => ['production_orders.production_stage = "stock_pending"'],
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'production_orders.status',
                'q_fields' => ['production_orders.production_number', 'customer_purchase_orders.po_number', 'clients.name', 'clients.tax_id', 'contracts.contract_number', 'contracts.reference_number'],
                'order_by' => 'ORDER BY production_orders.updated_at DESC, production_orders.id DESC',
                'columns' => [
                    ['key' => 'production_number', 'label' => 'Producción'],
                    ['key' => 'production_stage', 'label' => 'Etapa'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'po_number', 'label' => 'OC cliente'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'tax_id', 'label' => 'RUC'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'identifier_number', 'label' => 'ID licitación'],
                ],
            ],
            'textile_raw_material_shortages' => [
                'title' => 'Faltantes de insumos',
                'sql' => 'SELECT stock_checks.check_number,
                                 production_orders.production_number,
                                 production_order_items.item_code,
                                 stock_check_items.required_material_type,
                                 stock_check_items.required_description,
                                 stock_check_items.required_quantity,
                                 stock_check_items.available_quantity,
                                 stock_check_items.missing_quantity,
                                 stock_checks.status,
                                 clients.name AS client_name,
                                 contracts.contract_number
                          FROM stock_check_items
                          INNER JOIN stock_checks ON stock_checks.id = stock_check_items.stock_check_id
                          INNER JOIN production_orders ON production_orders.id = stock_checks.production_order_id
                          INNER JOIN production_order_items ON production_order_items.id = stock_check_items.production_order_item_id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'base_where' => ['stock_check_items.missing_quantity > 0.0001'],
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'stock_checks.status',
                'q_fields' => ['stock_checks.check_number', 'production_orders.production_number', 'production_order_items.item_code', 'stock_check_items.required_material_type', 'stock_check_items.required_description', 'clients.name', 'clients.tax_id', 'contracts.contract_number'],
                'order_by' => 'ORDER BY stock_checks.checked_at DESC, stock_checks.id DESC',
                'columns' => [
                    ['key' => 'check_number', 'label' => 'Stock check'],
                    ['key' => 'production_number', 'label' => 'Producción'],
                    ['key' => 'item_code', 'label' => 'Ítem'],
                    ['key' => 'required_material_type', 'label' => 'Insumo'],
                    ['key' => 'required_quantity', 'label' => 'Requerido'],
                    ['key' => 'available_quantity', 'label' => 'Disponible'],
                    ['key' => 'missing_quantity', 'label' => 'Faltante'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                ],
            ],
            'textile_pending_purchases' => [
                'title' => 'Compras pendientes',
                'sql' => 'SELECT purchase_requisitions.requisition_number,
                                 purchase_requisitions.status,
                                 production_orders.production_number,
                                 clients.name AS client_name,
                                 contracts.contract_number,
                                 COALESCE(SUM(purchase_requisition_items.requested_quantity), 0) AS requested_quantity,
                                 COUNT(DISTINCT supplier_quote_requests.supplier_id) AS requested_suppliers
                          FROM purchase_requisitions
                          INNER JOIN production_orders ON production_orders.id = purchase_requisitions.production_order_id
                          LEFT JOIN purchase_requisition_items ON purchase_requisition_items.purchase_requisition_id = purchase_requisitions.id
                          LEFT JOIN supplier_quote_requests ON supplier_quote_requests.purchase_requisition_id = purchase_requisitions.id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'base_where' => ['purchase_requisitions.status NOT IN ("cancelled", "closed")'],
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'purchase_requisitions.status',
                'q_fields' => ['purchase_requisitions.requisition_number', 'production_orders.production_number', 'clients.name', 'clients.tax_id', 'contracts.contract_number'],
                'group_by' => 'GROUP BY purchase_requisitions.id',
                'order_by' => 'ORDER BY purchase_requisitions.updated_at DESC, purchase_requisitions.id DESC',
                'columns' => [
                    ['key' => 'requisition_number', 'label' => 'Pedido'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'production_number', 'label' => 'Producción'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'requested_quantity', 'label' => 'Cantidad solicitada'],
                    ['key' => 'requested_suppliers', 'label' => 'Proveedores'],
                ],
            ],
            'textile_supplier_pos_pending_receipt' => [
                'title' => 'OC proveedor pendientes de recepción',
                'sql' => 'SELECT supplier_purchase_orders.supplier_po_number,
                                 supplier_purchase_orders.status,
                                 suppliers.name AS supplier_name,
                                 purchase_requisitions.requisition_number,
                                 COALESCE(SUM(supplier_purchase_order_items.quantity), 0) AS ordered_quantity,
                                 COALESCE(received.received_quantity, 0) AS received_quantity,
                                 COALESCE(SUM(supplier_purchase_order_items.quantity), 0) - COALESCE(received.received_quantity, 0) AS pending_quantity
                          FROM supplier_purchase_orders
                          INNER JOIN suppliers ON suppliers.id = supplier_purchase_orders.supplier_id
                          INNER JOIN purchase_requisitions ON purchase_requisitions.id = supplier_purchase_orders.purchase_requisition_id
                          LEFT JOIN supplier_purchase_order_items ON supplier_purchase_order_items.supplier_purchase_order_id = supplier_purchase_orders.id
                          LEFT JOIN (
                              SELECT supplier_purchase_order_id, SUM(accepted_quantity) AS received_quantity
                              FROM goods_receipts
                              INNER JOIN goods_receipt_items ON goods_receipt_items.goods_receipt_id = goods_receipts.id
                              WHERE goods_receipts.status = "confirmed"
                              GROUP BY supplier_purchase_order_id
                          ) received ON received.supplier_purchase_order_id = supplier_purchase_orders.id',
                'base_where' => ['supplier_purchase_orders.status IN ("confirmed", "sent", "partially_received")'],
                'status_field' => 'supplier_purchase_orders.status',
                'q_fields' => ['supplier_purchase_orders.supplier_po_number', 'suppliers.name', 'suppliers.ruc', 'purchase_requisitions.requisition_number'],
                'group_by' => 'GROUP BY supplier_purchase_orders.id',
                'order_by' => 'ORDER BY supplier_purchase_orders.expected_delivery_date IS NULL, supplier_purchase_orders.expected_delivery_date, supplier_purchase_orders.id DESC',
                'columns' => [
                    ['key' => 'supplier_po_number', 'label' => 'OC proveedor'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'supplier_name', 'label' => 'Proveedor'],
                    ['key' => 'requisition_number', 'label' => 'Pedido'],
                    ['key' => 'ordered_quantity', 'label' => 'Pedido'],
                    ['key' => 'received_quantity', 'label' => 'Recibido'],
                    ['key' => 'pending_quantity', 'label' => 'Pendiente'],
                ],
            ],
            'textile_partial_receipts' => [
                'title' => 'Recepciones parciales',
                'sql' => 'SELECT goods_receipts.receipt_number,
                                 goods_receipts.status,
                                 supplier_purchase_orders.supplier_po_number,
                                 suppliers.name AS supplier_name,
                                 COALESCE(SUM(goods_receipt_items.ordered_quantity), 0) AS ordered_quantity,
                                 COALESCE(SUM(goods_receipt_items.accepted_quantity), 0) AS accepted_quantity,
                                 COALESCE(SUM(goods_receipt_items.rejected_quantity), 0) AS rejected_quantity
                          FROM goods_receipts
                          INNER JOIN supplier_purchase_orders ON supplier_purchase_orders.id = goods_receipts.supplier_purchase_order_id
                          INNER JOIN suppliers ON suppliers.id = goods_receipts.supplier_id
                          LEFT JOIN goods_receipt_items ON goods_receipt_items.goods_receipt_id = goods_receipts.id',
                'base_where' => ['goods_receipts.status = "confirmed"'],
                'status_field' => 'goods_receipts.status',
                'q_fields' => ['goods_receipts.receipt_number', 'supplier_purchase_orders.supplier_po_number', 'suppliers.name', 'suppliers.ruc'],
                'group_by' => 'GROUP BY goods_receipts.id HAVING accepted_quantity < ordered_quantity',
                'order_by' => 'ORDER BY goods_receipts.received_at DESC, goods_receipts.id DESC',
                'columns' => [
                    ['key' => 'receipt_number', 'label' => 'Recepción'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'supplier_po_number', 'label' => 'OC proveedor'],
                    ['key' => 'supplier_name', 'label' => 'Proveedor'],
                    ['key' => 'ordered_quantity', 'label' => 'Pedido'],
                    ['key' => 'accepted_quantity', 'label' => 'Aceptado'],
                    ['key' => 'rejected_quantity', 'label' => 'Rechazado'],
                ],
            ],
            'textile_pending_cutting' => [
                'title' => 'Cortes pendientes',
                'sql' => 'SELECT production_orders.production_number,
                                 production_orders.production_stage,
                                 production_orders.status,
                                 customer_purchase_orders.po_number,
                                 clients.name AS client_name,
                                 contracts.contract_number
                          FROM production_orders
                          LEFT JOIN customer_purchase_orders ON customer_purchase_orders.id = production_orders.customer_purchase_order_id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'base_where' => ['production_orders.status = "confirmed"', 'production_orders.production_stage = "ready_for_cutting"'],
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'q_fields' => ['production_orders.production_number', 'customer_purchase_orders.po_number', 'clients.name', 'clients.tax_id', 'contracts.contract_number'],
                'order_by' => 'ORDER BY production_orders.updated_at DESC, production_orders.id DESC',
                'columns' => [
                    ['key' => 'production_number', 'label' => 'Producción'],
                    ['key' => 'production_stage', 'label' => 'Etapa'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'po_number', 'label' => 'OC cliente'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                ],
            ],
            'textile_external_work_pending_return' => [
                'title' => 'Trabajos externos pendientes de retorno',
                'sql' => 'SELECT external_work_orders.external_work_number,
                                 external_work_orders.work_type,
                                 external_work_orders.status,
                                 external_work_orders.expected_return_date,
                                 suppliers.name AS supplier_name,
                                 production_orders.production_number,
                                 cutting_orders.cutting_number,
                                 clients.name AS client_name,
                                 contracts.contract_number
                          FROM external_work_orders
                          INNER JOIN production_orders ON production_orders.id = external_work_orders.production_order_id
                          INNER JOIN cutting_orders ON cutting_orders.id = external_work_orders.cutting_order_id
                          LEFT JOIN suppliers ON suppliers.id = external_work_orders.supplier_id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'base_where' => ['external_work_orders.status IN ("sent", "partially_received")'],
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'external_work_orders.status',
                'date_field' => 'external_work_orders.expected_return_date',
                'q_fields' => ['external_work_orders.external_work_number', 'external_work_orders.send_note_number', 'suppliers.name', 'suppliers.ruc', 'production_orders.production_number', 'cutting_orders.cutting_number', 'clients.name', 'contracts.contract_number'],
                'order_by' => 'ORDER BY external_work_orders.expected_return_date IS NULL, external_work_orders.expected_return_date, external_work_orders.id DESC',
                'columns' => [
                    ['key' => 'external_work_number', 'label' => 'Trabajo externo'],
                    ['key' => 'work_type', 'label' => 'Tipo'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'expected_return_date', 'label' => 'Retorno esperado'],
                    ['key' => 'supplier_name', 'label' => 'Proveedor'],
                    ['key' => 'production_number', 'label' => 'Producción'],
                    ['key' => 'cutting_number', 'label' => 'Corte'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                ],
            ],
            'textile_sewing_by_seamster' => [
                'title' => 'Confección por costurero',
                'sql' => 'SELECT seamsters.name AS seamster_name,
                                 sewing_orders.status,
                                 COUNT(DISTINCT sewing_orders.id) AS order_count,
                                 COALESCE(SUM(sewing_order_items.quantity_assigned), 0) AS assigned_quantity,
                                 COALESCE(SUM(sewing_order_items.quantity_completed), 0) AS completed_quantity,
                                 COALESCE(SUM(sewing_order_items.quantity_pending), 0) AS pending_quantity
                          FROM sewing_orders
                          INNER JOIN seamsters ON seamsters.id = sewing_orders.seamster_id
                          LEFT JOIN sewing_order_items ON sewing_order_items.sewing_order_id = sewing_orders.id
                          INNER JOIN production_orders ON production_orders.id = sewing_orders.production_order_id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'sewing_orders.status',
                'q_fields' => ['seamsters.name', 'sewing_orders.sewing_number', 'production_orders.production_number', 'clients.name', 'contracts.contract_number'],
                'group_by' => 'GROUP BY seamsters.id, seamsters.name, sewing_orders.status',
                'order_by' => 'ORDER BY seamster_name, sewing_orders.status',
                'columns' => [
                    ['key' => 'seamster_name', 'label' => 'Costurero'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'order_count', 'label' => 'Órdenes'],
                    ['key' => 'assigned_quantity', 'label' => 'Asignado'],
                    ['key' => 'completed_quantity', 'label' => 'Confeccionado'],
                    ['key' => 'pending_quantity', 'label' => 'Pendiente'],
                ],
            ],
            'textile_quality_summary' => [
                'title' => 'Calidad: aprobados, rechazados y reproceso',
                'sql' => 'SELECT quality_control_checks.status,
                                 COUNT(DISTINCT quality_control_checks.id) AS check_count,
                                 COALESCE(SUM(quality_control_check_items.quantity_approved), 0) AS approved_quantity,
                                 COALESCE(SUM(quality_control_check_items.quantity_rejected), 0) AS rejected_quantity,
                                 COALESCE(SUM(quality_control_check_items.quantity_rework), 0) AS rework_quantity
                          FROM quality_control_checks
                          LEFT JOIN quality_control_check_items ON quality_control_check_items.quality_control_check_id = quality_control_checks.id
                          INNER JOIN production_orders ON production_orders.id = quality_control_checks.production_order_id
                          LEFT JOIN clients ON clients.id = production_orders.client_id
                          LEFT JOIN contracts ON contracts.id = production_orders.contract_id',
                'client_field' => 'production_orders.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'quality_control_checks.status',
                'q_fields' => ['quality_control_checks.qc_number', 'production_orders.production_number', 'clients.name', 'contracts.contract_number'],
                'group_by' => 'GROUP BY quality_control_checks.status',
                'order_by' => 'ORDER BY quality_control_checks.status',
                'columns' => [
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'check_count', 'label' => 'Controles'],
                    ['key' => 'approved_quantity', 'label' => 'Aprobado'],
                    ['key' => 'rejected_quantity', 'label' => 'Rechazado'],
                    ['key' => 'rework_quantity', 'label' => 'Reproceso'],
                ],
            ],
            'textile_finished_goods_available' => [
                'title' => 'Inventario terminado disponible',
                'sql' => 'SELECT finished_goods_inventory.internal_code,
                                 finished_goods_inventory.item_code,
                                 finished_goods_inventory.description,
                                 finished_goods_inventory.size,
                                 finished_goods_inventory.color,
                                 finished_goods_inventory.label,
                                 finished_goods_inventory.quantity_available,
                                 finished_goods_inventory.quantity_reserved,
                                 finished_goods_inventory.quantity_available - finished_goods_inventory.quantity_reserved AS real_available,
                                 finished_goods_inventory.status,
                                 clients.name AS client_name,
                                 contracts.contract_number,
                                 production_orders.production_number
                          FROM finished_goods_inventory
                          INNER JOIN clients ON clients.id = finished_goods_inventory.client_id
                          INNER JOIN contracts ON contracts.id = finished_goods_inventory.contract_id
                          INNER JOIN production_orders ON production_orders.id = finished_goods_inventory.production_order_id',
                'base_where' => ['finished_goods_inventory.status IN ("available", "reserved")', 'finished_goods_inventory.quantity_available - finished_goods_inventory.quantity_reserved > 0.0001'],
                'client_field' => 'finished_goods_inventory.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'finished_goods_inventory.status',
                'q_fields' => ['finished_goods_inventory.internal_code', 'finished_goods_inventory.item_code', 'finished_goods_inventory.description', 'finished_goods_inventory.size', 'finished_goods_inventory.color', 'finished_goods_inventory.label', 'clients.name', 'clients.tax_id', 'contracts.contract_number', 'production_orders.production_number'],
                'order_by' => 'ORDER BY finished_goods_inventory.id DESC',
                'columns' => [
                    ['key' => 'internal_code', 'label' => 'Código'],
                    ['key' => 'item_code', 'label' => 'Ítem'],
                    ['key' => 'description', 'label' => 'Producto'],
                    ['key' => 'size', 'label' => 'Talle'],
                    ['key' => 'color', 'label' => 'Color'],
                    ['key' => 'label', 'label' => 'Etiqueta'],
                    ['key' => 'real_available', 'label' => 'Disponible real'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'production_number', 'label' => 'Producción'],
                ],
            ],
            'textile_finished_goods_remitted' => [
                'title' => 'Inventario terminado remitido',
                'sql' => 'SELECT finished_goods_inventory.internal_code,
                                 finished_goods_inventory.item_code,
                                 finished_goods_inventory.description,
                                 finished_goods_inventory.quantity_remitted,
                                 finished_goods_inventory.status,
                                 clients.name AS client_name,
                                 contracts.contract_number,
                                 production_orders.production_number
                          FROM finished_goods_inventory
                          INNER JOIN clients ON clients.id = finished_goods_inventory.client_id
                          INNER JOIN contracts ON contracts.id = finished_goods_inventory.contract_id
                          INNER JOIN production_orders ON production_orders.id = finished_goods_inventory.production_order_id',
                'base_where' => ['finished_goods_inventory.quantity_remitted > 0.0001'],
                'client_field' => 'finished_goods_inventory.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'contracts.reference_number',
                'status_field' => 'finished_goods_inventory.status',
                'q_fields' => ['finished_goods_inventory.internal_code', 'finished_goods_inventory.item_code', 'finished_goods_inventory.description', 'clients.name', 'clients.tax_id', 'contracts.contract_number', 'production_orders.production_number'],
                'order_by' => 'ORDER BY finished_goods_inventory.updated_at DESC, finished_goods_inventory.id DESC',
                'columns' => [
                    ['key' => 'internal_code', 'label' => 'Código'],
                    ['key' => 'item_code', 'label' => 'Ítem'],
                    ['key' => 'description', 'label' => 'Producto'],
                    ['key' => 'quantity_remitted', 'label' => 'Remitido'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'production_number', 'label' => 'Producción'],
                ],
            ],
            'textile_remissions_from_finished_goods' => [
                'title' => 'Remisiones desde inventario terminado',
                'sql' => 'SELECT remissions.remission_number,
                                 remissions.remission_date AS date_label,
                                 remissions.status,
                                 clients.name AS client_name,
                                 contracts.contract_number,
                                 COUNT(remission_items.id) AS item_count,
                                 COALESCE(SUM(remission_items.quantity), 0) AS remitted_quantity,
                                 remissions.total_amount
                          FROM remissions
                          INNER JOIN remission_items ON remission_items.remission_id = remissions.id
                          LEFT JOIN clients ON clients.id = remissions.client_id
                          LEFT JOIN contracts ON contracts.id = remissions.contract_id',
                'base_where' => ['remission_items.finished_goods_inventory_id IS NOT NULL'],
                'client_field' => 'remissions.client_id',
                'contract_number_field' => 'contracts.contract_number',
                'identifier_field' => 'remissions.reference_number',
                'status_field' => 'remissions.status',
                'date_field' => 'remissions.remission_date',
                'q_fields' => ['remissions.remission_number', 'remissions.reference_number', 'clients.name', 'clients.tax_id', 'contracts.contract_number', 'remission_items.item_code', 'remission_items.label'],
                'group_by' => 'GROUP BY remissions.id',
                'order_by' => 'ORDER BY remissions.remission_date DESC, remissions.id DESC',
                'columns' => [
                    ['key' => 'date_label', 'label' => 'Fecha'],
                    ['key' => 'remission_number', 'label' => 'Remisión'],
                    ['key' => 'status', 'label' => 'Estado'],
                    ['key' => 'client_name', 'label' => 'Cliente'],
                    ['key' => 'contract_number', 'label' => 'Contrato'],
                    ['key' => 'item_count', 'label' => 'Ítems'],
                    ['key' => 'remitted_quantity', 'label' => 'Cantidad'],
                    ['key' => 'total_amount', 'label' => 'Total', 'money' => true],
                ],
            ],
        ];

        if (!isset($definitions[$type])) {
            throw new RuntimeException('Tipo de reporte no soportado: ' . $type);
        }

        return $definitions[$type];
    }
}
