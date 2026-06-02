<?php

declare(strict_types=1);

namespace App\Support;

final class AccessControl
{
    public static function requiresAuthentication(string $path): bool
    {
        return !in_array($path, ['/login'], true);
    }

    public static function permissionFor(string $method, string $path): ?string
    {
        if ($path === '/login') {
            return null;
        }

        if ($path === '/') {
            return 'dashboard.view';
        }

        if (str_starts_with($path, '/reports')) {
            return str_contains($path, '/export') ? 'reports.export' : 'reports.view';
        }

        if (str_starts_with($path, '/settings')) {
            return 'settings.manage';
        }

        if (str_starts_with($path, '/clients')) {
            return $method === 'GET' && !preg_match('#/(create|edit)$#', $path)
                ? 'documents.view'
                : 'clients.manage';
        }

        if (preg_match('#^/documents/[^/]+/\d+/(confirm|cancel|reopen|close)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/licitaciones/\d+/documentos/\d+/download$#', $path)) {
            return 'documents.view';
        }

        if (preg_match('#^/(contracts|purchase-orders|delivery-notes|remissions|invoices)/\d+/(print)$#', $path)) {
            return 'documents.print';
        }

        if (preg_match('#^/(customer-purchase-orders|production-orders)/\d+/(confirm|cancel|close)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/cutting-orders/\d+/(confirm|complete|cancel|close)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/external-work-orders/\d+/(send|cancel|close)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/external-work-orders/\d+/receipts/\d+/confirm$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/sewing-orders/\d+/(confirm|progress|cancel|close)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/quality-control/\d+/(results|confirm|cancel|close)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/quality-reworks/\d+/(complete|cancel|close)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/stock-checks/\d+/(reserve|cancel)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/supplier-quotes/\d+/approve$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/supplier-purchase-orders/\d+/(confirm|cancel|close)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/goods-receipts/\d+/(confirm|cancel)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/(contracts|purchase-orders|delivery-notes|remissions|invoices)/\d+/export/csv$#', $path)) {
            return 'documents.export';
        }

        if (preg_match('#^/contracts/\d+/item-specs/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/contracts/\d+/item-specs$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/contracts/\d+/item-specs/\d+/(edit|update)$#', $path)) {
            return 'documents.edit';
        }

        if (preg_match('#^/contracts/\d+/item-specs/\d+/(confirm|cancel)$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/(contracts|purchase-orders|delivery-notes|remissions|invoices)$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/(customer-purchase-orders|production-orders)$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if ($path === '/raw-materials' && $method === 'POST') {
            return 'documents.create';
        }

        if ($path === '/suppliers' && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/production-orders/\d+/stock-checks$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/production-orders/\d+/cutting-orders$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/cutting-orders/\d+/external-work-orders$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/external-work-orders/\d+/receipts$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/cutting-orders/\d+/sewing-orders$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/external-work-orders/\d+/receipts/\d+/sewing-orders$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/sewing-orders/\d+/quality-control$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/external-work-orders/\d+/receipts/\d+/quality-control$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if ($path === '/seamsters' && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/stock-checks/\d+/purchase-requisitions$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/purchase-requisitions/\d+/suppliers$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/purchase-requisitions/\d+/suppliers/\d+/quotes$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/supplier-purchase-orders/from-quote/\d+$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/supplier-purchase-orders/\d+/goods-receipts$#', $path) && $method === 'POST') {
            return 'documents.create';
        }

        if ($path === '/licitaciones' && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/(contracts|purchase-orders|delivery-notes|remissions|invoices)/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/(customer-purchase-orders|production-orders)/create$#', $path)) {
            return 'documents.create';
        }

        if ($path === '/raw-materials/create') {
            return 'documents.create';
        }

        if ($path === '/suppliers/create') {
            return 'documents.create';
        }

        if (preg_match('#^/production-orders/\d+/stock-checks/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/production-orders/\d+/cutting-orders/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/cutting-orders/\d+/external-work-orders/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/external-work-orders/\d+/receipts/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/cutting-orders/\d+/sewing-orders/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/external-work-orders/\d+/receipts/\d+/sewing-orders/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/sewing-orders/\d+/quality-control/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/external-work-orders/\d+/receipts/\d+/quality-control/create$#', $path)) {
            return 'documents.create';
        }

        if ($path === '/seamsters/create') {
            return 'documents.create';
        }

        if (preg_match('#^/stock-checks/\d+/purchase-requisitions/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/purchase-requisitions/\d+/suppliers/\d+/quotes/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/supplier-purchase-orders/from-quote/\d+/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/supplier-purchase-orders/\d+/goods-receipts/create$#', $path)) {
            return 'documents.create';
        }

        if ($path === '/licitaciones/create') {
            return 'documents.create';
        }

        if (preg_match('#^/contracts/\d+/(edit|update|delete)$#', $path)) {
            return 'documents.edit';
        }

        if (preg_match('#^/customer-purchase-orders/\d+/(edit|update)$#', $path)) {
            return 'documents.edit';
        }

        if (preg_match('#^/raw-materials/\d+/(edit|update)$#', $path)) {
            return 'documents.edit';
        }

        if (preg_match('#^/suppliers/\d+/(edit|update)$#', $path)) {
            return 'documents.edit';
        }

        if (preg_match('#^/seamsters/\d+/(edit|update)$#', $path)) {
            return 'documents.edit';
        }

        if (preg_match('#^/licitaciones/\d+/(edit|update|delete)$#', $path)) {
            return 'documents.edit';
        }

        if (preg_match('#^/invoices/\d+/send$#', $path)) {
            return 'documents.transition';
        }

        if (preg_match('#^/api/#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/licitaciones(/\d+)?$#', $path)) {
            return 'documents.view';
        }

        if (preg_match('#^/(contracts|purchase-orders|delivery-notes|remissions|invoices)(/\d+)?$#', $path)) {
            return 'documents.view';
        }

        if (preg_match('#^/(customer-purchase-orders|production-orders|cutting-orders|external-work-orders|sewing-orders|quality-control|quality-reworks)(/\d+)?$#', $path)) {
            return 'documents.view';
        }

        if (preg_match('#^/(raw-materials|stock-checks)(/\d+)?$#', $path)) {
            return 'documents.view';
        }

        if (preg_match('#^/(suppliers|seamsters|purchase-requisitions|supplier-purchase-orders|goods-receipts)(/\d+)?$#', $path)) {
            return 'documents.view';
        }

        return null;
    }
}
