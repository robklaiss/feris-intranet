<?php

declare(strict_types=1);

namespace App\Services;

final class DocumentFlowValidatorService
{
    public function __construct(
        private readonly DocumentContextService $contexts = new DocumentContextService(),
        private readonly OverconsumptionValidatorService $overconsumption = new OverconsumptionValidatorService()
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $submitted
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    public function preparePurchaseOrderItems(int $contractId, array $submitted): array
    {
        $context = $this->contexts->purchaseOrderContext($contractId);
        $items = $this->mergeWithContext($submitted, $context['items'], 'contract_item_id');
        $errors = array_merge(
            $this->assertKnownSources($submitted, $context['items'], 'contract_item_id', 'contrato'),
            $this->overconsumption->validateAgainstBalances($context['items'], $items, 'contract_item_id')
        );

        return [$items, $errors];
    }

    /**
     * @param array<int, int> $purchaseOrderIds
     * @param array<int, array<string, mixed>> $submitted
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<string, mixed>}
     */
    public function prepareDeliveryNoteItems(?int $contractId, array $purchaseOrderIds, array $submitted): array
    {
        $context = $this->contexts->deliveryNoteContext($contractId, $purchaseOrderIds);
        $errors = $this->assertConsistentDocuments($context['document']['orders'] ?? [], 'order_number', 'contract_id', 'client_id');
        $items = $this->mergeWithContext($submitted, $context['items'], 'purchase_order_item_id');
        $errors = array_merge(
            $errors,
            $this->assertKnownSources($submitted, $context['items'], 'purchase_order_item_id', 'orden seleccionada'),
            $this->overconsumption->validateAgainstBalances($context['items'], $items, 'purchase_order_item_id')
        );

        return [$items, $errors, $context];
    }

    /**
     * @param array<int, int> $deliveryNoteIds
     * @param array<int, array<string, mixed>> $submitted
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<string, mixed>}
     */
    public function prepareRemissionItems(array $deliveryNoteIds, array $submitted): array
    {
        $context = $this->contexts->remissionContext($deliveryNoteIds);
        $errors = $this->assertConsistentDocuments($context['document']['source_notes'] ?? [], 'note_number', 'contract_id', 'client_id');
        $items = $this->mergeWithContext($submitted, $context['items'], 'delivery_note_item_id', true);
        $errors = array_merge(
            $errors,
            $this->assertKnownSources($submitted, $context['items'], 'delivery_note_item_id', 'nota seleccionada'),
            $this->overconsumption->validateAgainstBalances($context['items'], $items, 'delivery_note_item_id')
        );

        return [$items, $errors, $context];
    }

    /**
     * @param array<int, int> $remissionIds
     * @param array<int, array<string, mixed>> $submitted
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<string, mixed>}
     */
    public function prepareInvoiceItems(array $remissionIds, array $submitted): array
    {
        $context = $this->contexts->invoiceContext($remissionIds);
        $errors = $this->assertConsistentDocuments($context['document']['source_remissions'] ?? [], 'remission_number', 'contract_id', 'client_id');
        $items = $this->mergeWithContext($submitted, $context['items'], 'remission_item_id', true);
        $errors = array_merge(
            $errors,
            $this->assertKnownSources($submitted, $context['items'], 'remission_item_id', 'remisión seleccionada'),
            $this->overconsumption->validateAgainstBalances($context['items'], $items, 'remission_item_id')
        );

        return [$items, $errors, $context];
    }

    /**
     * @param array<int, array<string, mixed>> $submitted
     * @param array<int, array<string, mixed>> $contextItems
     * @return array<int, array<string, mixed>>
     */
    private function mergeWithContext(array $submitted, array $contextItems, string $sourceKey, bool $autoBuildWhenEmpty = false): array
    {
        $index = [];
        foreach ($contextItems as $item) {
            $index[(int) $item['id']] = $item;
        }

        if ($submitted === [] && $autoBuildWhenEmpty) {
            $submitted = array_map(static function (array $item) use ($sourceKey): array {
                return [
                    $sourceKey => $item['id'],
                    'quantity' => $item['suggested_quantity'],
                ];
            }, $contextItems);
        }

        $normalized = [];

        foreach ($submitted as $item) {
            $sourceId = (int) ($item[$sourceKey] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($sourceId <= 0 || $quantity <= 0 || !isset($index[$sourceId])) {
                continue;
            }

            $source = $index[$sourceId];
            $normalizedItem = [
                $sourceKey => $sourceId,
                'product_name' => $source['product_name'],
                'unit_measure' => $source['unit_measure'],
                'quantity' => $quantity,
                'unit_price' => (float) $source['unit_price'],
                'total_item' => $quantity * (float) $source['unit_price'],
            ];

            foreach (['contract_item_id', 'purchase_order_item_id', 'delivery_note_id', 'purchase_order_id', 'delivery_note_item_id', 'remission_id', 'remission_item_id'] as $extraKey) {
                if (isset($source[$extraKey])) {
                    $normalizedItem[$extraKey] = $source[$extraKey];
                }
            }

            $normalized[] = $normalizedItem;
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     * @return array<int, string>
     */
    private function assertConsistentDocuments(array $documents, string $labelField, string $contractField, string $clientField): array
    {
        if (count($documents) <= 1) {
            return [];
        }

        $contractIds = array_unique(array_map(static fn (array $document): string => (string) ($document[$contractField] ?? ''), $documents));
        $clientIds = array_unique(array_map(static fn (array $document): string => (string) ($document[$clientField] ?? ''), $documents));

        $errors = [];

        if (count($contractIds) > 1) {
            $errors[] = 'No se pueden mezclar documentos de distinto contrato en un mismo flujo.';
        }

        if (count($clientIds) > 1) {
            $errors[] = 'No se pueden mezclar documentos de distinto cliente en un mismo flujo.';
        }

        foreach ($documents as $document) {
            if (($document[$labelField] ?? '') === '') {
                continue;
            }
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $submitted
     * @param array<int, array<string, mixed>> $contextItems
     * @return array<int, string>
     */
    private function assertKnownSources(array $submitted, array $contextItems, string $sourceKey, string $sourceLabel): array
    {
        $knownIds = array_map(static fn (array $item): int => (int) $item['id'], $contextItems);
        $errors = [];

        foreach ($submitted as $item) {
            $sourceId = (int) ($item[$sourceKey] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($sourceId <= 0 || $quantity <= 0) {
                continue;
            }

            if (!in_array($sourceId, $knownIds, true)) {
                $errors[] = 'Se detectó un item que no pertenece a la ' . $sourceLabel . ' cargada.';
            }
        }

        return array_values(array_unique($errors));
    }
}
