<section class="print-doc">
    <header class="print-doc__header">
        <div>
            <p class="eyebrow">Orden de compra</p>
            <h1><?= e($order['order_number']) ?></h1>
            <p><?= e($order['order_date']) ?></p>
        </div>
        <div class="print-doc__meta">
            <p><strong>Cliente:</strong> <?= e($order['client_name'] ?: 'Sin cliente') ?></p>
            <p><strong>Contrato:</strong> <?= e($order['contract_number'] ?: 'Manual') ?></p>
            <p><strong>Estado:</strong> <?= e(document_status_label($order['status'])) ?></p>
            <p><strong>Total:</strong> Gs. <?= e(money($order['total_amount'])) ?></p>
        </div>
    </header>

    <table class="table">
        <thead>
        <tr>
            <th>Producto</th>
            <th>Unidad</th>
            <th>Cantidad</th>
            <th>Precio</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td><?= e($item['product_name']) ?></td>
                <td><?= e($item['unit_measure']) ?></td>
                <td><?= e((string) $item['quantity']) ?></td>
                <td>Gs. <?= e(money($item['unit_price'])) ?></td>
                <td>Gs. <?= e(money($item['total_item'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
