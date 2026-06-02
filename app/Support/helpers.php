<?php

declare(strict_types=1);

use App\Support\Config;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;
use App\Repositories\AuditLogRepository;
use App\Services\DocumentWorkflowService;

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function base_path(string $path = ''): string
{
    return APP_BASE_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

function storage_path(string $path = ''): string
{
    return base_path('storage/' . ltrim($path, '/'));
}

function normalize_base_uri(string $baseUri): string
{
    $baseUri = parse_url($baseUri, PHP_URL_PATH) ?: $baseUri;
    $baseUri = '/' . trim($baseUri, '/');

    return $baseUri === '/' ? '' : $baseUri;
}

function detected_base_uri(): string
{
    $scriptName = parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH) ?: '';
    $scriptName = str_replace('\\', '/', $scriptName);

    if ($scriptName === '' || !str_starts_with($scriptName, '/') || $scriptName === '/index.php') {
        return '';
    }

    $directory = str_replace('\\', '/', dirname($scriptName));
    $directory = normalize_base_uri($directory);

    if ($directory === '/public') {
        return '';
    }

    if (str_ends_with($directory, '/public')) {
        $directory = substr($directory, 0, -strlen('/public'));
    }

    return normalize_base_uri($directory);
}

function app_base_uri(): string
{
    $baseUri = normalize_base_uri((string) config('app.base_uri', ''));

    return $baseUri !== '' ? $baseUri : detected_base_uri();
}

function app_request_path(string $path): string
{
    $path = parse_url($path, PHP_URL_PATH) ?: '/';
    $path = '/' . trim($path, '/');
    $path = $path === '/' ? '/' : rtrim($path, '/');

    $baseUri = app_base_uri();
    if ($baseUri !== '' && ($path === $baseUri || str_starts_with($path, $baseUri . '/'))) {
        $path = substr($path, strlen($baseUri)) ?: '/';
    }

    return $path === '' ? '/' : $path;
}

function url(string $path = ''): string
{
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) || str_starts_with($path, '//')) {
        return $path;
    }

    $baseUri = app_base_uri();
    if ($path === '' || $path === '/') {
        return $baseUri ?: '/';
    }

    $path = '/' . ltrim($path, '/');

    return $baseUri . $path;
}

function redirect(string $path): Response
{
    return Response::redirect($path);
}

function view(string $template, array $data = [], string $layout = 'layouts/app'): Response
{
    return Response::html(View::render($template, $data, $layout));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, mixed $default = null): mixed
{
    return Session::consumeFlash('_old.' . $key, $default);
}

function flash(string $key, mixed $default = null): mixed
{
    return Session::consumeFlash($key, $default);
}

function back(string $fallback = '/'): Response
{
    $location = $_SERVER['HTTP_REFERER'] ?? $fallback;
    return redirect((string) $location);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

/**
 * @return array{name:string, role:string}
 */
function current_user(): array
{
    $user = Auth::user();

    if ($user) {
        return [
            'name' => (string) ($user['name'] ?? $user['username'] ?? 'Usuario'),
            'role' => role_label((string) ($user['role'] ?? '')),
        ];
    }

    return [
        'name' => 'Invitado',
        'role' => 'Sin sesión',
    ];
}

function auth_check(): bool
{
    return Auth::check();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function document_statuses(): array
{
    return (new DocumentWorkflowService())->statuses();
}

function document_status_label(?string $status): string
{
    return (new DocumentWorkflowService())->statusLabel($status);
}

function status_badge_class(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'badge badge--draft',
        'confirmed' => 'badge badge--confirmed',
        'cancelled' => 'badge badge--cancelled',
        'closed' => 'badge badge--closed',
        'pending', 'stock_pending' => 'badge badge--draft',
        'ready_for_stock_check', 'ready_for_cutting', 'in_cutting', 'waiting_external_work', 'external_work_sent', 'external_work_received', 'in_sewing', 'quality_control', 'sufficient', 'reserved', 'active', 'cut', 'consumed', 'completed', 'returned' => 'badge badge--confirmed',
        'in_progress', 'partial', 'partially_returned' => 'badge badge--draft',
        'insufficient', 'depleted' => 'badge badge--cancelled',
        'inactive' => 'badge',
        'accepted', 'sent', 'processed', 'requested', 'quoted', 'approved', 'received', 'partially_received' => 'badge badge--confirmed',
        'error', 'rejected' => 'badge badge--cancelled',
        default => 'badge',
    };
}

/**
 * @param array<string, mixed> $document
 * @return array<string, mixed>
 */
function document_meta(string $type, array $document): array
{
    return (new DocumentWorkflowService())->metadata($type, (int) $document['id']);
}

function document_transition_path(string $type, int $id, string $action): string
{
    return url(sprintf('/documents/%s/%d/%s', $type, $id, $action));
}

/**
 * @return array{url:string, label:string}|null
 */
function document_next_step(string $type, int $id): ?array
{
    return match ($type) {
        'contracts' => ['url' => url('/purchase-orders/create?contract_id=' . $id), 'label' => 'Generar orden'],
        'purchase_orders' => ['url' => url('/delivery-notes/create?purchase_order_id=' . $id), 'label' => 'Crear nota'],
        'delivery_notes' => ['url' => url('/remissions/create?delivery_note_ids=' . $id), 'label' => 'Nueva remisión'],
        'remissions' => ['url' => url('/invoices/create?remission_ids=' . $id), 'label' => 'Nueva factura'],
        default => null,
    };
}

function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'operador' => 'Operador',
        'consulta' => 'Consulta',
        default => ucfirst($role),
    };
}

function audit_action_label(string $action): string
{
    return match ($action) {
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
        'confirm' => 'Confirmado',
        'cancel' => 'Anulado',
        'reopen' => 'Reabierto',
        'close' => 'Cerrado',
        'printed' => 'Impreso',
        'exported' => 'Exportado',
        'create_client_dependency' => 'Dependencia creada',
        'update_client_dependency' => 'Dependencia actualizada',
        'create_billing_contact' => 'Contacto de facturación creado',
        'update_billing_contact' => 'Contacto de facturación actualizado',
        'update_contract_dncp_data' => 'Datos DNCP actualizados',
        'create_contract_item_spec' => 'Item técnico creado',
        'update_contract_item_spec' => 'Item técnico actualizado',
        'confirm_contract_item_spec' => 'Item técnico confirmado',
        'cancel_contract_item_spec' => 'Item técnico anulado',
        'create_customer_purchase_order' => 'OC cliente creada',
        'update_customer_purchase_order' => 'OC cliente actualizada',
        'confirm_customer_purchase_order' => 'OC cliente confirmada',
        'cancel_customer_purchase_order' => 'OC cliente anulada',
        'close_customer_purchase_order' => 'OC cliente cerrada',
        'create_production_order' => 'Orden de producción creada',
        'update_production_order' => 'Orden de producción actualizada',
        'confirm_production_order' => 'Orden de producción confirmada',
        'cancel_production_order' => 'Orden de producción anulada',
        'close_production_order' => 'Orden de producción cerrada',
        'create_stock_check' => 'Verificación de stock creada',
        'reserve_stock_check' => 'Stock reservado',
        'cancel_stock_check' => 'Verificación de stock anulada',
        'create_supplier' => 'Proveedor creado',
        'update_supplier' => 'Proveedor actualizado',
        'create_purchase_requisition' => 'Pedido de presupuesto creado',
        'add_supplier_quote_request' => 'Solicitud a proveedor registrada',
        'register_supplier_quote' => 'Presupuesto proveedor registrado',
        'approve_supplier_quote' => 'Presupuesto proveedor aprobado',
        'create_supplier_purchase_order' => 'OC proveedor creada',
        'confirm_supplier_purchase_order' => 'OC proveedor confirmada',
        'cancel_supplier_purchase_order' => 'OC proveedor anulada',
        'close_supplier_purchase_order' => 'OC proveedor cerrada',
        'create_goods_receipt' => 'Recepción creada',
        'update_goods_receipt' => 'Recepción actualizada',
        'confirm_goods_receipt' => 'Recepción confirmada',
        'cancel_goods_receipt' => 'Recepción anulada',
        'create_raw_material_from_receipt' => 'Insumo ingresado desde recepción',
        'update_supplier_purchase_order_receipt_status' => 'Estado de OC proveedor actualizado por recepción',
        'create_cutting_order' => 'Orden de corte creada',
        'confirm_cutting_order' => 'Orden de corte confirmada',
        'complete_cutting_order' => 'Orden de corte completada',
        'cancel_cutting_order' => 'Orden de corte anulada',
        'close_cutting_order' => 'Orden de corte cerrada',
        'create_external_work_order' => 'Trabajo externo creado',
        'update_external_work_order' => 'Trabajo externo actualizado',
        'send_external_work_order' => 'Trabajo externo enviado',
        'create_external_work_receipt' => 'Recepción externa creada',
        'confirm_external_work_receipt' => 'Recepción externa confirmada',
        'cancel_external_work_order' => 'Trabajo externo anulado',
        'close_external_work_order' => 'Trabajo externo cerrado',
        'send_simulated' => 'Enviado a placeholder',
        default => ucfirst(str_replace('_', ' ', $action)),
    };
}

function production_stage_label(?string $stage): string
{
    return match ((string) $stage) {
        'pending' => 'Pendiente',
        'stock_pending' => 'Stock pendiente',
        'ready_for_stock_check' => 'Listo para verificar stock',
        'ready_for_cutting' => 'Listo para corte',
        'in_cutting' => 'En corte',
        'waiting_external_work' => 'Pendiente de serigrafía/bordado',
        'external_work_sent' => 'En proveedor externo',
        'external_work_received' => 'Retorno externo parcial',
        'in_sewing' => 'Pendiente de confección',
        'quality_control' => 'Pendiente de control de calidad',
        default => ucfirst(str_replace('_', ' ', (string) $stage)),
    };
}

function cutting_order_status_label(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Borrador',
        'confirmed' => 'Confirmada',
        'in_progress' => 'En proceso',
        'completed' => 'Completada',
        'cancelled' => 'Anulada',
        'closed' => 'Cerrada',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function cutting_item_status_label(?string $status): string
{
    return match ((string) $status) {
        'pending' => 'Pendiente',
        'cut' => 'Cortado',
        'partial' => 'Parcial',
        'cancelled' => 'Anulado',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function cutting_material_status_label(?string $status): string
{
    return match ((string) $status) {
        'reserved' => 'Reservado',
        'consumed' => 'Consumido',
        'released' => 'Liberado',
        'cancelled' => 'Anulado',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function external_work_order_status_label(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Borrador',
        'confirmed' => 'Confirmado',
        'sent' => 'Enviado',
        'partially_returned' => 'Retorno parcial',
        'returned' => 'Retornado',
        'cancelled' => 'Anulado',
        'closed' => 'Cerrado',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function external_work_item_status_label(?string $status): string
{
    return match ((string) $status) {
        'pending' => 'Pendiente',
        'sent' => 'Enviado',
        'partially_returned' => 'Retorno parcial',
        'returned' => 'Retornado',
        'rejected' => 'Rechazado',
        'cancelled' => 'Anulado',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function external_work_receipt_status_label(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Borrador',
        'confirmed' => 'Confirmada',
        'cancelled' => 'Anulada',
        'closed' => 'Cerrada',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function external_work_type_label(?string $type): string
{
    return match ((string) $type) {
        'embroidery' => 'Bordado',
        'screen_printing' => 'Serigrafía',
        'both' => 'Serigrafía y bordado',
        'other' => 'Otro',
        default => ucfirst(str_replace('_', ' ', (string) $type)),
    };
}

function external_next_stage_label(?string $stage): string
{
    return match ((string) $stage) {
        'sewing' => 'Confección',
        'quality_control' => 'Control de calidad',
        default => ucfirst(str_replace('_', ' ', (string) $stage)),
    };
}

function stock_check_status_label(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Borrador',
        'sufficient' => 'Suficiente',
        'insufficient' => 'Insuficiente',
        'reserved' => 'Reservado',
        'cancelled' => 'Anulado',
        'closed' => 'Cerrado',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function supplier_status_label(?string $status): string
{
    return match ((string) $status) {
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function purchase_requisition_status_label(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Borrador',
        'requested' => 'Solicitado',
        'quoted' => 'Con presupuestos',
        'approved' => 'Aprobado',
        'cancelled' => 'Anulado',
        'closed' => 'Cerrado',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function supplier_quote_status_label(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Borrador',
        'received' => 'Recibido',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'cancelled' => 'Anulado',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function supplier_purchase_order_status_label(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Borrador',
        'confirmed' => 'Confirmada',
        'sent' => 'Enviada',
        'partially_received' => 'Recepción parcial',
        'received' => 'Recibida',
        'cancelled' => 'Anulada',
        'closed' => 'Cerrada',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function goods_receipt_status_label(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'Borrador',
        'confirmed' => 'Confirmada',
        'cancelled' => 'Anulada',
        'closed' => 'Cerrada',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

function goods_receipt_quality_status_label(?string $status): string
{
    return match ((string) $status) {
        'pending' => 'Pendiente',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
        default => ucfirst(str_replace('_', ' ', (string) $status)),
    };
}

/**
 * @return array<int, array<string, mixed>>
 */
function document_audit_entries(string $type, int $id, int $limit = 20): array
{
    return (new AuditLogRepository())->recentForDocument($type, $id, $limit);
}

function licitacion_statuses(): array
{
    return [
        'draft' => 'Borrador',
        'active' => 'En preparación',
        'submitted' => 'Presentada',
        'awarded' => 'Adjudicada',
        'cancelled' => 'Cancelada',
    ];
}

function licitacion_status_label(?string $status): string
{
    $statuses = licitacion_statuses();
    return $statuses[(string) $status] ?? ucfirst(str_replace('_', ' ', (string) $status));
}

function licitacion_status_badge_class(?string $status): string
{
    return match ((string) $status) {
        'draft' => 'badge badge--draft',
        'active', 'submitted', 'awarded' => 'badge badge--confirmed',
        'cancelled' => 'badge badge--cancelled',
        default => 'badge',
    };
}

function money(mixed $amount): string
{
    return number_format((float) $amount, 2, ',', '.');
}

function file_size_label(mixed $bytes): string
{
    $bytes = max(0, (int) $bytes);

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    }

    return $bytes . ' B';
}

function app_url(string $path = ''): string
{
    return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
}
