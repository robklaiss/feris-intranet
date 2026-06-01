<?php

declare(strict_types=1);

use App\Services\DocumentWorkflowService;
use App\Support\Database;

$db = Database::connection();
$db->beginTransaction();

try {
    $service = new DocumentWorkflowService();

    $confirm = $service->transition('invoices', 1, 'confirm');
    $close = $service->transition('invoices', 1, 'close');
    $reopen = $service->transition('invoices', 1, 'reopen');

    $closeContract = $service->canClose('contracts', 1);
    $editLock = $service->assertEditable('contracts', 1);

    $remissionError = null;
    try {
        $service->transition('remissions', 1, 'cancel');
    } catch (RuntimeException $exception) {
        $remissionError = $exception->getMessage();
    }

    $orderReopenError = null;
    try {
        $service->transition('purchase_orders', 1, 'reopen');
    } catch (RuntimeException $exception) {
        $orderReopenError = $exception->getMessage();
    }

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $assert($confirm['status'] === 'confirmed', 'La factura debe poder confirmarse desde borrador.');
    $assert($close['status'] === 'closed', 'La factura confirmada debe poder cerrarse.');
    $assert($reopen['status'] === 'draft', 'La factura cerrada debe poder reabrirse a borrador.');
    $assert($closeContract['allowed'] === false, 'El contrato demo no debe cerrarse con saldo pendiente.');
    $assert(is_string($editLock) && $editLock !== '', 'Un contrato confirmado debe quedar bloqueado para edición libre.');
    $assert(is_string($remissionError) && str_contains($remissionError, 'consumido'), 'No debe anularse una remisión ya consumida por factura.');
    $assert(is_string($orderReopenError) && str_contains($orderReopenError, 'consumido'), 'No debe reabrirse una orden ya consumida por notas.');
} finally {
    $db->rollBack();
}

return true;
