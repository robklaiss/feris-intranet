<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\LicitacionRepository;
use App\Services\LicitacionFileStorageService;
use App\Services\OperationalAuditService;
use App\Support\Request;
use App\Support\Response;
use Throwable;

final class LicitacionController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
        ];

        return $this->render('licitaciones/index', [
            'licitaciones' => (new LicitacionRepository())->search($filters),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $id)
    {
        $licitacion = (new LicitacionRepository())->find((int) $id);

        if (!$licitacion) {
            return $this->redirectWithMessage('/licitaciones', 'Licitación no encontrada.', 'error');
        }

        return $this->render('licitaciones/show', [
            'licitacion' => $licitacion,
            'mainFiles' => $this->filterFilesByCategory($licitacion['files'], 'pliego'),
            'supportFiles' => $this->filterFilesByCategory($licitacion['files'], 'adjunto'),
        ]);
    }

    public function create(Request $request)
    {
        return $this->render('licitaciones/form', [
            'licitacion' => $this->emptyLicitacion(),
            'action' => '/licitaciones',
            'title' => 'Nueva licitación',
        ]);
    }

    public function store(Request $request)
    {
        [$data, $checklists, $errors] = $this->extractPayload($request);
        $storage = new LicitacionFileStorageService();
        $storedFiles = [];

        $mainFile = $request->file('pliego_document');
        if ($mainFile === null) {
            $errors[] = 'Adjuntá el llamado a licitación o el pliego de bases y condiciones.';
        }

        if ($errors !== []) {
            return $this->redirectWithMessage('/licitaciones/create', implode(' ', $errors), 'error');
        }

        try {
            if ($mainFile !== null) {
                $storedFiles[] = $storage->store($mainFile, 'pliego');
            }

            foreach ($request->files('support_documents') as $uploadedFile) {
                $storedFiles[] = $storage->store($uploadedFile, 'adjunto');
            }

            $repository = new LicitacionRepository();
            $id = $repository->create($data, $checklists, $storedFiles);

            (new OperationalAuditService())->logDocumentAction(
                'licitaciones',
                $id,
                (string) $data['call_number'],
                'created',
                null,
                (string) $data['status'],
                [
                    'licitacion' => $data,
                    'checklists' => $checklists,
                    'files' => array_map(
                        static fn (array $file): array => [
                            'category' => $file['category'],
                            'original_name' => $file['original_name'],
                        ],
                        $storedFiles
                    ),
                ]
            );

            return $this->redirectWithMessage('/licitaciones/' . $id, 'Licitación creada correctamente.');
        } catch (Throwable $exception) {
            foreach ($storedFiles as $file) {
                $storage->delete((string) ($file['stored_name'] ?? ''));
            }

            return $this->redirectWithMessage('/licitaciones/create', 'No se pudo crear la licitación: ' . $exception->getMessage(), 'error');
        }
    }

    public function edit(Request $request, string $id)
    {
        $licitacion = (new LicitacionRepository())->find((int) $id);

        if (!$licitacion) {
            return $this->redirectWithMessage('/licitaciones', 'Licitación no encontrada.', 'error');
        }

        if ($licitacion['checklists'] === []) {
            $licitacion['checklists'] = [['label' => '', 'is_attached' => 0]];
        }

        return $this->render('licitaciones/form', [
            'licitacion' => $licitacion,
            'action' => '/licitaciones/' . $id . '/update',
            'title' => 'Editar licitación',
        ]);
    }

    public function update(Request $request, string $id)
    {
        $repository = new LicitacionRepository();
        $licitacion = $repository->find((int) $id);

        if (!$licitacion) {
            return $this->redirectWithMessage('/licitaciones', 'Licitación no encontrada.', 'error');
        }

        [$data, $checklists, $errors] = $this->extractPayload($request);
        $storage = new LicitacionFileStorageService();
        $storedFiles = [];

        $deleteFileIds = $this->parseFileIds($request->input('delete_file_ids', []));
        $mainFile = $request->file('pliego_document');
        $currentMainFiles = $this->filterFilesByCategory($licitacion['files'], 'pliego');
        $currentMainFileIds = array_map(static fn (array $file): int => (int) $file['id'], $currentMainFiles);

        if ($mainFile !== null) {
            $deleteFileIds = array_values(array_unique(array_merge($deleteFileIds, $currentMainFileIds)));
        }

        $remainingMainFiles = array_diff($currentMainFileIds, $deleteFileIds);
        if ($mainFile === null && $remainingMainFiles === []) {
            $errors[] = 'La licitación debe conservar al menos un llamado o pliego adjunto.';
        }

        if ($errors !== []) {
            return $this->redirectWithMessage('/licitaciones/' . $id . '/edit', implode(' ', $errors), 'error');
        }

        $filesToDelete = array_values(array_filter(
            $licitacion['files'],
            static fn (array $file): bool => in_array((int) $file['id'], $deleteFileIds, true)
        ));

        try {
            if ($mainFile !== null) {
                $storedFiles[] = $storage->store($mainFile, 'pliego');
            }

            foreach ($request->files('support_documents') as $uploadedFile) {
                $storedFiles[] = $storage->store($uploadedFile, 'adjunto');
            }

            $repository->update((int) $id, $data, $checklists, $storedFiles, $deleteFileIds);

            foreach ($filesToDelete as $file) {
                $storage->delete((string) ($file['stored_name'] ?? ''));
            }

            (new OperationalAuditService())->logDocumentAction(
                'licitaciones',
                (int) $id,
                (string) $data['call_number'],
                'updated',
                (string) $licitacion['status'],
                (string) $data['status'],
                [
                    'licitacion' => $data,
                    'checklists' => $checklists,
                    'new_files' => array_map(
                        static fn (array $file): array => [
                            'category' => $file['category'],
                            'original_name' => $file['original_name'],
                        ],
                        $storedFiles
                    ),
                    'deleted_file_ids' => $deleteFileIds,
                ]
            );

            return $this->redirectWithMessage('/licitaciones/' . $id, 'Licitación actualizada correctamente.');
        } catch (Throwable $exception) {
            foreach ($storedFiles as $file) {
                $storage->delete((string) ($file['stored_name'] ?? ''));
            }

            return $this->redirectWithMessage('/licitaciones/' . $id . '/edit', 'No se pudo actualizar la licitación: ' . $exception->getMessage(), 'error');
        }
    }

    public function delete(Request $request, string $id)
    {
        $repository = new LicitacionRepository();
        $licitacion = $repository->find((int) $id);

        if (!$licitacion) {
            return $this->redirectWithMessage('/licitaciones', 'Licitación no encontrada.', 'error');
        }

        $repository->delete((int) $id);

        $storage = new LicitacionFileStorageService();
        foreach ($licitacion['files'] as $file) {
            $storage->delete((string) ($file['stored_name'] ?? ''));
        }

        (new OperationalAuditService())->logDocumentAction(
            'licitaciones',
            (int) $id,
            (string) $licitacion['call_number'],
            'deleted',
            (string) $licitacion['status'],
            null,
            ['licitacion' => $licitacion]
        );

        return $this->redirectWithMessage('/licitaciones', 'Licitación eliminada.');
    }

    public function downloadFile(Request $request, string $licitacionId, string $fileId)
    {
        $repository = new LicitacionRepository();
        $licitacion = $repository->find((int) $licitacionId);

        if (!$licitacion) {
            return $this->redirectWithMessage('/licitaciones', 'Licitación no encontrada.', 'error');
        }

        $file = $repository->findFile((int) $licitacionId, (int) $fileId);
        if (!$file) {
            return $this->redirectWithMessage('/licitaciones/' . $licitacionId, 'Archivo no encontrado.', 'error');
        }

        $storage = new LicitacionFileStorageService();
        $path = $storage->path((string) $file['stored_name']);

        if (!is_file($path)) {
            return $this->redirectWithMessage('/licitaciones/' . $licitacionId, 'El archivo no está disponible en disco.', 'error');
        }

        (new OperationalAuditService())->logDocumentAction(
            'licitaciones',
            (int) $licitacionId,
            (string) $licitacion['call_number'],
            'downloaded',
            (string) $licitacion['status'],
            (string) $licitacion['status'],
            [
                'file' => [
                    'id' => (int) $file['id'],
                    'category' => $file['category'],
                    'original_name' => $file['original_name'],
                ],
            ]
        );

        return Response::make((string) file_get_contents($path), 200, [
            'Content-Type' => (string) ($file['mime_type'] ?: 'application/octet-stream'),
            'Content-Disposition' => 'attachment; filename="' . rawurlencode((string) $file['original_name']) . '"',
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: array<int, string>}
     */
    private function extractPayload(Request $request): array
    {
        $data = $request->only([
            'call_number',
            'title',
            'institution',
            'publish_date',
            'opening_date',
            'status',
            'notes',
        ]);

        $data = array_map(
            static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            $data
        );
        $data['status'] = (string) ($data['status'] ?: 'draft');

        $errors = [];

        if ($data['call_number'] === '') {
            $errors[] = 'Indicá el número del llamado.';
        }

        if ($data['title'] === '') {
            $errors[] = 'Indicá el nombre de la licitación.';
        }

        if ($data['institution'] === '') {
            $errors[] = 'Indicá la entidad convocante.';
        }

        if (!array_key_exists($data['status'], licitacion_statuses())) {
            $errors[] = 'Estado de licitación inválido.';
        }

        if ($data['publish_date'] !== '' && $data['opening_date'] !== '' && $data['opening_date'] < $data['publish_date']) {
            $errors[] = 'La fecha de apertura no puede ser anterior a la fecha de publicación.';
        }

        return [$data, $this->extractChecklistItems($request), $errors];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractChecklistItems(Request $request): array
    {
        $labels = $request->input('checklist_label', []);
        $markers = $request->input('checklist_attached', []);

        if (!is_array($labels)) {
            return [];
        }

        $markers = is_array($markers) ? array_values($markers) : [];
        $pointer = 0;
        $items = [];

        foreach ($labels as $label) {
            $attached = false;

            if (($markers[$pointer] ?? null) === '0') {
                $pointer++;
                if (($markers[$pointer] ?? null) === '1') {
                    $attached = true;
                    $pointer++;
                }
            } elseif (($markers[$pointer] ?? null) === '1') {
                $attached = true;
                $pointer++;
            }

            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }

            $items[] = [
                'label' => $label,
                'is_attached' => $attached ? 1 : 0,
            ];
        }

        return $items;
    }

    /**
     * @param mixed $input
     * @return array<int, int>
     */
    private function parseFileIds(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $ids = [];

        foreach ($input as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @return array<int, array<string, mixed>>
     */
    private function filterFilesByCategory(array $files, string $category): array
    {
        return array_values(array_filter(
            $files,
            static fn (array $file): bool => (string) ($file['category'] ?? '') === $category
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLicitacion(): array
    {
        return [
            'call_number' => '',
            'title' => '',
            'institution' => '',
            'publish_date' => '',
            'opening_date' => '',
            'status' => 'draft',
            'notes' => '',
            'checklists' => [['label' => '', 'is_attached' => 0]],
            'files' => [],
        ];
    }
}
