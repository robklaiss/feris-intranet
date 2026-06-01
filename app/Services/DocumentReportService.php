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
        $where = [];

        if (!empty($filters['client_id'])) {
            $where[] = ($definition['client_field']) . ' = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }

        if (!empty($filters['contract_number'])) {
            $where[] = ($definition['contract_number_field']) . ' LIKE :contract_number';
            $params['contract_number'] = '%' . trim((string) $filters['contract_number']) . '%';
        }

        if (!empty($filters['identifier_number'])) {
            $where[] = ($definition['identifier_field']) . ' LIKE :identifier_number';
            $params['identifier_number'] = '%' . trim((string) $filters['identifier_number']) . '%';
        }

        $sql = $definition['sql'];
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
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
        ];

        if (!isset($definitions[$type])) {
            throw new RuntimeException('Tipo de reporte no soportado: ' . $type);
        }

        return $definitions[$type];
    }
}
