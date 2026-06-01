<section class="print-sheet">
    <header class="print-header">
        <img src="<?= e(config('app.company.logo')) ?>" alt="Industria Feris" class="brand__logo">
        <div>
            <h1>Remisión <?= e($remission['remission_number']) ?></h1>
            <p><?= e($remission['remission_date']) ?></p>
        </div>
    </header>
    <section class="print-grid">
        <div class="print-card">
            <p><strong>Cliente:</strong> <?= e($remission['client_name']) ?></p>
            <p><strong>Contrato:</strong> <?= e($remission['contract_number']) ?></p>
            <p><strong>ID:</strong> <?= e($remission['reference_number']) ?></p>
            <p><strong>Modalidad:</strong> <?= e($remission['contract_type']) ?></p>
            <p><strong>RUC:</strong> <?= e($remission['tax_id']) ?></p>
            <p><strong>Notas fuente:</strong> <?= e(implode(', ', array_map(static fn (array $note): string => (string) $note['note_number'], $remission['source_notes'] ?? []))) ?></p>
        </div>
        <div class="print-card">
            <p><strong>Partida:</strong> <?= e($remission['origin_address']) ?></p>
            <p><strong>Llegada:</strong> <?= e($remission['destination_address']) ?></p>
            <p><strong>Traslado:</strong> <?= e($remission['transfer_start_date']) ?> al <?= e($remission['transfer_end_date']) ?></p>
            <p><strong>Vehículo:</strong> <?= e($remission['vehicle_brand']) ?> · <?= e($remission['vehicle_plate']) ?></p>
            <p><strong>Transportista:</strong> <?= e($remission['carrier_name']) ?> · <?= e($remission['carrier_tax_id']) ?></p>
            <p><strong>Conductor:</strong> <?= e($remission['driver_name']) ?> · <?= e($remission['driver_document']) ?></p>
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
        <?php foreach ($remission['items'] as $item): ?>
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
        <strong>Gs. <?= e(money($remission['total_amount'])) ?></strong>
    </footer>
</section>
