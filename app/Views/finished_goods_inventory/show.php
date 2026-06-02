<section class="page-head">
    <div>
        <p class="eyebrow">Producto terminado</p>
        <h1><?= e($item['internal_code']) ?></h1>
        <p class="muted">
            Empaquetado <a href="/packaging-orders/<?= e((string) $item['packaging_order_id']) ?>"><?= e($item['packaging_number']) ?></a>
            · Producción <a href="/production-orders/<?= e((string) $item['production_order_id']) ?>"><?= e($item['production_number']) ?></a>
        </p>
        <span class="<?= e(status_badge_class($item['status'])) ?>"><?= e(finished_goods_status_label($item['status'])) ?></span>
    </div>
    <div class="action-row">
        <?php if (in_array((string) $item['status'], ['available', 'reserved'], true) && (float) ($item['real_available'] ?? 0) > 0.0001): ?>
            <a href="/remissions/create?source=finished_goods&amp;finished_goods_inventory_ids=<?= e((string) $item['id']) ?>" class="button">Generar remisión</a>
        <?php endif; ?>
        <a href="/finished-goods-inventory" class="button button--secondary">Volver</a>
    </div>
</section>

<section class="grid-two">
    <article class="panel">
        <h2>Producto</h2>
        <dl class="detail-list">
            <div><dt>Cliente</dt><dd><?= e($item['client_name']) ?></dd></div>
            <div><dt>Dependencia</dt><dd><?= e($item['dependency_name'] ?? '-') ?></dd></div>
            <div><dt>Contrato</dt><dd><?= e($item['contract_number']) ?></dd></div>
            <div><dt>OC cliente</dt><dd><?= e($item['po_number']) ?></dd></div>
            <div><dt>Control de calidad</dt><dd><a href="/quality-control/<?= e((string) $item['quality_control_check_id']) ?>"><?= e($item['qc_number']) ?></a></dd></div>
            <div><dt>Código ítem</dt><dd><?= e($item['item_code']) ?></dd></div>
            <div><dt>Producto</dt><dd><?= e($item['product_type']) ?></dd></div>
            <div><dt>Descripción</dt><dd><?= e($item['description']) ?></dd></div>
            <div><dt>Talle</dt><dd><?= e($item['size']) ?></dd></div>
            <div><dt>Color</dt><dd><?= e($item['color']) ?></dd></div>
            <div><dt>Etiqueta</dt><dd><?= e($item['label'] ?? '-') ?></dd></div>
            <div><dt>Paquete</dt><dd><?= e($item['package_code'] ?? '-') ?></dd></div>
            <div><dt>Ubicación</dt><dd><?= e($item['location'] ?? '-') ?></dd></div>
            <div><dt>Observaciones</dt><dd><?= nl2br(e($item['notes'])) ?></dd></div>
        </dl>
    </article>
    <article class="panel">
        <h2>Cantidades</h2>
        <dl class="detail-list">
            <div><dt>Disponible</dt><dd><?= e((string) $item['quantity_available']) ?> <?= e($item['unit'] ?? '') ?></dd></div>
            <div><dt>Disponible real</dt><dd><?= e((string) ($item['real_available'] ?? $item['quantity_available'])) ?> <?= e($item['unit'] ?? '') ?></dd></div>
            <div><dt>Reservado</dt><dd><?= e((string) $item['quantity_reserved']) ?> <?= e($item['unit'] ?? '') ?></dd></div>
            <div><dt>Remitido</dt><dd><?= e((string) $item['quantity_remitted']) ?> <?= e($item['unit'] ?? '') ?></dd></div>
        </dl>
    </article>
</section>
