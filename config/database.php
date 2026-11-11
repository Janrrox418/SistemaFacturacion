<?php

$production = getenv('APP_ENV') === 'production';
$mysqlHost = getenv('MYSQLHOST');
$mysqlUser = getenv('MYSQLUSER');
$mysqlPassword = getenv('MYSQLPASSWORD');
$mysqlDatabase = getenv('MYSQLDATABASE');

if (
    $production
    && (
        $mysqlHost === false || $mysqlHost === ''
        || $mysqlUser === false || $mysqlUser === ''
        || $mysqlPassword === false || $mysqlPassword === ''
        || $mysqlDatabase === false || $mysqlDatabase === ''
    )
) {
    error_log('Required MySQL environment variables are missing.');
    http_response_code(500);
    exit('La configuración de la base de datos está incompleta.');
}

$mysqlPort = getenv('MYSQLPORT');
define('DB_HOST', $mysqlHost !== false && $mysqlHost !== '' ? $mysqlHost : 'localhost');
define('DB_USER', $mysqlUser !== false && $mysqlUser !== '' ? $mysqlUser : 'root');
define('DB_PASS', $mysqlPassword !== false ? $mysqlPassword : '');
define('DB_NAME', $mysqlDatabase !== false && $mysqlDatabase !== '' ? $mysqlDatabase : 'db_facturacion');
define('DB_PORT', $mysqlPort !== false && $mysqlPort !== '' ? $mysqlPort : '3306');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    if ($production) {
        exit('No fue posible conectar con la base de datos.');
    }

    exit('ERROR: No se pudo conectar a la base de datos. ' . $e->getMessage());
}