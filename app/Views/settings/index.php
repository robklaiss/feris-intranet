<section class="page-head">
    <div>
        <p class="eyebrow">Base técnica</p>
        <h1>Configuración</h1>
        <p class="muted">Numeradores configurables para órdenes, nota interna, remisión y factura.</p>
    </div>
</section>

<form method="post" action="/settings" class="panel form-stack">
    <?= csrf_field() ?>
    <div class="panel__header">
        <h2>Numeradores</h2>
        <span class="muted">El valor actual actúa como base inicial configurable.</span>
    </div>
    <div class="table-wrap">
        <table class="table table--form">
            <thead>
            <tr>
                <th>Módulo</th>
                <th>Prefijo</th>
                <th>Valor actual</th>
                <th>Padding</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($numerators as $numerator): ?>
                <tr>
                    <td>
                        <?= e($numerator['module']) ?>
                        <input type="hidden" name="module[]" value="<?= e($numerator['module']) ?>">
                    </td>
                    <td><input type="text" name="prefix[]" value="<?= e($numerator['prefix']) ?>"></td>
                    <td><input type="number" name="current_value[]" value="<?= e((string) $numerator['current_value']) ?>" min="0" step="1"></td>
                    <td><input type="number" name="padding[]" value="<?= e((string) $numerator['padding']) ?>" min="1" step="1"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions">
        <button type="submit" class="button">Guardar configuración</button>
    </div>
</form>
