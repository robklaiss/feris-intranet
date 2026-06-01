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

        if ($path === '/licitaciones' && $method === 'POST') {
            return 'documents.create';
        }

        if (preg_match('#^/(contracts|purchase-orders|delivery-notes|remissions|invoices)/create$#', $path)) {
            return 'documents.create';
        }

        if (preg_match('#^/(customer-purchase-orders|production-orders)/create$#', $path)) {
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

        if (preg_match('#^/(customer-purchase-orders|production-orders)(/\d+)?$#', $path)) {
            return 'documents.view';
        }

        return null;
    }
}
