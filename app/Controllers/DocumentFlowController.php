<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DocumentContextService;
use App\Support\Request;
use App\Support\Response;

final class DocumentFlowController extends Controller
{
    public function contractContext(Request $request, string $id): Response
    {
        return $this->json((new DocumentContextService())->purchaseOrderContext((int) $id));
    }

    public function deliveryNoteContext(Request $request): Response
    {
        $contractId = (int) $request->input('contract_id', 0);
        $orderIds = $this->parseIds($request->input('purchase_order_ids', ''));

        return $this->json((new DocumentContextService())->deliveryNoteContext($contractId ?: null, $orderIds));
    }

    public function remissionContext(Request $request): Response
    {
        $noteIds = $this->parseIds($request->input('delivery_note_ids', ''));
        return $this->json((new DocumentContextService())->remissionContext($noteIds));
    }

    public function invoiceContext(Request $request): Response
    {
        $remissionIds = $this->parseIds($request->input('remission_ids', ''));
        return $this->json((new DocumentContextService())->invoiceContext($remissionIds));
    }

    /**
     * @return array<int, int>
     */
    private function parseIds(mixed $raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $parts = array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $parts), static fn (string $value): bool => $value !== '');
        return array_values(array_unique(array_map('intval', $parts)));
    }
}
