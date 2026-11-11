<?php
session_start();

if (!isset($_SESSION['proveedor_id'], $_SESSION['proveedor_sap_id'], $_SESSION['proveedor_bukrs'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

$redirect = static function (string $type, string $message): void {
    $query = [$type => $message];
    foreach (['fecha_inicio', 'fecha_fin'] as $field) {
        if (is_string($_POST[$field] ?? null)) {
            $query[$field] = $_POST[$field];
        }
    }
    header('Location: ../index.php?' . http_build_query($query) . '#resultado-partidas-abiertas');
    exit();
};
$longitudTexto = static function (string $value): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $length = iconv_strlen($value, 'UTF-8');
    return $length === false ? PHP_INT_MAX : $length;
};
$recortarTexto = static function (string $value, int $length): string {
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length, 'UTF-8');
    }

    $substring = iconv_substr($value, 0, $length, 'UTF-8');
    return $substring === false ? substr($value, 0, $length) : $substring;
};

$csrfToken = $_SESSION['csrf_token'] ?? '';
$submittedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
if (!is_string($csrfToken) || $csrfToken === '' || !hash_equals($csrfToken, $submittedToken)) {
    http_response_code(400);
    exit('La solicitud expiró. Recargue la página e inténtelo nuevamente.');
}

$belnr = is_string($_POST['belnr'] ?? null) ? trim($_POST['belnr']) : '';
$gjahr = is_string($_POST['gjahr'] ?? null) ? trim($_POST['gjahr']) : '';
$oc = is_string($_POST['oc'] ?? null) ? trim($_POST['oc']) : '';
$documentSignature = is_string($_POST['document_signature'] ?? null)
    ? $_POST['document_signature']
    : '';
if (
    !preg_match('/^\d{1,20}$/', $belnr)
    || !preg_match('/^\d{4}$/', $gjahr)
    || !preg_match('/^\d{1,10}$/', $oc)
) {
    $redirect('error', 'La partida seleccionada no es válida.');
}

$documentKey = implode('|', [
    (string) $_SESSION['proveedor_bukrs'],
    (string) $_SESSION['proveedor_sap_id'],
    $belnr,
    $gjahr,
    $oc,
]);
$expectedSignature = hash_hmac('sha256', $documentKey, $csrfToken);
if (!hash_equals($expectedSignature, $documentSignature)) {
    $redirect('error', 'No se pudo validar la partida seleccionada. Vuelva a consultar el estado de cuenta.');
}

$file = $_FILES['factura'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $redirect('error', 'Seleccione un archivo válido para cargar.');
}
if (
    !isset($file['tmp_name'], $file['size'])
    || !is_string($file['tmp_name'])
    || !is_numeric($file['size'])
    || (int) $file['size'] > 10 * 1024 * 1024
    || !is_uploaded_file($file['tmp_name'])
) {
    $redirect('error', 'El archivo supera el límite de 10 MB o no es válido.');
}

$mimeType = 'application/pdf';
if (class_exists(finfo::class)) {
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMimeType = $fileInfo->file($file['tmp_name']);
    if ($detectedMimeType !== 'application/pdf') {
        $redirect('error', 'Formato no permitido. Cargue un archivo PDF.');
    }
} else {
    $fileHeader = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    $trailerOffset = max(0, (int) $file['size'] - 1024);
    $fileTrailer = file_get_contents($file['tmp_name'], false, null, $trailerOffset, 1024);
    if (
        !is_string($fileHeader)
        || !is_string($fileTrailer)
        || strpos($fileHeader, '%PDF-') === false
        || strpos($fileTrailer, '%%EOF') === false
    ) {
        $redirect('error', 'No se pudo validar el PDF. Verifique que el archivo sea un PDF válido.');
    }
}
$facturaNumero = is_string($_POST['factura_numero'] ?? null)
    ? trim($_POST['factura_numero'])
    : '';
$neto = is_string($_POST['neto'] ?? null) ? trim($_POST['neto']) : '';
$fechaDocumento = is_string($_POST['fecha_documento'] ?? null)
    ? trim($_POST['fecha_documento'])
    : '';
$metodoPago = is_string($_POST['metodo_pago'] ?? null) ? trim($_POST['metodo_pago']) : '';
$indicadorImpuesto = is_string($_POST['indicador_impuesto'] ?? null)
    ? trim($_POST['indicador_impuesto'])
    : '';
$fechaDocumentoObj = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaDocumento);
if (
    $facturaNumero === ''
    || $longitudTexto($facturaNumero) > 50
    || !preg_match('/^\d+(?:\.\d{1,2})?$/', $neto)
    || (float) $neto <= 0
    || $fechaDocumentoObj === false
    || $fechaDocumentoObj->format('Y-m-d') !== $fechaDocumento
    || $metodoPago === ''
    || $longitudTexto($metodoPago) > 20
    || $indicadorImpuesto === ''
    || $longitudTexto($indicadorImpuesto) > 20
) {
    $redirect('error', 'Complete los datos de cabecera de la factura con valores válidos.');
}

$submittedItems = $_POST['posiciones'] ?? null;
if (!is_array($submittedItems) || count($submittedItems) < 1 || count($submittedItems) > 100) {
    $redirect('error', 'Agregue al menos una posición válida para la orden de compra.');
}
$items = [];
foreach ($submittedItems as $item) {
    if (
        !is_array($item)
        || !is_string($item['POSOC'] ?? null)
        || !is_string($item['MENGE'] ?? null)
        || !is_string($item['MEINS'] ?? null)
        || !is_string($item['NETWR'] ?? null)
    ) {
        $redirect('error', 'Una posición de la orden de compra tiene datos inválidos.');
    }

    $position = trim($item['POSOC']);
    $quantity = trim($item['MENGE']);
    $unit = strtoupper(trim($item['MEINS']));
    $netValue = trim($item['NETWR']);
    if (
        !preg_match('/^\d{1,5}$/', $position)
        || !preg_match('/^\d+(?:\.\d{1,3})?$/', $quantity)
        || (float) $quantity <= 0
        || !preg_match('/^[A-Z0-9]{1,3}$/', $unit)
        || !preg_match('/^\d+(?:\.\d{1,2})?$/', $netValue)
        || (float) $netValue <= 0
    ) {
        $redirect('error', 'Una posición de la orden de compra tiene datos inválidos.');
    }
    $items[] = [
        'POSOC' => $position,
        'MENGE' => (float) $quantity,
        'MEINS' => $unit,
        'NETWR' => $netValue,
    ];
}

require_once '../config/database.php';
require_once '../config/sap.php';
require_once '../config/storage.php';
require_once '../services/SapApiService.php';

$storageDirectory = INVOICE_STORAGE_DIRECTORY;
if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
    error_log('Could not create storage directory for SAP invoice attachments.');
    $redirect('error', 'No fue posible preparar el almacenamiento de facturas.');
}

$storedFileName = bin2hex(random_bytes(16)) . '.pdf';
$storedFilePath = $storageDirectory . '/' . $storedFileName;
if (!move_uploaded_file($file['tmp_name'], $storedFilePath)) {
    error_log('Could not move uploaded SAP invoice attachment.');
    $redirect('error', 'No fue posible guardar el archivo de factura.');
}

$archivo = file_get_contents($storedFilePath);
if ($archivo === false) {
    if (!unlink($storedFilePath)) {
        error_log('Could not remove unreadable SAP invoice attachment: ' . $storedFilePath);
    }
    error_log('Could not read uploaded SAP invoice attachment: ' . $storedFilePath);
    $redirect('error', 'No fue posible leer el archivo de factura.');
}
$nombreArchivo = $recortarTexto(
    basename((string) ($file['name'] ?? 'factura.pdf')),
    255
);
$payload = [
    [
        'CAB_MIRO' => [
            'PROVEEDOR' => (string) $_SESSION['proveedor_sap_id'],
            'OC' => $oc,
            'FACTURA' => $facturaNumero,
            'NETO' => $neto,
            'TAX_IND' => $indicadorImpuesto,
            'SAT' => $facturaNumero,
            'FECHADOC' => $fechaDocumentoObj->format('Ymd'),
            'METODOP' => $metodoPago,
        ],
        'ITM_MIRO' => $items,
        'ARC_MIRO' => [
            'NOMBRE_ARCHIVO' => $nombreArchivo,
            'ARCHIVO_B64' => base64_encode($archivo),
        ],
    ],
];
$mostrarRespuestaSap = getenv('APP_ENV') !== 'production'
    && ($_POST['debug_sap_response'] ?? '') === '1';
$mostrarRespuestaCruda = static function (string $response, ?int $statusCode = null): void {
    if ($statusCode !== null) {
        http_response_code($statusCode);
    }
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $response;
    exit;
};

try {
    $sapApi = new SapApiService(
        SAP_API_URL,
        SAP_API_USERNAME,
        SAP_API_PASSWORD
    );
    $sapResponse = $sapApi->putRaw($payload);
    if ($mostrarRespuestaSap) {
        if (is_file($storedFilePath) && !unlink($storedFilePath)) {
            error_log('Could not remove diagnostic SAP invoice upload: ' . $storedFilePath);
        }
        $mostrarRespuestaCruda($sapResponse);
    }
} catch (JsonException $e) {
    if (!unlink($storedFilePath)) {
        error_log('Could not remove invoice after SAP payload encoding failed: ' . $storedFilePath);
    }
    error_log('Could not encode SAP invoice payload: ' . $e->getMessage());
    $redirect('error', 'No fue posible preparar los datos de la factura para SAP.');
} catch (SapApiException $e) {
    if ($mostrarRespuestaSap) {
        if (is_file($storedFilePath) && !unlink($storedFilePath)) {
            error_log('Could not remove diagnostic SAP invoice upload: ' . $storedFilePath);
        }
        $mostrarRespuestaCruda($e->getResponseBody(), $e->getHttpStatusCode());
    }
    if (is_file($storedFilePath) && !unlink($storedFilePath)) {
        error_log('Could not remove invoice after SAP request failed: ' . $storedFilePath);
    }
    error_log('Could not create SAP invoice: ' . $e->getMessage());
    $redirect('error', 'SAP no pudo registrar la factura. Verifique los datos e inténtelo nuevamente.');
} catch (RuntimeException $e) {
    if (!unlink($storedFilePath)) {
        error_log('Could not remove invoice after SAP request failed: ' . $storedFilePath);
    }
    error_log('Could not create SAP invoice: ' . $e->getMessage());
    $redirect('error', 'No se pudo confirmar la respuesta de SAP. Verifique si la factura quedó registrada antes de volver a intentarlo.');
}

try {
    $stmt = $pdo->prepare(
        'SELECT archivo
         FROM proveedor_facturas_sap
         WHERE bukrs = ? AND lifnr = ? AND belnr = ? AND gjahr = ?'
    );
    $stmt->execute([
        (string) $_SESSION['proveedor_bukrs'],
        (string) $_SESSION['proveedor_sap_id'],
        $belnr,
        $gjahr,
    ]);
    $previousFile = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO proveedor_facturas_sap
            (bukrs, lifnr, belnr, gjahr, archivo, nombre_original, mime_type)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            archivo = VALUES(archivo),
            nombre_original = VALUES(nombre_original),
            mime_type = VALUES(mime_type),
            uploaded_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        (string) $_SESSION['proveedor_bukrs'],
        (string) $_SESSION['proveedor_sap_id'],
        $belnr,
        $gjahr,
        $storedFileName,
        $nombreArchivo,
        $mimeType,
    ]);

    if (
        is_string($previousFile)
        && preg_match('/^[a-f0-9]{32}\.pdf$/', $previousFile)
    ) {
        $previousPath = $storageDirectory . '/' . $previousFile;
        if (is_file($previousPath) && !unlink($previousPath)) {
            error_log('Could not remove replaced SAP invoice attachment: ' . $previousPath);
        }
    }
} catch (PDOException $e) {
    if (is_file($storedFilePath) && !unlink($storedFilePath)) {
        error_log('Could not clean up failed SAP invoice upload: ' . $storedFilePath);
    }
    error_log('Could not save SAP invoice attachment metadata: ' . $e->getMessage());
    $redirect('error', 'SAP registró la factura, pero no se pudo actualizar el registro del portal. Contacte al administrador antes de volver a enviarla.');
}

$redirect('success', 'La factura se registró en SAP para la orden de compra ' . $oc . ' y se guardó en el portal.');
