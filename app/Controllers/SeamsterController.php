<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SeamsterRepository;
use App\Support\Request;
use Throwable;

final class SeamsterController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
        ];

        return $this->render('seamsters/index', [
            'seamsters' => (new SeamsterRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('seamsters/form', [
            'title' => 'Nuevo costurero',
            'action' => '/seamsters',
            'seamster' => [
                'name' => '',
                'document_number' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
                'status' => 'active',
                'notes' => '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        try {
            $id = (new SeamsterRepository())->create($request->only([
                'name',
                'document_number',
                'phone',
                'email',
                'address',
                'status',
                'notes',
            ]));

            return $this->redirectWithMessage('/seamsters/' . $id, 'Costurero creado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/seamsters/create', 'No se pudo crear el costurero: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $seamster = (new SeamsterRepository())->find((int) $id);
        if (!$seamster) {
            return $this->redirectWithMessage('/seamsters', 'Costurero no encontrado.', 'error');
        }

        return $this->render('seamsters/show', ['seamster' => $seamster]);
    }

    public function edit(Request $request, string $id)
    {
        $seamster = (new SeamsterRepository())->find((int) $id);
        if (!$seamster) {
            return $this->redirectWithMessage('/seamsters', 'Costurero no encontrado.', 'error');
        }

        return $this->render('seamsters/form', [
            'title' => 'Editar costurero',
            'action' => '/seamsters/' . (int) $id . '/update',
            'seamster' => $seamster,
        ]);
    }

    public function update(Request $request, string $id)
    {
        try {
            (new SeamsterRepository())->update((int) $id, $request->only([
                'name',
                'document_number',
                'phone',
                'email',
                'address',
                'status',
                'notes',
            ]));

            return $this->redirectWithMessage('/seamsters/' . (int) $id, 'Costurero actualizado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/seamsters/' . (int) $id . '/edit', 'No se pudo actualizar el costurero: ' . $exception->getMessage(), 'error');
        }
    }
}
