<?php

declare(strict_types=1);

use App\Controllers\ClientController;
use App\Controllers\ContractController;
use App\Controllers\CuttingOrderController;
use App\Controllers\CustomerPurchaseOrderController;
use App\Controllers\DashboardController;
use App\Controllers\DeliveryNoteController;
use App\Controllers\DocumentFlowController;
use App\Controllers\DocumentOperationController;
use App\Controllers\ExternalWorkOrderController;
use App\Controllers\GoodsReceiptController;
use App\Controllers\InvoiceController;
use App\Controllers\LicitacionController;
use App\Controllers\FinishedGoodsInventoryController;
use App\Controllers\AuthController;
use App\Controllers\ProductionOrderController;
use App\Controllers\PackagingOrderController;
use App\Controllers\QualityControlController;
use App\Controllers\PurchaseOrderController;
use App\Controllers\PurchaseRequisitionController;
use App\Controllers\RawMaterialInventoryController;
use App\Controllers\RemissionController;
use App\Controllers\ReportController;
use App\Controllers\SeamsterController;
use App\Controllers\SettingsController;
use App\Controllers\SewingOrderController;
use App\Controllers\StockCheckController;
use App\Controllers\SupplierController;
use App\Controllers\SupplierPurchaseOrderController;
use App\Controllers\SupplierQuoteController;
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
    $router->get('/production-orders/{id}/stock-checks/create', [StockCheckController::class, 'create']);
    $router->post('/production-orders/{id}/stock-checks', [StockCheckController::class, 'store']);
    $router->get('/production-orders/{id}/cutting-orders/create', [CuttingOrderController::class, 'create']);
    $router->post('/production-orders/{id}/cutting-orders', [CuttingOrderController::class, 'store']);

    $router->get('/cutting-orders', [CuttingOrderController::class, 'index']);
    $router->get('/cutting-orders/{id}', [CuttingOrderController::class, 'show']);
    $router->post('/cutting-orders/{id}/confirm', [CuttingOrderController::class, 'confirm']);
    $router->post('/cutting-orders/{id}/complete', [CuttingOrderController::class, 'complete']);
    $router->post('/cutting-orders/{id}/cancel', [CuttingOrderController::class, 'cancel']);
    $router->post('/cutting-orders/{id}/close', [CuttingOrderController::class, 'close']);
    $router->get('/cutting-orders/{id}/external-work-orders/create', [ExternalWorkOrderController::class, 'createFromCuttingOrder']);
    $router->post('/cutting-orders/{id}/external-work-orders', [ExternalWorkOrderController::class, 'storeFromCuttingOrder']);
    $router->get('/cutting-orders/{id}/sewing-orders/create', [SewingOrderController::class, 'createFromCuttingOrder']);
    $router->post('/cutting-orders/{id}/sewing-orders', [SewingOrderController::class, 'storeFromCuttingOrder']);

    $router->get('/external-work-orders', [ExternalWorkOrderController::class, 'index']);
    $router->get('/external-work-orders/{id}', [ExternalWorkOrderController::class, 'show']);
    $router->post('/external-work-orders/{id}/send', [ExternalWorkOrderController::class, 'send']);
    $router->get('/external-work-orders/{id}/receipts/create', [ExternalWorkOrderController::class, 'createReceipt']);
    $router->post('/external-work-orders/{id}/receipts', [ExternalWorkOrderController::class, 'storeReceipt']);
    $router->post('/external-work-orders/{id}/receipts/{receiptId}/confirm', [ExternalWorkOrderController::class, 'confirmReceipt']);
    $router->get('/external-work-orders/{id}/receipts/{receiptId}/sewing-orders/create', [SewingOrderController::class, 'createFromExternalReceipt']);
    $router->post('/external-work-orders/{id}/receipts/{receiptId}/sewing-orders', [SewingOrderController::class, 'storeFromExternalReceipt']);
    $router->get('/external-work-orders/{id}/receipts/{receiptId}/quality-control/create', [QualityControlController::class, 'createFromExternalReceipt']);
    $router->post('/external-work-orders/{id}/receipts/{receiptId}/quality-control', [QualityControlController::class, 'storeFromExternalReceipt']);
    $router->post('/external-work-orders/{id}/cancel', [ExternalWorkOrderController::class, 'cancel']);
    $router->post('/external-work-orders/{id}/close', [ExternalWorkOrderController::class, 'close']);

    $router->get('/seamsters', [SeamsterController::class, 'index']);
    $router->get('/seamsters/create', [SeamsterController::class, 'create']);
    $router->post('/seamsters', [SeamsterController::class, 'store']);
    $router->get('/seamsters/{id}', [SeamsterController::class, 'show']);
    $router->get('/seamsters/{id}/edit', [SeamsterController::class, 'edit']);
    $router->post('/seamsters/{id}/update', [SeamsterController::class, 'update']);

    $router->get('/sewing-orders', [SewingOrderController::class, 'index']);
    $router->get('/sewing-orders/{id}', [SewingOrderController::class, 'show']);
    $router->post('/sewing-orders/{id}/confirm', [SewingOrderController::class, 'confirm']);
    $router->post('/sewing-orders/{id}/progress', [SewingOrderController::class, 'progress']);
    $router->post('/sewing-orders/{id}/cancel', [SewingOrderController::class, 'cancel']);
    $router->post('/sewing-orders/{id}/close', [SewingOrderController::class, 'close']);
    $router->get('/sewing-orders/{id}/quality-control/create', [QualityControlController::class, 'createFromSewingOrder']);
    $router->post('/sewing-orders/{id}/quality-control', [QualityControlController::class, 'storeFromSewingOrder']);

    $router->get('/quality-control', [QualityControlController::class, 'index']);
    $router->get('/quality-control/{id}', [QualityControlController::class, 'show']);
    $router->get('/quality-control/{id}/packaging-orders/create', [PackagingOrderController::class, 'createFromQualityControl']);
    $router->post('/quality-control/{id}/packaging-orders', [PackagingOrderController::class, 'storeFromQualityControl']);
    $router->post('/quality-control/{id}/results', [QualityControlController::class, 'results']);
    $router->post('/quality-control/{id}/confirm', [QualityControlController::class, 'confirm']);
    $router->post('/quality-control/{id}/cancel', [QualityControlController::class, 'cancel']);
    $router->post('/quality-control/{id}/close', [QualityControlController::class, 'close']);
    $router->get('/quality-reworks', [QualityControlController::class, 'reworks']);
    $router->get('/quality-reworks/{id}', [QualityControlController::class, 'reworkShow']);
    $router->post('/quality-reworks/{id}/complete', [QualityControlController::class, 'completeRework']);
    $router->post('/quality-reworks/{id}/cancel', [QualityControlController::class, 'cancelRework']);
    $router->post('/quality-reworks/{id}/close', [QualityControlController::class, 'closeRework']);

    $router->get('/packaging-orders', [PackagingOrderController::class, 'index']);
    $router->get('/packaging-orders/{id}', [PackagingOrderController::class, 'show']);
    $router->post('/packaging-orders/{id}/pack', [PackagingOrderController::class, 'pack']);
    $router->post('/packaging-orders/{id}/cancel', [PackagingOrderController::class, 'cancel']);
    $router->post('/packaging-orders/{id}/close', [PackagingOrderController::class, 'close']);
    $router->get('/finished-goods-inventory', [FinishedGoodsInventoryController::class, 'index']);
    $router->get('/finished-goods-inventory/{id}', [FinishedGoodsInventoryController::class, 'show']);

    $router->get('/raw-materials', [RawMaterialInventoryController::class, 'index']);
    $router->get('/raw-materials/create', [RawMaterialInventoryController::class, 'create']);
    $router->post('/raw-materials', [RawMaterialInventoryController::class, 'store']);
    $router->get('/raw-materials/{id}', [RawMaterialInventoryController::class, 'show']);
    $router->get('/raw-materials/{id}/edit', [RawMaterialInventoryController::class, 'edit']);
    $router->post('/raw-materials/{id}/update', [RawMaterialInventoryController::class, 'update']);

    $router->get('/suppliers', [SupplierController::class, 'index']);
    $router->get('/suppliers/create', [SupplierController::class, 'create']);
    $router->post('/suppliers', [SupplierController::class, 'store']);
    $router->get('/suppliers/{id}', [SupplierController::class, 'show']);
    $router->get('/suppliers/{id}/edit', [SupplierController::class, 'edit']);
    $router->post('/suppliers/{id}/update', [SupplierController::class, 'update']);

    $router->get('/stock-checks/{id}', [StockCheckController::class, 'show']);
    $router->post('/stock-checks/{id}/reserve', [StockCheckController::class, 'reserve']);
    $router->post('/stock-checks/{id}/cancel', [StockCheckController::class, 'cancel']);
    $router->get('/stock-checks/{id}/purchase-requisitions/create', [PurchaseRequisitionController::class, 'createFromStockCheck']);
    $router->post('/stock-checks/{id}/purchase-requisitions', [PurchaseRequisitionController::class, 'storeFromStockCheck']);

    $router->get('/purchase-requisitions', [PurchaseRequisitionController::class, 'index']);
    $router->get('/purchase-requisitions/{id}', [PurchaseRequisitionController::class, 'show']);
    $router->post('/purchase-requisitions/{id}/suppliers', [PurchaseRequisitionController::class, 'addSuppliers']);
    $router->get('/purchase-requisitions/{id}/suppliers/{supplierId}/quotes/create', [SupplierQuoteController::class, 'create']);
    $router->post('/purchase-requisitions/{id}/suppliers/{supplierId}/quotes', [SupplierQuoteController::class, 'store']);
    $router->post('/supplier-quotes/{id}/approve', [SupplierQuoteController::class, 'approve']);

    $router->get('/supplier-purchase-orders', [SupplierPurchaseOrderController::class, 'index']);
    $router->get('/supplier-purchase-orders/from-quote/{quoteId}/create', [SupplierPurchaseOrderController::class, 'createFromQuote']);
    $router->post('/supplier-purchase-orders/from-quote/{quoteId}', [SupplierPurchaseOrderController::class, 'storeFromQuote']);
    $router->get('/supplier-purchase-orders/{id}', [SupplierPurchaseOrderController::class, 'show']);
    $router->get('/supplier-purchase-orders/{id}/goods-receipts/create', [GoodsReceiptController::class, 'createFromSupplierPurchaseOrder']);
    $router->post('/supplier-purchase-orders/{id}/goods-receipts', [GoodsReceiptController::class, 'storeFromSupplierPurchaseOrder']);
    $router->post('/supplier-purchase-orders/{id}/confirm', [SupplierPurchaseOrderController::class, 'confirm']);
    $router->post('/supplier-purchase-orders/{id}/cancel', [SupplierPurchaseOrderController::class, 'cancel']);
    $router->post('/supplier-purchase-orders/{id}/close', [SupplierPurchaseOrderController::class, 'close']);

    $router->get('/goods-receipts', [GoodsReceiptController::class, 'index']);
    $router->get('/goods-receipts/{id}', [GoodsReceiptController::class, 'show']);
    $router->post('/goods-receipts/{id}/confirm', [GoodsReceiptController::class, 'confirm']);
    $router->post('/goods-receipts/{id}/cancel', [GoodsReceiptController::class, 'cancel']);

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
