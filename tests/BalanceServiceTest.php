<?php

declare(strict_types=1);

use App\Services\BalanceService;

$service = new BalanceService();
$contractBalances = $service->contractItemBalances(1);
$orderBalances = $service->purchaseOrderItemBalances(1);
$noteBalances = $service->deliveryNoteItemBalancesForNotes([1]);
$remissionBalances = $service->remissionItemBalances([1]);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(count($contractBalances) === 2, 'Deben existir 2 items de contrato en la seed.');
$assert((float) $contractBalances[0]['remaining_quantity'] === 850.0, 'Saldo incorrecto para item 1 de contrato.');
$assert((float) $orderBalances[1]['remaining_quantity'] === 105.0, 'Saldo incorrecto para item 2 de orden.');
$assert((float) $noteBalances[0]['remaining_quantity'] === 30.0, 'Saldo incorrecto para item 1 de nota.');
$assert((float) $remissionBalances[0]['remaining_quantity'] === 0.0, 'Saldo incorrecto para item 1 de remisión.');

return true;

