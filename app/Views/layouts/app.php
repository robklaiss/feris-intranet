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
<?php
$navigationGroups = [
    [
        'label' => 'Dashboard',
        'items' => [
            ['label' => 'Dashboard', 'href' => '/', 'active' => ['/']],
        ],
    ],
    [
        'label' => 'Comercial',
        'items' => [
            ['label' => 'Clientes', 'href' => '/clients', 'active' => ['/clients']],
            ['label' => 'Contratos', 'href' => '/contracts', 'active' => ['/contracts']],
            ['label' => 'Licitaciones', 'href' => '/licitaciones', 'active' => ['/licitaciones']],
            ['label' => 'OC cliente', 'href' => '/customer-purchase-orders', 'active' => ['/customer-purchase-orders']],
        ],
    ],
    [
        'label' => 'Producción',
        'items' => [
            ['label' => 'Producciones', 'href' => '/production-orders', 'active' => ['/production-orders']],
            ['label' => 'Corte', 'href' => '/cutting-orders', 'active' => ['/cutting-orders']],
            ['label' => 'Externos', 'href' => '/external-work-orders', 'active' => ['/external-work-orders']],
            ['label' => 'Costureros', 'href' => '/seamsters', 'active' => ['/seamsters']],
            ['label' => 'Confección', 'href' => '/sewing-orders', 'active' => ['/sewing-orders']],
            ['label' => 'Calidad', 'href' => '/quality-control', 'active' => ['/quality-control', '/quality-reworks']],
            ['label' => 'Empaque', 'href' => '/packaging-orders', 'active' => ['/packaging-orders']],
        ],
    ],
    [
        'label' => 'Inventario',
        'items' => [
            ['label' => 'Insumos', 'href' => '/raw-materials', 'active' => ['/raw-materials']],
            ['label' => 'Producto listo', 'href' => '/finished-goods-inventory', 'active' => ['/finished-goods-inventory']],
        ],
    ],
    [
        'label' => 'Compras',
        'items' => [
            ['label' => 'Proveedores', 'href' => '/suppliers', 'active' => ['/suppliers']],
            ['label' => 'Presupuestos', 'href' => '/purchase-requisitions', 'active' => ['/purchase-requisitions']],
            ['label' => 'OC proveedor', 'href' => '/supplier-purchase-orders', 'active' => ['/supplier-purchase-orders']],
            ['label' => 'Recepciones', 'href' => '/goods-receipts', 'active' => ['/goods-receipts']],
        ],
    ],
    [
        'label' => 'Despacho',
        'items' => [
            ['label' => 'Notas', 'href' => '/delivery-notes', 'active' => ['/delivery-notes']],
            ['label' => 'Remisiones', 'href' => '/remissions', 'active' => ['/remissions']],
            ['label' => 'Facturas', 'href' => '/invoices', 'active' => ['/invoices']],
        ],
    ],
    [
        'label' => 'Reportes',
        'items' => [
            ['label' => 'Reportes', 'href' => '/reports', 'active' => ['/reports']],
        ],
    ],
    [
        'label' => 'Sistema',
        'items' => [
            ['label' => 'Configuración', 'href' => '/settings', 'active' => ['/settings']],
        ],
    ],
];

$canViewNavItem = static function (array $item): bool {
    $permission = \App\Support\AccessControl::permissionFor('GET', (string) $item['href']);

    return $permission === null || can($permission);
};

$isNavItemActive = static function (array $item) use ($currentPath): bool {
    foreach ($item['active'] as $activePath) {
        if ($activePath === '/') {
            if ($currentPath === '/') {
                return true;
            }

            continue;
        }

        if ($currentPath === $activePath || str_starts_with($currentPath, $activePath . '/')) {
            return true;
        }
    }

    return false;
};

$visibleNavigationGroups = [];
foreach ($navigationGroups as $group) {
    $items = array_values(array_filter($group['items'], $canViewNavItem));

    if ($items !== []) {
        $visibleNavigationGroups[] = [
            'label' => $group['label'],
            'items' => $items,
        ];
    }
}
?>
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
                <?php foreach ($visibleNavigationGroups as $group): ?>
                    <?php $isGroupActive = array_filter($group['items'], $isNavItemActive) !== []; ?>
                    <details class="nav-group" <?= $isGroupActive ? 'open' : '' ?>>
                        <summary class="nav-group__summary"><?= e($group['label']) ?></summary>
                        <div class="nav-group__items">
                            <?php foreach ($group['items'] as $item): ?>
                                <a href="<?= e($item['href']) ?>" class="<?= $isNavItemActive($item) ? 'is-active' : '' ?>"><?= e($item['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
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
