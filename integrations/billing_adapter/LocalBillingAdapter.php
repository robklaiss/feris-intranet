<?php

declare(strict_types=1);

namespace Integrations\BillingAdapter;

use App\Repositories\InvoiceRepository;

final class LocalBillingAdapter implements BillingAdapterInterface
{
    public function createInvoicePayload(int $invoiceId): array
    {
        $invoice = (new InvoiceRepository())->findWithItems($invoiceId);

        return [
            'mode' => 'local',
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoice['invoice_number'] ?? null,
            'total_amount' => $invoice['total_amount'] ?? 0,
            'items' => $invoice['items'] ?? [],
            'generated_at' => date(DATE_ATOM),
        ];
    }

    public function validateInvoiceForSifen(int $invoiceId): array
    {
        $payload = $this->createInvoicePayload($invoiceId);
        $errors = [];

        if (empty($payload['invoice_number'])) {
            $errors[] = 'La factura no tiene numeración.';
        }

        if (empty($payload['items'])) {
            $errors[] = 'La factura no tiene items.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'payload_preview' => $payload,
        ];
    }

    public function sendInvoice(int $invoiceId): array
    {
        $validation = $this->validateInvoiceForSifen($invoiceId);

        if (!$validation['valid']) {
            return [
                'status' => 'validation_error',
                'errors' => $validation['errors'],
                'simulated' => true,
            ];
        }

        return [
            'status' => 'simulated_sent',
            'simulated' => true,
            'tracking_id' => 'LOCAL-' . $invoiceId . '-' . date('YmdHis'),
            'payload' => $validation['payload_preview'],
        ];
    }
}

