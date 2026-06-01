<?php

declare(strict_types=1);

namespace App\Services;

final class OverconsumptionValidatorService
{
    public function validateAgainstBalances(array $balances, array $requested, string $key): array
    {
        $index = [];

        foreach ($balances as $balance) {
            $index[(int) $balance['id']] = $balance;
        }

        $errors = [];

        foreach ($requested as $item) {
            $sourceId = (int) ($item[$key] ?? 0);
            if (!$sourceId || !isset($index[$sourceId])) {
                continue;
            }

            $remaining = (float) $index[$sourceId]['remaining_quantity'];
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($quantity > $remaining + 0.0001) {
                $errors[] = sprintf(
                    'El item "%s" excede el saldo disponible. Disponible: %s, solicitado: %s.',
                    $index[$sourceId]['product_name'],
                    rtrim(rtrim(number_format($remaining, 4, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.')
                );
            }
        }

        return $errors;
    }
}

