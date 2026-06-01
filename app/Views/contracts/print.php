<section class="print-sheet">
    <header class="print-header">
        <img src="<?= e(config('app.company.logo')) ?>" alt="Industria Feris" class="brand__logo">
        <div>
            <h1>Contrato <?= e($contract['contract_number']) ?></h1>
            <p><?= e($contract['date']) ?></p>
        </div>
    </header>
    <p><strong>Cliente:</strong> <?= e($contract['client_name'] ?: 'Sin cliente') ?></p>
    <p><strong>Observaciones:</strong> <?= e($contract['notes']) ?></p>
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
        <?php foreach ($contract['items'] as $item): ?>
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

