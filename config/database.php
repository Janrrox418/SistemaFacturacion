<?php

// Railway proporciona estas variables automáticamente si las vinculaste
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER') ?: 'root'); 
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');     
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'db_facturacion'); 
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');

try {
    // Añadimos el puerto a la cadena de conexión DSN
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");
} catch (PDOException $e) {
    die("ERROR: No se pudo conectar a la base de datos. " . $e->getMessage());
}