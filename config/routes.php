<?php

declare(strict_types=1);

use App\Controllers\ClientController;
use App\Controllers\ContractController;
use App\Controllers\CustomerPurchaseOrderController;
use App\Controllers\DashboardController;
use App\Controllers\DeliveryNoteController;
use App\Controllers\DocumentFlowController;
use App\Controllers\DocumentOperationController;
use App\Controllers\InvoiceController;
use App\Controllers\LicitacionController;
use App\Controllers\AuthController;
use App\Controllers\ProductionOrderController;
use App\Controllers\PurchaseOrderController;
use App\Controllers\RemissionController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Support\Router;

return static function (Router $router): void {
    $router->get('/login', [AuthController::class, 'loginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/', [DashboardController::class, 'index']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/clients', [ClientController::class, 'index']);
    $router->get('/clients/create', [ClientController::class, 'create']);
    $router->post('/clients', [ClientController::class, 'store']);
    $router->get('/clients/{id}', [ClientController::class, 'show']);
    $router->get('/clients/{id}/edit', [ClientController::class, 'edit']);
    $router->post('/clients/{id}/update', [ClientController::class, 'update']);
    $router->post('/clients/{id}/delete', [ClientController::class, 'delete']);
    $router->post('/clients/{id}/dependencies', [ClientController::class, 'storeDependency']);
    $router->post('/clients/{id}/dependencies/{dependencyId}/update', [ClientController::class, 'updateDependency']);
    $router->post('/clients/{id}/billing-contacts', [ClientController::class, 'storeBillingContact']);
    $router->post('/clients/{id}/billing-contacts/{contactId}/update', [ClientController::class, 'updateBillingContact']);

    $router->get('/contracts', [ContractController::class, 'index']);
    $router->get('/contracts/create', [ContractController::class, 'create']);
    $router->post('/contracts', [ContractController::class, 'store']);
    $router->get('/contracts/{id}', [ContractController::class, 'show']);
    $router->get('/contracts/{id}/edit', [ContractController::class, 'edit']);
    $router->post('/contracts/{id}/update', [ContractController::class, 'update']);
    $router->post('/contracts/{id}/delete', [ContractController::class, 'delete']);
    $router->get('/contracts/{id}/item-specs/create', [ContractController::class, 'createItemSpec']);
    $router->post('/contracts/{id}/item-specs', [ContractController::class, 'storeItemSpec']);
    $router->get('/contracts/{id}/item-specs/{specId}/edit', [ContractController::class, 'editItemSpec']);
    $router->post('/contracts/{id}/item-specs/{specId}/update', [ContractController::class, 'updateItemSpec']);
    $router->post('/contracts/{id}/item-specs/{specId}/confirm', [ContractController::class, 'confirmItemSpec']);
    $router->post('/contracts/{id}/item-specs/{specId}/cancel', [ContractController::class, 'cancelItemSpec']);
    $router->get('/contracts/{id}/print', [ContractController::class, 'print']);
    $router->get('/contracts/{id}/export/csv', [ContractController::class, 'exportCsv']);

    $router->get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    $router->get('/purchase-orders/create', [PurchaseOrderController::class, 'create']);
    $router->post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    $router->get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
    $router->get('/purchase-orders/{id}/print', [PurchaseOrderController::class, 'print']);
    $router->get('/purchase-orders/{id}/export/csv', [PurchaseOrderController::class, 'exportCsv']);

    $router->get('/customer-purchase-orders', [CustomerPurchaseOrderController::class, 'index']);
    $router->get('/customer-purchase-orders/create', [CustomerPurchaseOrderController::class, 'create']);
    $router->post('/customer-purchase-orders', [CustomerPurchaseOrderController::class, 'store']);
    $router->get('/customer-purchase-orders/{id}', [CustomerPurchaseOrderController::class, 'show']);
    $router->get('/customer-purchase-orders/{id}/edit', [CustomerPurchaseOrderController::class, 'edit']);
    $router->post('/customer-purchase-orders/{id}/update', [CustomerPurchaseOrderController::class, 'update']);
    $router->post('/customer-purchase-orders/{id}/confirm', [CustomerPurchaseOrderController::class, 'confirm']);
    $router->post('/customer-purchase-orders/{id}/cancel', [CustomerPurchaseOrderController::class, 'cancel']);
    $router->post('/customer-purchase-orders/{id}/close', [CustomerPurchaseOrderController::class, 'close']);

    $router->get('/production-orders', [ProductionOrderController::class, 'index']);
    $router->get('/production-orders/create', [ProductionOrderController::class, 'create']);
    $router->post('/production-orders', [ProductionOrderController::class, 'store']);
    $router->get('/production-orders/{id}', [ProductionOrderController::class, 'show']);
    $router->post('/production-orders/{id}/confirm', [ProductionOrderController::class, 'confirm']);
    $router->post('/production-orders/{id}/cancel', [ProductionOrderController::class, 'cancel']);
    $router->post('/production-orders/{id}/close', [ProductionOrderController::class, 'close']);

    $router->get('/delivery-notes', [DeliveryNoteController::class, 'index']);
    $router->get('/delivery-notes/create', [DeliveryNoteController::class, 'create']);
    $router->post('/delivery-notes', [DeliveryNoteController::class, 'store']);
    $router->get('/delivery-notes/{id}', [DeliveryNoteController::class, 'show']);
    $router->get('/delivery-notes/{id}/print', [DeliveryNoteController::class, 'print']);

    $router->get('/remissions', [RemissionController::class, 'index']);
    $router->get('/remissions/create', [RemissionController::class, 'create']);
    $router->post('/remissions', [RemissionController::class, 'store']);
    $router->get('/remissions/{id}', [RemissionController::class, 'show']);
    $router->get('/remissions/{id}/print', [RemissionController::class, 'print']);

    $router->get('/invoices', [InvoiceController::class, 'index']);
    $router->get('/invoices/create', [InvoiceController::class, 'create']);
    $router->post('/invoices', [InvoiceController::class, 'store']);
    $router->get('/invoices/{id}', [InvoiceController::class, 'show']);
    $router->post('/invoices/{id}/send', [InvoiceController::class, 'send']);
    $router->get('/invoices/{id}/print', [InvoiceController::class, 'print']);
    $router->get('/delivery-notes/{id}/export/csv', [DeliveryNoteController::class, 'exportCsv']);
    $router->get('/remissions/{id}/export/csv', [RemissionController::class, 'exportCsv']);
    $router->get('/invoices/{id}/export/csv', [InvoiceController::class, 'exportCsv']);

    $router->get('/licitaciones', [LicitacionController::class, 'index']);
    $router->get('/licitaciones/create', [LicitacionController::class, 'create']);
    $router->post('/licitaciones', [LicitacionController::class, 'store']);
    $router->get('/licitaciones/{id}', [LicitacionController::class, 'show']);
    $router->get('/licitaciones/{id}/edit', [LicitacionController::class, 'edit']);
    $router->post('/licitaciones/{id}/update', [LicitacionController::class, 'update']);
    $router->post('/licitaciones/{id}/delete', [LicitacionController::class, 'delete']);
    $router->get('/licitaciones/{id}/documentos/{fileId}/download', [LicitacionController::class, 'downloadFile']);

    $router->post('/documents/{type}/{id}/confirm', [DocumentOperationController::class, 'confirm']);
    $router->post('/documents/{type}/{id}/cancel', [DocumentOperationController::class, 'cancel']);
    $router->post('/documents/{type}/{id}/reopen', [DocumentOperationController::class, 'reopen']);
    $router->post('/documents/{type}/{id}/close', [DocumentOperationController::class, 'close']);

    $router->get('/reports', [ReportController::class, 'index']);
    $router->get('/reports/export', [ReportController::class, 'export']);
    $router->get('/settings', [SettingsController::class, 'index']);
    $router->post('/settings', [SettingsController::class, 'update']);

    $router->get('/api/contracts/{id}/context', [DocumentFlowController::class, 'contractContext']);
    $router->get('/api/delivery-notes/context', [DocumentFlowController::class, 'deliveryNoteContext']);
    $router->get('/api/remissions/context', [DocumentFlowController::class, 'remissionContext']);
    $router->get('/api/invoices/context', [DocumentFlowController::class, 'invoiceContext']);
};
