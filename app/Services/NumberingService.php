<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BaseRepository;
use App\Support\Database;
use Throwable;

final class NumberingService
{
    /**
     * @return array<string, array<string, int|string>>
     */
    public function defaults(): array
    {
        return [
            'purchase_orders' => ['prefix' => 'OC-', 'current_value' => 0, 'padding' => 6],
            'delivery_notes' => ['prefix' => 'NE-', 'current_value' => 0, 'padding' => 6],
            'remissions' => ['prefix' => 'REM-', 'current_value' => 0, 'padding' => 6],
            'invoices' => ['prefix' => 'FAC-', 'current_value' => 0, 'padding' => 6],
        ];
    }

    public function next(string $module): string
    {
        $this->ensureModule($module);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('SELECT * FROM numerators WHERE module = :module');
            $statement->execute(['module' => $module]);
            $numerator = $statement->fetch();

            $next = (int) $numerator['current_value'] + 1;

            $pdo->prepare(
                'UPDATE numerators SET current_value = :current_value, updated_at = CURRENT_TIMESTAMP WHERE module = :module'
            )->execute([
                'module' => $module,
                'current_value' => $next,
            ]);

            $pdo->commit();

            return (string) $numerator['prefix'] . str_pad((string) $next, (int) $numerator['padding'], '0', STR_PAD_LEFT);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function ensureDefaults(): void
    {
        foreach (array_keys($this->defaults()) as $module) {
            $this->ensureModule($module);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $module, array $data): void
    {
        $this->ensureModule($module);

        Database::connection()->prepare(
            'UPDATE numerators
             SET prefix = :prefix, current_value = :current_value, padding = :padding, updated_at = CURRENT_TIMESTAMP
             WHERE module = :module'
        )->execute([
            'module' => $module,
            'prefix' => trim((string) ($data['prefix'] ?? '')),
            'current_value' => (int) ($data['current_value'] ?? 0),
            'padding' => max(1, (int) ($data['padding'] ?? 6)),
        ]);
    }

    private function ensureModule(string $module): void
    {
        $statement = Database::connection()->prepare('SELECT module FROM numerators WHERE module = :module');
        $statement->execute(['module' => $module]);

        if ($statement->fetch()) {
            return;
        }

        $defaults = $this->defaults()[$module] ?? [
            'prefix' => strtoupper(substr($module, 0, 3)) . '-',
            'current_value' => 0,
            'padding' => 6,
        ];

        Database::connection()->prepare(
            'INSERT INTO numerators (module, prefix, current_value, padding, updated_at)
             VALUES (:module, :prefix, :current_value, :padding, CURRENT_TIMESTAMP)'
        )->execute([
            'module' => $module,
            'prefix' => $defaults['prefix'],
            'current_value' => $defaults['current_value'],
            'padding' => $defaults['padding'],
        ]);
    }
}
