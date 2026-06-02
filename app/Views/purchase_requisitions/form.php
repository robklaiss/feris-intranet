<section class="page-head">
    <div>
        <p class="eyebrow">Pedido de presupuesto</p>
        <h1>Generar desde <?= e($check['check_number']) ?></h1>
        <p class="muted">Orden de producción <a href="/production-orders/<?= e((string) $check['production_order_id']) ?>"><?= e($check['production_number']) ?></a></p>
    </div>
    <a href="/stock-checks/<?= e((string) $check['id']) ?>" class="button button--secondary">Volver</a>
</section>

<?php if (!empty($existing)): ?>
    <section class="panel">
        <p>Esta verificación ya tiene un pedido de presupuesto asociado.</p>
        <a href="/purchase-requisitions/<?= e((string) $existing['id']) ?>" class="button">Ver pedido</a>
    </section>
<?php else: ?>
    <form method="post" action="/stock-checks/<?= e((string) $check['id']) ?>/purchase-requisitions" class="panel">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>
                <span>Número de pedido</span>
                <input type="text" name="requisition_number" placeholder="Automático">
            </label>
            <label class="span-2">
                <span>Observaciones</span>
                <textarea name="notes" rows="3"></textarea>
            </label>
        </div>
        <h2>Faltantes detectados</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Requerimiento</th>
                    <th>Faltante</th>
                    <th>Cantidad a solicitar</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($check['items'] as $item): ?>
                    <?php if ((float) $item['missing_quantity'] <= 0): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <tr>
                        <td>
                            <strong><?= e($item['required_material_type']) ?></strong><br>
                            <span class="muted"><?= e($item['required_description']) ?></span>
                        </td>
                        <td><?= e((string) $item['missing_quantity']) ?> <?= e($item['required_unit']) ?></td>
                        <td>
                            <input type="number" step="0.01" min="0.01" name="requested_quantity[<?= e((string) $item['id']) ?>]" value="<?= e((string) $item['missing_quantity']) ?>" required>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="button">Generar pedido</button>
        </div>
    </form>
<?php endif; ?>
