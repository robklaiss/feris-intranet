<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use App\Support\Request;
use App\Services\NumberingService;
use Throwable;

final class SettingsController extends Controller
{
    public function index(Request $request)
    {
        (new NumberingService())->ensureDefaults();
        $numerators = Database::connection()->query('SELECT * FROM numerators ORDER BY module')->fetchAll() ?: [];

        return $this->render('settings/index', [
            'numerators' => $numerators,
        ]);
    }

    public function update(Request $request)
    {
        $service = new NumberingService();

        try {
            foreach (($request->input('module') ?? []) as $index => $module) {
                if (!is_string($module) || trim($module) === '') {
                    continue;
                }

                $service->update($module, [
                    'prefix' => $request->input('prefix')[$index] ?? '',
                    'current_value' => $request->input('current_value')[$index] ?? 0,
                    'padding' => $request->input('padding')[$index] ?? 6,
                ]);
            }

            return $this->redirectWithMessage('/settings', 'Numeradores actualizados.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/settings', 'No se pudieron actualizar los numeradores: ' . $exception->getMessage(), 'error');
        }
    }
}
