<?php

declare(strict_types=1);

use App\Services\OverconsumptionValidatorService;

$service = new OverconsumptionValidatorService();
$balances = [
    ['id' => 10, 'product_name' => 'Perfil', 'remaining_quantity' => 12],
    ['id' => 11, 'product_name' => 'Chapón', 'remaining_quantity' => 5],
];

$valid = $service->validateAgainstBalances($balances, [
    ['contract_item_id' => 10, 'quantity' => 10],
], 'contract_item_id');

$invalid = $service->validateAgainstBalances($balances, [
    ['contract_item_id' => 11, 'quantity' => 9],
], 'contract_item_id');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert($valid === [], 'No debería haber errores con cantidades dentro del saldo.');
$assert(count($invalid) === 1, 'Debe detectarse exactamente un error de sobreconsumo.');
$assert(str_contains($invalid[0], 'excede el saldo disponible'), 'El mensaje debe indicar sobreconsumo.');

return true;

