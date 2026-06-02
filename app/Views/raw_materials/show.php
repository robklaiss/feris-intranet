<section class="page-head">
    <div>
        <p class="eyebrow">Insumo</p>
        <h1><?= e($material['internal_code']) ?></h1>
        <p class="muted"><?= e($material['material_type']) ?> · <?= e($material['description']) ?></p>
        <span class="<?= e(status_badge_class($material['status'])) ?>"><?= e($material['status']) ?></span>
    </div>
    <div class="page-actions">
        <a href="/raw-materials" class="button button--secondary">Volver</a>
        <?php if (can('documents.edit')): ?>
            <a href="/raw-materials/<?= e((string) $material['id']) ?>/edit" class="button">Editar</a>
        <?php endif; ?>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Disponibilidad</h2>
        <dl class="detail-list">
            <div><dt>Disponible</dt><dd><?= e((string) $material['quantity_available']) ?> <?= e($material['unit']) ?></dd></div>
            <div><dt>Reservado</dt><dd><?= e((string) $material['quantity_reserved']) ?> <?= e($material['unit']) ?></dd></div>
            <div><dt>Libre para reservar</dt><dd><?= e((string) $material['available_to_reserve']) ?> <?= e($material['unit']) ?></dd></div>
            <div><dt>Stock mínimo</dt><dd><?= e((string) $material['minimum_stock']) ?> <?= e($material['unit']) ?></dd></div>
            <div><dt>Costo</dt><dd><?= e((string) $material['cost']) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Relaciones</h2>
        <dl class="detail-list">
            <div><dt>Item relacionado</dt><dd><?= e($material['related_item_code']) ?></dd></div>
            <div><dt>Tipo producto relacionado</dt><dd><?= e($material['related_product_type']) ?></dd></div>
            <div><dt>Proveedor</dt><dd><?= e($material['supplier_name']) ?></dd></div>
            <div><dt>RUC proveedor</dt><dd><?= e($material['supplier_ruc']) ?></dd></div>
            <div><dt>Lote</dt><dd><?= e($material['lot_number']) ?></dd></div>
            <div><dt>Ubicación</dt><dd><?= e($material['location']) ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($material['notes'])) ?></dd></div>
        </dl>
    </article>
</section>

<section class="panel">
    <h2>Reservas activas</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Producción</th><th>Item</th><th>Cantidad</th><th>Fecha</th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $reservation): ?>
                <tr>
                    <td><a href="/production-orders/<?= e((string) $reservation['production_order_id']) ?>"><?= e($reservation['production_number']) ?></a></td>
                    <td><?= e($reservation['item_code']) ?> · <?= e($reservation['item_description']) ?></td>
                    <td><?= e((string) $reservation['reserved_quantity']) ?> <?= e($material['unit']) ?></td>
                    <td><?= e(format_datetime($reservation['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($reservations === []): ?>
                <tr><td colspan="4" class="empty">Sin reservas activas para este insumo.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
