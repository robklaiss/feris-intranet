<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientBillingContactRepository;
use App\Repositories\ClientDependencyRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CustomerPurchaseOrderRepository;
use App\Repositories\ProductionOrderRepository;
use App\Services\OperationalAuditService;
use App\Support\Request;
use Throwable;

final class CustomerPurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
            'client_id' => (string) $request->input('client_id', ''),
            'contract_id' => (string) $request->input('contract_id', ''),
            'dependency_id' => (string) $request->input('dependency_id', ''),
        ];

        return $this->render('customer_purchase_orders/index', [
            'orders' => (new CustomerPurchaseOrderRepository())->search($filters),
            'filters' => $filters,
            'clients' => (new ClientRepository())->search(),
            'contracts' => (new ContractRepository())->activeForSelection(),
        ]);
    }

    public function create(Request $request)
    {
        $contractId = (int) $request->input('contract_id', 0);
        $contract = (new ContractRepository())->findWithItems($contractId);

        if (!$contract || (string) $contract['status'] !== 'confirmed') {
            return $this->redirectWithMessage('/contracts', 'Solo se puede crear una OC cliente desde un contrato confirmado.', 'error');
        }

        return $this->render('customer_purchase_orders/form', [
            'title' => 'Nueva orden de compra cliente',
            'action' => '/customer-purchase-orders',
            'order' => [
                'contract_id' => $contractId,
                'po_date' => date('Y-m-d'),
                'received_date' => date('Y-m-d'),
                'po_number' => '',
                'dependency_id' => '',
                'billing_contact_id' => '',
                'notes' => '',
                'attachment_path' => '',
            ],
            'contract' => $contract,
            'availableItems' => (new CustomerPurchaseOrderRepository())->availableContractItems($contractId),
            'dependencies' => (new ClientDependencyRepository())->byClientId((int) $contract['client_id']),
            'billingContacts' => (new ClientBillingContactRepository())->byClientId((int) $contract['client_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only(['contract_id', 'dependency_id', 'billing_contact_id', 'po_number', 'po_date', 'received_date', 'notes', 'attachment_path']);
        $items = $this->collectItems($request->all(), 'contract_item_spec_id');

        try {
            $repo = new CustomerPurchaseOrderRepository();
            $id = $repo->create($data, $items);
            $order = $repo->find($id);
            $this->audit('create_customer_purchase_order', null, 'draft', $order, $id, null, $repo->totalQuantity($id), count($items));

            return $this->redirectWithMessage('/customer-purchase-orders/' . $id, 'Orden de compra cliente creada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/customer-purchase-orders/create?contract_id=' . (int) ($data['contract_id'] ?? 0), 'No se pudo crear la OC cliente: ' . $exception->getMessage(), 'error');
        }
    }

    public function show(Request $request, string $id)
    {
        $repo = new CustomerPurchaseOrderRepository();
        $order = $repo->findWithItems((int) $id);

        if (!$order) {
            return $this->redirectWithMessage('/customer-purchase-orders', 'Orden de compra cliente no encontrada.', 'error');
        }

        return $this->render('customer_purchase_orders/show', [
            'order' => $order,
            'balances' => $repo->itemBalances((int) $id),
            'productionOrders' => (new ProductionOrderRepository())->search(['customer_purchase_order_id' => (int) $id]),
        ]);
    }

    public function edit(Request $request, string $id)
    {
        $repo = new CustomerPurchaseOrderRepository();
        $order = $repo->findWithItems((int) $id);

        if (!$order) {
            return $this->redirectWithMessage('/customer-purchase-orders', 'Orden de compra cliente no encontrada.', 'error');
        }
        if ((string) $order['status'] !== 'draft') {
            return $this->redirectWithMessage('/customer-purchase-orders/' . $id, 'Solo se puede editar una OC cliente en borrador.', 'error');
        }

        $contract = (new ContractRepository())->findWithItems((int) $order['contract_id']);

        return $this->render('customer_purchase_orders/form', [
            'title' => 'Editar orden de compra cliente',
            'action' => '/customer-purchase-orders/' . $id . '/update',
            'order' => $order,
            'contract' => $contract,
            'availableItems' => $repo->availableContractItems((int) $order['contract_id'], (int) $id),
            'dependencies' => (new ClientDependencyRepository())->byClientId((int) $order['client_id']),
            'billingContacts' => (new ClientBillingContactRepository())->byClientId((int) $order['client_id']),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $repo = new CustomerPurchaseOrderRepository();
        $existing = $repo->find((int) $id);
        if (!$existing) {
            return $this->redirectWithMessage('/customer-purchase-orders', 'Orden de compra cliente no encontrada.', 'error');
        }

        $data = $request->only(['dependency_id', 'billing_contact_id', 'po_number', 'po_date', 'received_date', 'notes', 'attachment_path']);
        $items = $this->collectItems($request->all(), 'contract_item_spec_id');

        try {
            $repo->updateDraft((int) $id, $data, $items);
            $order = $repo->find((int) $id);
            $this->audit('update_customer_purchase_order', (string) $existing['status'], (string) ($order['status'] ?? 'draft'), $order, (int) $id, null, $repo->totalQuantity((int) $id), count($items));

            return $this->redirectWithMessage('/customer-purchase-orders/' . $id, 'Orden de compra cliente actualizada.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/customer-purchase-orders/' . $id . '/edit', 'No se pudo actualizar la OC cliente: ' . $exception->getMessage(), 'error');
        }
    }

    public function confirm(Request $request, string $id)
    {
        return $this->transition((int) $id, 'confirm_customer_purchase_order', 'confirm');
    }

    public function cancel(Request $request, string $id)
    {
        return $this->transition((int) $id, 'cancel_customer_purchase_order', 'cancel');
    }

    public function close(Request $request, string $id)
    {
        return $this->transition((int) $id, 'close_customer_purchase_order', 'close');
    }

    private function transition(int $id, string $action, string $method)
    {
        $repo = new CustomerPurchaseOrderRepository();
        $existing = $repo->find($id);
        if (!$existing) {
            return $this->redirectWithMessage('/customer-purchase-orders', 'Orden de compra cliente no encontrada.', 'error');
        }

        try {
            $repo->{$method}($id);
            $order = $repo->find($id);
            $this->audit($action, (string) $existing['status'], (string) ($order['status'] ?? ''), $order, $id, null, $repo->totalQuantity($id), count($repo->items($id)));

            return $this->redirectWithMessage('/customer-purchase-orders/' . $id, 'Estado actualizado.');
        } catch (Throwable $exception) {
            return $this->redirectWithMessage('/customer-purchase-orders/' . $id, $exception->getMessage(), 'error');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function collectItems(array $payload, string $idField): array
    {
        $ids = is_array($payload[$idField] ?? null) ? $payload[$idField] : [];
        $quantities = is_array($payload['quantity'] ?? null) ? $payload['quantity'] : [];
        $notes = is_array($payload['notes_item'] ?? null) ? $payload['notes_item'] : [];
        $items = [];

        foreach ($ids as $index => $rawId) {
            if (trim((string) $rawId) === '') {
                continue;
            }
            $quantity = (float) ($quantities[$index] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $items[] = [
                $idField => (int) $rawId,
                'quantity' => $quantity,
                'notes' => trim((string) ($notes[$index] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $order
     */
    private function audit(string $action, ?string $previous, string $next, ?array $order, int $orderId, ?int $productionOrderId, float $totalQuantity, int $itemCount): void
    {
        (new OperationalAuditService())->logDocumentAction(
            'customer_purchase_orders',
            $orderId,
            (string) ($order['po_number'] ?? ''),
            $action,
            $previous,
            $next,
            [
                'client_id' => $order['client_id'] ?? null,
                'dependency_id' => $order['dependency_id'] ?? null,
                'contract_id' => $order['contract_id'] ?? null,
                'customer_purchase_order_id' => $orderId,
                'production_order_id' => $productionOrderId,
                'po_number' => $order['po_number'] ?? null,
                'production_number' => null,
                'status_previous' => $previous,
                'status_new' => $next,
                'total_quantity' => $totalQuantity,
                'item_count' => $itemCount,
            ]
        );
    }
}
