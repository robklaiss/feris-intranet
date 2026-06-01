<?php

declare(strict_types=1);

use App\Repositories\LicitacionRepository;

$repository = new LicitacionRepository();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$callNumber = 'LIC-TEST-' . bin2hex(random_bytes(4));
$licitacionId = null;

try {
    $licitacionId = $repository->create(
        [
            'call_number' => $callNumber,
            'title' => 'Licitacion de prueba',
            'institution' => 'Industria Feris',
            'publish_date' => '2026-03-08',
            'opening_date' => '2026-03-12',
            'status' => 'active',
            'notes' => 'Carga inicial',
        ],
        [
            ['label' => 'RUC vigente', 'is_attached' => 1],
            ['label' => 'Oferta economica', 'is_attached' => 0],
        ],
        [
            [
                'category' => 'pliego',
                'original_name' => 'pliego.pdf',
                'stored_name' => 'pliego-test.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
            ],
            [
                'category' => 'adjunto',
                'original_name' => 'anexo.xlsx',
                'stored_name' => 'anexo-test.xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'size_bytes' => 2048,
            ],
        ]
    );

    $created = $repository->find($licitacionId);
    $assert($created !== null, 'La licitacion creada debe existir.');
    $assert(count($created['checklists']) === 2, 'La licitacion debe guardar el checklist documental.');
    $assert(count($created['files']) === 2, 'La licitacion debe guardar los adjuntos.');

    $search = $repository->search(['q' => $callNumber]);
    $assert($search !== [], 'La busqueda debe encontrar la licitacion.');
    $assert((int) $search[0]['checklist_total'] === 2, 'La grilla debe exponer el total del checklist.');
    $assert((int) $search[0]['checklist_attached'] === 1, 'La grilla debe exponer el avance documental.');

    $mainFile = current(array_filter(
        $created['files'],
        static fn (array $file): bool => (string) ($file['category'] ?? '') === 'pliego'
    ));
    $deleteFileIds = [(int) ($mainFile['id'] ?? 0)];

    $repository->update(
        $licitacionId,
        [
            'call_number' => $callNumber,
            'title' => 'Licitacion actualizada',
            'institution' => 'Industria Feris',
            'publish_date' => '2026-03-08',
            'opening_date' => '2026-03-15',
            'status' => 'submitted',
            'notes' => 'Carga actualizada',
        ],
        [
            ['label' => 'RUC vigente', 'is_attached' => 1],
            ['label' => 'Balance general', 'is_attached' => 1],
        ],
        [
            [
                'category' => 'pliego',
                'original_name' => 'pliego-v2.pdf',
                'stored_name' => 'pliego-test-v2.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 4096,
            ],
        ],
        $deleteFileIds
    );

    $updated = $repository->find($licitacionId);
    $assert($updated !== null, 'La licitacion actualizada debe existir.');
    $assert($updated['title'] === 'Licitacion actualizada', 'La licitacion debe actualizar sus datos principales.');
    $assert(count($updated['checklists']) === 2, 'La actualizacion debe reemplazar el checklist.');
    $assert(count($updated['files']) === 2, 'La actualizacion debe borrar y agregar archivos segun corresponda.');
    $assert((string) $updated['files'][0]['original_name'] !== 'pliego.pdf', 'El pliego viejo debe eliminarse del registro.');
} finally {
    if (is_int($licitacionId)) {
        $repository->delete($licitacionId);
    }
}

return true;
