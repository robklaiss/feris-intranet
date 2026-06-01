<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DocumentWorkflowService;
use App\Support\Request;
use Throwable;

final class DocumentOperationController extends Controller
{
    public function confirm(Request $request, string $type, string $id)
    {
        return $this->handleTransition($type, (int) $id, 'confirm');
    }

    public function cancel(Request $request, string $type, string $id)
    {
        return $this->handleTransition($type, (int) $id, 'cancel');
    }

    public function reopen(Request $request, string $type, string $id)
    {
        return $this->handleTransition($type, (int) $id, 'reopen');
    }

    public function close(Request $request, string $type, string $id)
    {
        return $this->handleTransition($type, (int) $id, 'close');
    }

    private function handleTransition(string $type, int $id, string $action)
    {
        $service = new DocumentWorkflowService();

        try {
            $document = $service->transition($type, $id, $action);

            return $this->redirectWithMessage(
                $service->showPath($type, $id),
                sprintf(
                    '%s %s pasó a %s.',
                    $service->typeLabel($type),
                    (string) ($document[$this->numberField($type)] ?? ('#' . $id)),
                    strtolower($service->statusLabel((string) $document['status']))
                )
            );
        } catch (Throwable $exception) {
            return $this->redirectWithMessage(
                $service->showPath($type, $id),
                $exception->getMessage(),
                'error'
            );
        }
    }

    private function numberField(string $type): string
    {
        return match ($type) {
            'contracts' => 'contract_number',
            'purchase_orders' => 'order_number',
            'delivery_notes' => 'note_number',
            'remissions' => 'remission_number',
            'invoices' => 'invoice_number',
            default => 'id',
        };
    }
}
