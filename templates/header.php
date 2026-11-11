<?php
// Determinar la ruta base: si estamos en 'public', subimos un nivel ('../'), si no, estamos en raíz ('')
$script_directory = str_replace(
    '\\',
    '/',
    dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/')
);
$app_directory = basename($script_directory) === 'public'
    ? dirname($script_directory)
    : $script_directory;
$app_url = rtrim($app_directory, '/');
if ($app_url === '.') {
    $app_url = '';
}
$base_path = basename($script_directory) === 'public' ? '../' : '';
$custom_css_path = $app_url . '/public/css/style.css';
$custom_css_version = is_file(__DIR__ . '/../public/css/style.css')
    ? filemtime(__DIR__ . '/../public/css/style.css')
    : time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de proveedores</title>
    <!-- Bootstrap-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS personalizado para pequeños ajustes -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($custom_css_path, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $custom_css_version; ?>">
</head>
<body>

<?php
$current_page = basename($_SERVER['PHP_SELF']);
$provider_name = htmlspecialchars((string) ($_SESSION['proveedor_nombre'] ?? 'Proveedor'), ENT_QUOTES, 'UTF-8');
$sidebar_links = [
    ['label' => 'Partidas abiertas / Cargar facturas', 'icon' => '↑', 'href' => $base_path . 'index.php#partidas-abiertas', 'page' => 'index.php'],
    ['label' => 'Mi perfil', 'icon' => '○', 'href' => $base_path . 'public/perfil_proveedor.php', 'page' => 'perfil_proveedor.php'],
    ['label' => 'Cambiar contraseña', 'icon' => '●', 'href' => $base_path . 'public/cambiar_clave.php', 'page' => 'cambiar_clave.php'],
];
?>
<div class="app-shell">
    <div class="app-sidebar-backdrop" data-sidebar-backdrop></div>
    <aside class="app-sidebar" id="appSidebar" aria-label="Navegación principal">
        <a class="app-sidebar-brand" href="<?php echo $base_path; ?>index.php" title="Portal de proveedores">
            <span class="app-brand-mark" aria-hidden="true">PP</span>
            <span class="app-sidebar-label">Portal de proveedores</span>
        </a>
        <div class="app-sidebar-user">
            <span class="app-sidebar-label">Sesión activa</span>
            <strong class="app-sidebar-label"><?php echo $provider_name; ?></strong>
        </div>
        <nav class="app-sidebar-nav">
            <?php foreach ($sidebar_links as $link): ?>
                <a
                    class="app-sidebar-link<?php echo $current_page === $link['page'] ? ' active' : ''; ?>"
                    href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    title="<?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $current_page === $link['page'] ? 'aria-current="page"' : ''; ?>
                >
                    <span class="app-sidebar-icon" aria-hidden="true"><?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="app-sidebar-label"><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <a class="app-sidebar-link app-sidebar-logout" href="<?php echo $base_path; ?>public/logout.php" title="Cerrar sesión">
            <span class="app-sidebar-icon" aria-hidden="true">↪</span>
            <span class="app-sidebar-label">Cerrar sesión</span>
        </a>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button
                type="button"
                class="app-sidebar-toggle"
                data-sidebar-toggle
                aria-controls="appSidebar"
                aria-expanded="true"
                aria-label="Plegar menú lateral"
                title="Plegar o desplegar menú"
            >
                <span aria-hidden="true">☰</span>
            </button>
            <div class="app-topbar-greeting">Hola, <?php echo $provider_name; ?></div>
        </header>
        <main class="app-content container-fluid py-4">