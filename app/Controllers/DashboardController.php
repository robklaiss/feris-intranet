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
use App\Support\Database;

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
            'textileStats' => $this->textileStats(),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int, href: string}>
     */
    private function textileStats(): array
    {
        return [
            ['label' => 'Producciones activas', 'value' => $this->count('SELECT COUNT(*) FROM production_orders WHERE status = "confirmed"'), 'href' => '/production-orders?status=confirmed'],
            ['label' => 'Producciones con faltantes', 'value' => $this->count('SELECT COUNT(*) FROM production_orders WHERE production_stage = "stock_pending"'), 'href' => '/production-orders?production_stage=stock_pending'],
            ['label' => 'Stock checks insuficientes', 'value' => $this->count('SELECT COUNT(*) FROM stock_checks WHERE status = "insufficient"'), 'href' => '/reports?type=textile_raw_material_shortages&status=insufficient'],
            ['label' => 'Compras pendientes', 'value' => $this->count('SELECT COUNT(*) FROM purchase_requisitions WHERE status NOT IN ("cancelled", "closed")'), 'href' => '/reports?type=textile_pending_purchases'],
            ['label' => 'Externos pendientes', 'value' => $this->count('SELECT COUNT(*) FROM external_work_orders WHERE status IN ("sent", "partially_received")'), 'href' => '/reports?type=textile_external_work_pending_return'],
            ['label' => 'Confecciones en progreso', 'value' => $this->count('SELECT COUNT(*) FROM sewing_orders WHERE status IN ("confirmed", "in_progress", "partially_completed")'), 'href' => '/sewing-orders?status=in_progress'],
            ['label' => 'Calidad con reproceso', 'value' => $this->count('SELECT COUNT(*) FROM quality_control_checks WHERE status = "rework_required"'), 'href' => '/quality-control?status=rework_required'],
            ['label' => 'Inventario terminado disponible', 'value' => $this->count('SELECT COUNT(*) FROM finished_goods_inventory WHERE status IN ("available", "reserved") AND quantity_available - quantity_reserved > 0.0001'), 'href' => '/finished-goods-inventory?available_only=1'],
            ['label' => 'Remisiones pendientes', 'value' => $this->count('SELECT COUNT(DISTINCT remissions.id) FROM remissions INNER JOIN remission_items ON remission_items.remission_id = remissions.id WHERE remission_items.finished_goods_inventory_id IS NOT NULL AND remissions.status = "draft"'), 'href' => '/reports?type=textile_remissions_from_finished_goods&status=draft'],
        ];
    }

    private function count(string $sql): int
    {
        return (int) Database::connection()->query($sql)->fetchColumn();
    }
}
