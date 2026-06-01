<section class="print-sheet">
    <header class="print-header">
        <img src="<?= e(config('app.company.logo')) ?>" alt="Industria Feris" class="brand__logo">
        <div>
            <h1>Nota de entrega <?= e($note['note_number']) ?></h1>
            <p><?= e($note['note_date']) ?> · Órdenes <?= e(implode(', ', array_map(static fn (array $order): string => (string) $order['order_number'], $note['source_orders'] ?? []))) ?></p>
        </div>
    </header>
    <section class="print-grid">
        <div class="print-card">
            <p><strong>Cliente:</strong> <?= e($note['client_name']) ?></p>
            <p><strong>Contrato:</strong> <?= e($note['contract_number']) ?></p>
            <p><strong>ID:</strong> <?= e($note['identifier_number']) ?></p>
            <p><strong>Modalidad:</strong> <?= e($note['contract_type']) ?></p>
            <p><strong>RUC:</strong> <?= e($note['tax_id']) ?></p>
        </div>
        <div class="print-card">
            <p><strong>Dirección de entrega:</strong> <?= e($note['delivery_address']) ?></p>
            <p><strong>Recibe:</strong> <?= e($note['receiver_name']) ?></p>
            <p><strong>Firma recibe:</strong> <?= e($note['receiver_signature']) ?></p>
            <p><strong>Entrega:</strong> <?= e($note['issuer_name']) ?></p>
            <p><strong>Firma entrega:</strong> <?= e($note['issuer_signature']) ?></p>
        </div>
    </section>
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
        <?php foreach ($note['items'] as $item): ?>
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
    <footer class="print-footer">
        <strong>Total documento</strong>
        <strong>Gs. <?= e(money($note['total_amount'])) ?></strong>
    </footer>
</section>
