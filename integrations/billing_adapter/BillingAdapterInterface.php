<?php

declare(strict_types=1);

namespace Integrations\BillingAdapter;

interface BillingAdapterInterface
{
    /**
     * @return array<string, mixed>
     */
    public function createInvoicePayload(int $invoiceId): array;

    /**
     * @return array<string, mixed>
     */
    public function validateInvoiceForSifen(int $invoiceId): array;

    /**
     * @return array<string, mixed>
     */
    public function sendInvoice(int $invoiceId): array;
}

