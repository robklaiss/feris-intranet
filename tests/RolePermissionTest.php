<?php

declare(strict_types=1);

use App\Support\Auth;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

Auth::logout();
$assert(Auth::attempt('admin', 'admin123'), 'El usuario admin demo debe autenticarse.');
$assert(Auth::can('settings.manage'), 'Admin debe poder administrar configuración.');
$assert(Auth::can('documents.export'), 'Admin debe poder exportar documentos.');

Auth::logout();
$assert(Auth::attempt('operador', 'operador123'), 'El usuario operador demo debe autenticarse.');
$assert(Auth::can('documents.create'), 'Operador debe poder crear documentos.');
$assert(Auth::can('documents.transition'), 'Operador debe poder confirmar/anular/reabrir.');
$assert(Auth::can('documents.export'), 'Operador debe poder exportar documentos.');
$assert(!Auth::can('settings.manage'), 'Operador no debe administrar configuración sensible.');

Auth::logout();
$assert(Auth::attempt('consulta', 'consulta123'), 'El usuario consulta demo debe autenticarse.');
$assert(Auth::can('reports.view'), 'Consulta debe poder ver reportes.');
$assert(Auth::can('documents.print'), 'Consulta debe poder imprimir.');
$assert(!Auth::can('documents.create'), 'Consulta no debe poder crear documentos.');
$assert(!Auth::can('documents.export'), 'Consulta no debe exportar documentos operativos.');
$assert(!Auth::can('documents.transition'), 'Consulta no debe poder cambiar estados.');

Auth::logout();

return true;
