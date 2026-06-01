<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\DeliveryNoteRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\LicitacionRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Repositories\RemissionRepository;
use App\Support\Request;

final class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $clients = new ClientRepository();
        $contracts = new ContractRepository();
        $orders = new PurchaseOrderRepository();
        $notes = new DeliveryNoteRepository();
        $remissions = new RemissionRepository();
        $invoices = new InvoiceRepository();
        $licitaciones = new LicitacionRepository();

        return $this->render('dashboard/index', [
            'stats' => [
                ['label' => 'Clientes', 'value' => $clients->count(), 'href' => '/clients'],
                ['label' => 'Contratos', 'value' => $contracts->count(), 'href' => '/contracts'],
                ['label' => 'Licitaciones', 'value' => $licitaciones->count(), 'href' => '/licitaciones'],
                ['label' => 'Órdenes', 'value' => $orders->count(), 'href' => '/purchase-orders'],
                ['label' => 'Notas', 'value' => $notes->count(), 'href' => '/delivery-notes'],
                ['label' => 'Remisiones', 'value' => $remissions->count(), 'href' => '/remissions'],
                ['label' => 'Facturas', 'value' => $invoices->count(), 'href' => '/invoices'],
            ],
            'recentContracts' => array_slice($contracts->search(), 0, 5),
            'recentOrders' => array_slice($orders->all(), 0, 5),
            'recentInvoices' => array_slice($invoices->all(), 0, 5),
            'recentActivity' => (new AuditLogRepository())->recentDocumentActivity(10),
        ]);
    }
}
