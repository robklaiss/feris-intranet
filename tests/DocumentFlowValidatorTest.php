<?php

declare(strict_types=1);

use App\Services\DocumentFlowValidatorService;

$service = new DocumentFlowValidatorService();

[$autoRemissionItems, $autoRemissionErrors] = $service->prepareRemissionItems([1], []);
[$invalidInvoiceItems, $invalidInvoiceErrors] = $service->prepareInvoiceItems([1], [
    ['remission_item_id' => 999999, 'quantity' => 1],
]);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(count($autoRemissionItems) >= 1, 'La remisión debe autoconstruir items desde las notas seleccionadas.');
$assert($autoRemissionErrors === [], 'No debería haber errores al autoconstruir una remisión válida.');
$assert($invalidInvoiceItems === [], 'Los items incoherentes de factura no deben normalizarse.');
$assert(count($invalidInvoiceErrors) >= 1, 'Debe rechazarse un item de factura que no proviene de la remisión seleccionada.');

return true;
