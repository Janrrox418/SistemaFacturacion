<?php
$login_css_file = __DIR__ . '/../public/css/style.css';
$login_css_version = is_file($login_css_file) ? filemtime($login_css_file) : time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - Portal de proveedores</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css?v=<?php echo $login_css_version; ?>">
</head>
<body>

<div class="container mt-5">
    <div class="text-center">
        <h1 class="display-4">Portal de proveedores</h1>
    </div>
    <main class="mt-4">