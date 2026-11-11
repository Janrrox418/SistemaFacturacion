<?php
session_start();

if (!isset($_SESSION['proveedor_id'], $_SESSION['proveedor_sap_id'], $_SESSION['proveedor_bukrs'])) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$belnr = is_string($_GET['belnr'] ?? null) ? trim($_GET['belnr']) : '';
$gjahr = is_string($_GET['gjahr'] ?? null) ? trim($_GET['gjahr']) : '';
if (!preg_match('/^\d{1,20}$/', $belnr) || !preg_match('/^\d{4}$/', $gjahr)) {
    http_response_code(400);
    exit('Identificador de documento inválido.');
}

require_once '../config/database.php';
require_once '../config/storage.php';

try {
    $stmt = $pdo->prepare(
        'SELECT archivo, mime_type
         FROM proveedor_facturas_sap
         WHERE bukrs = ? AND lifnr = ? AND belnr = ? AND gjahr = ?'
    );
    $stmt->execute([
        (string) $_SESSION['proveedor_bukrs'],
        (string) $_SESSION['proveedor_sap_id'],
        $belnr,
        $gjahr,
    ]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load SAP invoice attachment metadata: ' . $e->getMessage());
    http_response_code(500);
    exit('No fue posible consultar el archivo.');
}

$mimeTypes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];
if (
    !$attachment
    || !isset($mimeTypes[$attachment['mime_type']])
    || !preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', (string) $attachment['archivo'])
) {
    http_response_code(404);
    exit('Factura no encontrada.');
}

$filePath = INVOICE_STORAGE_DIRECTORY . '/' . $attachment['archivo'];
if (!is_file($filePath)) {
    http_response_code(404);
    exit('El archivo de factura ya no está disponible.');
}

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: attachment; filename="factura-' . $belnr . '.' . $mimeTypes[$attachment['mime_type']] . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
