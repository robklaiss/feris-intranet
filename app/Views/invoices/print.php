<section class="print-sheet">
    <header class="print-header">
        <img src="<?= e(config('app.company.logo')) ?>" alt="Industria Feris" class="brand__logo">
        <div>
            <h1>Factura <?= e($invoice['invoice_number']) ?></h1>
            <p><?= e($invoice['invoice_date']) ?></p>
        </div>
    </header>
    <section class="print-grid">
        <div class="print-card">
            <p><strong>Condición de venta:</strong> <?= e($invoice['sale_condition']) ?></p>
            <p><strong>Cliente:</strong> <?= e($invoice['client_name']) ?></p>
            <p><strong>Contrato:</strong> <?= e($invoice['contract_number']) ?></p>
            <p><strong>ID:</strong> <?= e($invoice['reference_number']) ?></p>
            <p><strong>Modalidad:</strong> <?= e($invoice['contract_type']) ?></p>
            <p><strong>RUC:</strong> <?= e($invoice['tax_id']) ?></p>
        </div>
        <div class="print-card">
            <p><strong>Dirección:</strong> <?= e($invoice['billing_address']) ?></p>
            <p><strong>Remisiones fuente:</strong> <?= e(implode(', ', array_map(static fn (array $remission): string => (string) $remission['remission_number'], $invoice['source_remissions'] ?? []))) ?></p>
            <p><strong>Estado integración local:</strong> <?= e($invoice['billing_status']) ?></p>
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
        <?php foreach ($invoice['items'] as $item): ?>
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
        <strong>Total factura</strong>
        <strong>Gs. <?= e(money($invoice['total_amount'])) ?></strong>
    </footer>
</section>
