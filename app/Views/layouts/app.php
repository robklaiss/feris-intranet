<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?php $currentPath = app_request_path((string) ($_SERVER['REQUEST_URI'] ?? '/')); ?>
<?php $currentUser = current_user(); ?>
<header class="topbar">
    <div class="brand">
        <img src="<?= e(config('app.company.logo')) ?>" alt="Industria Feris" class="brand__logo">
        <div>
            <strong><?= e(config('app.company.name')) ?></strong>
            <span>CRM operativo y documental</span>
        </div>
    </div>
    <div class="topbar__actions">
        <div class="user-chip" aria-label="Usuario actual">
            <strong><?= e($currentUser['name']) ?></strong>
            <span><?= e($currentUser['role']) ?></span>
        </div>
        <button type="button" class="menu-toggle" data-nav-toggle aria-label="Abrir navegación">Menu</button>
    </div>
</header>

<div class="shell">
    <aside class="sidebar" data-nav>
        <div class="sidebar__content">
            <nav class="nav">
                <a href="/" class="<?= $currentPath === '/' ? 'is-active' : '' ?>">Dashboard</a>
                <a href="/clients" class="<?= str_starts_with($currentPath, '/clients') ? 'is-active' : '' ?>">Clientes</a>
                <a href="/contracts" class="<?= str_starts_with($currentPath, '/contracts') ? 'is-active' : '' ?>">Contratos</a>
                <a href="/licitaciones" class="<?= str_starts_with($currentPath, '/licitaciones') ? 'is-active' : '' ?>">Licitaciones</a>
                <a href="/purchase-orders" class="<?= str_starts_with($currentPath, '/purchase-orders') ? 'is-active' : '' ?>">Órdenes</a>
                <a href="/customer-purchase-orders" class="<?= str_starts_with($currentPath, '/customer-purchase-orders') ? 'is-active' : '' ?>">OC cliente</a>
                <a href="/production-orders" class="<?= str_starts_with($currentPath, '/production-orders') ? 'is-active' : '' ?>">Producción</a>
                <a href="/cutting-orders" class="<?= str_starts_with($currentPath, '/cutting-orders') ? 'is-active' : '' ?>">Corte</a>
                <a href="/external-work-orders" class="<?= str_starts_with($currentPath, '/external-work-orders') ? 'is-active' : '' ?>">Trabajos externos</a>
                <a href="/sewing-orders" class="<?= str_starts_with($currentPath, '/sewing-orders') ? 'is-active' : '' ?>">Confección</a>
                <a href="/quality-control" class="<?= str_starts_with($currentPath, '/quality-control') || str_starts_with($currentPath, '/quality-reworks') ? 'is-active' : '' ?>">Calidad</a>
                <a href="/seamsters" class="<?= str_starts_with($currentPath, '/seamsters') ? 'is-active' : '' ?>">Costureros</a>
                <a href="/raw-materials" class="<?= str_starts_with($currentPath, '/raw-materials') ? 'is-active' : '' ?>">Insumos</a>
                <a href="/suppliers" class="<?= str_starts_with($currentPath, '/suppliers') ? 'is-active' : '' ?>">Proveedores</a>
                <a href="/purchase-requisitions" class="<?= str_starts_with($currentPath, '/purchase-requisitions') || str_starts_with($currentPath, '/supplier-purchase-orders') ? 'is-active' : '' ?>">Compras</a>
                <a href="/goods-receipts" class="<?= str_starts_with($currentPath, '/goods-receipts') ? 'is-active' : '' ?>">Recepciones</a>
                <a href="/delivery-notes" class="<?= str_starts_with($currentPath, '/delivery-notes') ? 'is-active' : '' ?>">Notas de entrega</a>
                <a href="/remissions" class="<?= str_starts_with($currentPath, '/remissions') ? 'is-active' : '' ?>">Remisiones</a>
                <a href="/invoices" class="<?= str_starts_with($currentPath, '/invoices') ? 'is-active' : '' ?>">Facturas</a>
                <a href="/reports" class="<?= str_starts_with($currentPath, '/reports') ? 'is-active' : '' ?>">Reportes</a>
                <?php if (can('settings.manage')): ?>
                    <a href="/settings" class="<?= str_starts_with($currentPath, '/settings') ? 'is-active' : '' ?>">Configuración</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar__footer">
                <form method="post" action="/logout" class="logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button--secondary logout-button">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </aside>

    <main class="content">
        <?= \App\Support\View::partial('partials/flash') ?>
        <?= $content ?>
    </main>
</div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
