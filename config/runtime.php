<?php

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if (!is_string($documentRoot) || $documentRoot === '') {
    return;
}

$configDirectory = dirname(rtrim($documentRoot, DIRECTORY_SEPARATOR));
$directory = rtrim($documentRoot, DIRECTORY_SEPARATOR);
while ($directory !== '' && $directory !== dirname($directory)) {
    if (strtolower(basename($directory)) === 'public_html') {
        $configDirectory = dirname($directory);
        break;
    }

    $directory = dirname($directory);
}

$privateConfigPath = $configDirectory . DIRECTORY_SEPARATOR . 'facturacion-private.php';

if (!is_file($privateConfigPath)) {
    return;
}

$privateConfig = require $privateConfigPath;
$allowedKeys = [
    'APP_ENV',
    'MYSQLHOST',
    'MYSQLPORT',
    'MYSQLUSER',
    'MYSQLPASSWORD',
    'MYSQLDATABASE',
    'SAP_API_URL',
    'SAP_API_CLIENT',
    'SAP_API_USERNAME',
    'SAP_API_PASSWORD',
    'INVOICE_STORAGE_PATH',
];

if (!is_array($privateConfig)) {
    error_log('The private application config must return an array.');
    http_response_code(500);
    exit('La configuración privada del portal no es válida.');
}

foreach ($privateConfig as $key => $value) {
    if (!is_string($key) || !in_array($key, $allowedKeys, true) || !is_string($value)) {
        error_log('The private application config contains an invalid entry.');
        http_response_code(500);
        exit('La configuración privada del portal no es válida.');
    }

    if (getenv($key) === false) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
