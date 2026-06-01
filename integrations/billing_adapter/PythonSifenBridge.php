<?php

declare(strict_types=1);

namespace Integrations\BillingAdapter;

final class PythonSifenBridge implements BillingAdapterInterface
{
    public function createInvoicePayload(int $invoiceId): array
    {
        return [
            'ready' => false,
            'message' => 'Puente Python/SIFEN pendiente de implementación.',
            'invoice_id' => $invoiceId,
        ];
    }

    public function validateInvoiceForSifen(int $invoiceId): array
    {
        return [
            'ready' => false,
            'message' => 'Validación SIFEN real aún no integrada.',
            'invoice_id' => $invoiceId,
        ];
    }

    public function sendInvoice(int $invoiceId): array
    {
        return [
            'ready' => false,
            'status' => 'not_implemented',
            'message' => 'Este puente quedará conectado luego con sifen-minisender-3.',
            'invoice_id' => $invoiceId,
        ];
    }
}

