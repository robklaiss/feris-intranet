<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use RuntimeException;

final class DocumentWorkflowService
{
    private const STATUS_LABELS = [
        'draft' => 'Borrador',
        'confirmed' => 'Confirmado',
        'cancelled' => 'Anulado',
        'closed' => 'Cerrado',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const TYPES = [
        'contracts' => [
            'label' => 'Contrato',
            'table' => 'contracts',
            'number_field' => 'contract_number',
            'show_path' => '/contracts/%d',
            'child_sql' => 'SELECT COUNT(*) AS total FROM purchase_orders WHERE contract_id = :id',
            'child_label' => 'órdenes de compra',
        ],
        'purchase_orders' => [
            'label' => 'Orden de compra',
            'table' => 'purchase_orders',
            'number_field' => 'order_number',
            'show_path' => '/purchase-orders/%d',
            'child_sql' => 'SELECT COUNT(*) AS total FROM delivery_note_source_orders WHERE purchase_order_id = :id',
            'child_label' => 'notas internas',
        ],
        'delivery_notes' => [
            'label' => 'Nota interna',
            'table' => 'delivery_notes',
            'number_field' => 'note_number',
            'show_path' => '/delivery-notes/%d',
            'child_sql' => 'SELECT COUNT(*) AS total FROM remission_source_notes WHERE delivery_note_id = :id',
            'child_label' => 'remisiones',
        ],
        'remissions' => [
            'label' => 'Remisión',
            'table' => 'remissions',
            'number_field' => 'remission_number',
            'show_path' => '/remissions/%d',
            'child_sql' => 'SELECT COUNT(*) AS total FROM invoice_source_remissions WHERE remission_id = :id',
            'child_label' => 'facturas',
        ],
        'invoices' => [
            'label' => 'Factura',
            'table' => 'invoices',
            'number_field' => 'invoice_number',
            'show_path' => '/invoices/%d',
            'child_sql' => 'SELECT 0 AS total',
            'child_label' => 'documentos posteriores',
        ],
    ];

    public function __construct(
        private readonly BalanceService $balances = new BalanceService(),
        private readonly OperationalAuditService $audit = new OperationalAuditService()
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function statuses(): array
    {
        return self::STATUS_LABELS;
    }

    public function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[(string) $status] ?? ucfirst((string) $status);
    }

    public function typeLabel(string $type): string
    {
        return $this->config($type)['label'];
    }

    public function showPath(string $type, int $id): string
    {
        return sprintf($this->config($type)['show_path'], $id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $type, int $id): ?array
    {
        $config = $this->config($type);
        $statement = Database::connection()->prepare(
            sprintf('SELECT * FROM %s WHERE id = :id LIMIT 1', $config['table'])
        );
        $statement->execute(['id' => $id]);
        $document = $statement->fetch();

        if (!$document) {
            return null;
        }

        $document['status'] = $this->normalizeStatus((string) ($document['status'] ?? 'draft'));
        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(string $type, int $id): array
    {
        $document = $this->find($type, $id);

        if (!$document) {
            throw new RuntimeException('Documento no encontrado.');
        }

        $locks = $this->destructiveLock($type, $id);
        $canClose = $this->canClose($type, $id);

        return [
            'document' => $document,
            'locks' => $locks,
            'can_edit' => $document['status'] === 'draft',
            'can_confirm' => $document['status'] === 'draft',
            'can_cancel' => in_array($document['status'], ['draft', 'confirmed'], true) && !$locks['locked'],
            'can_reopen' => in_array($document['status'], ['confirmed', 'cancelled', 'closed'], true) && !$locks['locked'],
            'can_close' => $document['status'] === 'confirmed' && $canClose['allowed'],
            'close_reason' => $canClose['reason'],
            'status_label' => $this->statusLabel((string) $document['status']),
        ];
    }

    public function assertEditable(string $type, int $id): ?string
    {
        $document = $this->find($type, $id);

        if (!$document) {
            return 'Documento no encontrado.';
        }

        if ((string) $document['status'] !== 'draft') {
            return sprintf(
                '%s %s está en estado %s. Reabrí a borrador antes de editar.',
                $this->typeLabel($type),
                $document[$this->config($type)['number_field']],
                strtolower($this->statusLabel((string) $document['status']))
            );
        }

        return null;
    }

    /**
     * @return array{locked: bool, count: int, reason: string|null}
     */
    public function destructiveLock(string $type, int $id): array
    {
        $config = $this->config($type);
        $statement = Database::connection()->prepare($config['child_sql']);
        $params = str_contains($config['child_sql'], ':id') ? ['id' => $id] : [];
        $statement->execute($params);
        $total = (int) (($statement->fetch()['total'] ?? 0));

        if ($total <= 0) {
            return ['locked' => false, 'count' => 0, 'reason' => null];
        }

        return [
            'locked' => true,
            'count' => $total,
            'reason' => sprintf('Ya fue consumido por %d %s.', $total, $config['child_label']),
        ];
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function canClose(string $type, int $id): array
    {
        return match ($type) {
            'contracts' => $this->closeFromBalances($this->balances->contractItemBalances($id), 'El contrato todavía tiene saldo pendiente.'),
            'purchase_orders' => $this->closeFromBalances($this->balances->purchaseOrderItemBalances($id), 'La orden todavía tiene saldo pendiente de nota interna.'),
            'delivery_notes' => $this->closeFromBalances($this->balances->deliveryNoteItemBalancesForNotes([$id]), 'La nota todavía tiene saldo pendiente de remisión.'),
            'remissions' => $this->closeFromBalances($this->balances->remissionItemBalances([$id]), 'La remisión todavía tiene saldo pendiente de factura.'),
            'invoices' => ['allowed' => true, 'reason' => null],
            default => ['allowed' => false, 'reason' => 'Tipo de documento no soportado.'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function transition(string $type, int $id, string $action): array
    {
        $document = $this->find($type, $id);

        if (!$document) {
            throw new RuntimeException('Documento no encontrado.');
        }

        $currentStatus = (string) $document['status'];
        $targetStatus = match ($action) {
            'confirm' => 'confirmed',
            'cancel' => 'cancelled',
            'reopen' => 'draft',
            'close' => 'closed',
            default => throw new RuntimeException('Acción no soportada.'),
        };

        $message = $this->validateTransition($type, $document, $action, $targetStatus);
        if ($message !== null) {
            throw new RuntimeException($message);
        }

        $config = $this->config($type);
        $statement = Database::connection()->prepare(
            sprintf('UPDATE %s SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id', $config['table'])
        );
        $statement->execute([
            'id' => $id,
            'status' => $targetStatus,
        ]);

        $number = (string) ($document[$config['number_field']] ?? ('#' . $id));
        $this->audit->logDocumentAction(
            $type,
            $id,
            $number,
            $action,
            $currentStatus,
            $targetStatus,
            [
                'document_number' => $number,
                'action' => $action,
            ]
        );

        $document['status'] = $targetStatus;

        return $document;
    }

    public function normalizeStatus(string $status): string
    {
        return match ($status) {
            'active', 'vigente', 'approved', 'issued', 'partial', 'confirmed' => 'confirmed',
            'cancelled', 'anulado' => 'cancelled',
            'closed', 'cerrado' => 'closed',
            default => 'draft',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $balances
     * @return array{allowed: bool, reason: string|null}
     */
    private function closeFromBalances(array $balances, string $reason): array
    {
        if ($balances === []) {
            return ['allowed' => false, 'reason' => 'No hay items para cerrar.'];
        }

        foreach ($balances as $balance) {
            if ((float) ($balance['remaining_quantity'] ?? 0) > 0.0001) {
                return ['allowed' => false, 'reason' => $reason];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function validateTransition(string $type, array $document, string $action, string $targetStatus): ?string
    {
        $status = (string) $document['status'];
        $locks = $this->destructiveLock($type, (int) $document['id']);

        return match ($action) {
            'confirm' => $status !== 'draft' ? 'Solo los borradores pueden confirmarse.' : null,
            'cancel' => !in_array($status, ['draft', 'confirmed'], true)
                ? 'Solo se puede anular un borrador o confirmado.'
                : ($locks['locked'] ? $locks['reason'] : null),
            'reopen' => !in_array($status, ['confirmed', 'cancelled', 'closed'], true)
                ? 'Solo se puede reabrir un documento confirmado, anulado o cerrado.'
                : ($locks['locked'] ? $locks['reason'] : null),
            'close' => $status !== 'confirmed'
                ? 'Solo un documento confirmado puede cerrarse.'
                : $this->canClose($type, (int) $document['id'])['reason'],
            default => 'Acción no soportada.',
        };
    }

    /**
     * @return array<string, string>
     */
    private function config(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException('Tipo de documento no soportado: ' . $type);
        }

        return self::TYPES[$type];
    }
}
