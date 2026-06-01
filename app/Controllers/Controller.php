<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Response;
use App\Support\Session;

abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], string $layout = 'layouts/app'): Response
    {
        return view($template, $data, $layout);
    }

    protected function redirectWithMessage(string $path, string $message, string $type = 'success'): Response
    {
        Session::flash($type, $message);
        return redirect($path);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, int $status = 200): Response
    {
        return Response::make(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    protected function flashInput(array $input): void
    {
        foreach ($input as $key => $value) {
            Session::flash('_old.' . $key, $value);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $fields
     * @return array<int, array<string, mixed>>
     */
    protected function collectLineItems(array $payload, array $fields): array
    {
        $rows = 0;

        foreach ($fields as $field) {
            $values = $payload[$field] ?? [];
            if (is_array($values)) {
                $rows = max($rows, count($values));
            }
        }

        $items = [];

        for ($index = 0; $index < $rows; $index++) {
            $item = [];

            foreach ($fields as $field) {
                $value = $payload[$field][$index] ?? null;
                $item[$field] = is_string($value) ? trim($value) : $value;
            }

            $product = trim((string) ($item['product_name'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($product === '' && $quantity <= 0) {
                continue;
            }

            $item['quantity'] = $quantity;
            $item['unit_price'] = (float) ($item['unit_price'] ?? 0);
            $item['total_item'] = $quantity * $item['unit_price'];
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function sumItems(array $items): float
    {
        return array_reduce(
            $items,
            static fn (float $carry, array $item): float => $carry + (float) ($item['total_item'] ?? 0),
            0.0
        );
    }
}
