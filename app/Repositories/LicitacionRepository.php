<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class LicitacionRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $sql = 'SELECT
                licitaciones.*,
                COALESCE(checklist_stats.total, 0) AS checklist_total,
                COALESCE(checklist_stats.attached, 0) AS checklist_attached,
                COALESCE(file_stats.total, 0) AS file_total
            FROM licitaciones
            LEFT JOIN (
                SELECT
                    licitacion_id,
                    COUNT(*) AS total,
                    SUM(CASE WHEN is_attached = 1 THEN 1 ELSE 0 END) AS attached
                FROM licitacion_checklists
                GROUP BY licitacion_id
            ) AS checklist_stats ON checklist_stats.licitacion_id = licitaciones.id
            LEFT JOIN (
                SELECT licitacion_id, COUNT(*) AS total
                FROM licitacion_files
                GROUP BY licitacion_id
            ) AS file_stats ON file_stats.licitacion_id = licitaciones.id
            WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (
                licitaciones.call_number LIKE :q
                OR licitaciones.title LIKE :q
                OR licitaciones.institution LIKE :q
            )';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND licitaciones.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY COALESCE(licitaciones.opening_date, licitaciones.publish_date, licitaciones.created_at) DESC, licitaciones.id DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $licitacion = $this->fetchOne(
            'SELECT * FROM licitaciones WHERE id = :id',
            ['id' => $id]
        );

        if (!$licitacion) {
            return null;
        }

        $licitacion['checklists'] = $this->fetchAll(
            'SELECT * FROM licitacion_checklists WHERE licitacion_id = :licitacion_id ORDER BY sort_order, id',
            ['licitacion_id' => $id]
        );
        $licitacion['files'] = $this->fetchAll(
            'SELECT * FROM licitacion_files WHERE licitacion_id = :licitacion_id ORDER BY category, id',
            ['licitacion_id' => $id]
        );

        return $licitacion;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $checklists
     * @param array<int, array<string, mixed>> $files
     */
    public function create(array $data, array $checklists, array $files): int
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'INSERT INTO licitaciones (
                    call_number, title, institution, publish_date, opening_date, status, notes, updated_at
                ) VALUES (
                    :call_number, :title, :institution, :publish_date, :opening_date, :status, :notes, CURRENT_TIMESTAMP
                )',
                [
                    'call_number' => $data['call_number'],
                    'title' => $data['title'],
                    'institution' => $data['institution'],
                    'publish_date' => $data['publish_date'] ?: null,
                    'opening_date' => $data['opening_date'] ?: null,
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?: null,
                ]
            );

            $licitacionId = (int) $pdo->lastInsertId();
            $this->syncChecklists($licitacionId, $checklists);
            $this->syncFiles($licitacionId, $files);

            $pdo->commit();
            return $licitacionId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $checklists
     * @param array<int, array<string, mixed>> $newFiles
     * @param array<int, int> $deleteFileIds
     */
    public function update(int $id, array $data, array $checklists, array $newFiles, array $deleteFileIds = []): void
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        try {
            $this->execute(
                'UPDATE licitaciones SET
                    call_number = :call_number,
                    title = :title,
                    institution = :institution,
                    publish_date = :publish_date,
                    opening_date = :opening_date,
                    status = :status,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id',
                [
                    'id' => $id,
                    'call_number' => $data['call_number'],
                    'title' => $data['title'],
                    'institution' => $data['institution'],
                    'publish_date' => $data['publish_date'] ?: null,
                    'opening_date' => $data['opening_date'] ?: null,
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?: null,
                ]
            );

            $this->execute('DELETE FROM licitacion_checklists WHERE licitacion_id = :licitacion_id', ['licitacion_id' => $id]);
            $this->syncChecklists($id, $checklists);

            if ($deleteFileIds !== []) {
                $placeholders = [];
                $params = ['licitacion_id' => $id];

                foreach (array_values($deleteFileIds) as $index => $fileId) {
                    $placeholder = ':file_' . $index;
                    $placeholders[] = $placeholder;
                    $params['file_' . $index] = $fileId;
                }

                $this->execute(
                    'DELETE FROM licitacion_files WHERE licitacion_id = :licitacion_id AND id IN (' . implode(', ', $placeholders) . ')',
                    $params
                );
            }

            $this->syncFiles($id, $newFiles);

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM licitaciones WHERE id = :id', ['id' => $id]);
    }

    public function count(): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM licitaciones');
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findFile(int $licitacionId, int $fileId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM licitacion_files WHERE licitacion_id = :licitacion_id AND id = :id',
            ['licitacion_id' => $licitacionId, 'id' => $fileId]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $checklists
     */
    private function syncChecklists(int $licitacionId, array $checklists): void
    {
        foreach ($checklists as $index => $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $this->execute(
                'INSERT INTO licitacion_checklists (
                    licitacion_id, label, is_attached, sort_order, updated_at
                ) VALUES (
                    :licitacion_id, :label, :is_attached, :sort_order, CURRENT_TIMESTAMP
                )',
                [
                    'licitacion_id' => $licitacionId,
                    'label' => $label,
                    'is_attached' => !empty($item['is_attached']) ? 1 : 0,
                    'sort_order' => $index,
                ]
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function syncFiles(int $licitacionId, array $files): void
    {
        foreach ($files as $file) {
            $this->execute(
                'INSERT INTO licitacion_files (
                    licitacion_id, category, original_name, stored_name, mime_type, size_bytes
                ) VALUES (
                    :licitacion_id, :category, :original_name, :stored_name, :mime_type, :size_bytes
                )',
                [
                    'licitacion_id' => $licitacionId,
                    'category' => $file['category'] ?? 'adjunto',
                    'original_name' => $file['original_name'],
                    'stored_name' => $file['stored_name'],
                    'mime_type' => $file['mime_type'] ?? null,
                    'size_bytes' => (int) ($file['size_bytes'] ?? 0),
                ]
            );
        }
    }
}
