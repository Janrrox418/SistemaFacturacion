<?php
session_start();

if (!isset($_SESSION['proveedor_id'], $_SESSION['proveedor_sap_id'], $_SESSION['proveedor_bukrs'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/sap.php';
require_once '../services/SapApiService.php';

$error = '';
$perfil = [];
$campos_faltantes = [];
$campos = [
    'LIFNR' => 'Código de proveedor',
    'NAME1' => 'Nombre / Razón social',
    'STCD1' => 'RIF / Identificación fiscal',
    'STRAS' => 'Dirección fiscal',
    'TELF1' => 'Teléfono',
    'SMTP_ADDR' => 'Correo electrónico',
];
$valores_por_defecto = [
    'NAME1' => 'Nombre no disponible',
    'STCD1' => 'No registrado en SAP',
    'STRAS' => 'Sin dirección',
    'TELF1' => 'Sin teléfono',
    'SMTP_ADDR' => 'Sin correo',
];

$normalizar_lifnr = static function (string $lifnr): string {
    $lifnr = trim($lifnr);
    $normalizado = ltrim($lifnr, '0');
    return $normalizado === '' ? $lifnr : $normalizado;
};
$es_lista = static fn(array $values): bool => $values === []
    || array_keys($values) === range(0, count($values) - 1);

try {
    $data = (new SapApiService(
        SAP_API_URL,
        SAP_API_USERNAME,
        SAP_API_PASSWORD
    ))->get([
        'sap-client' => SAP_API_CLIENT,
        'TIPO' => 'PERFIL',
        'BUKRS' => (string) $_SESSION['proveedor_bukrs'],
        'LIFNR' => (string) $_SESSION['proveedor_sap_id'],
    ]);

    if (is_array($data) && $es_lista($data)) {
        if (count($data) !== 1 || !is_array($data[0])) {
            throw new UnexpectedValueException('SAP devolvió un formato inválido para el perfil.');
        }
        $data = $data[0];
    }

    if (!is_array($data)) {
        throw new UnexpectedValueException('SAP devolvió un formato inválido para el perfil.');
    }

    foreach ($campos as $campo => $etiqueta) {
        if (!array_key_exists($campo, $data) || $data[$campo] === null) {
            if ($campo === 'LIFNR') {
                throw new UnexpectedValueException('SAP devolvió el perfil sin el identificador LIFNR.');
            }
            $perfil[$campo] = $valores_por_defecto[$campo];
            $campos_faltantes[] = $etiqueta;
            continue;
        }
        if (!is_scalar($data[$campo])) {
            throw new UnexpectedValueException('SAP devolvió un valor inválido para el campo ' . $campo . '.');
        }
        $valor = trim((string) $data[$campo]);
        if ($valor === '') {
            if ($campo === 'LIFNR') {
                throw new UnexpectedValueException('SAP devolvió el perfil sin el identificador LIFNR.');
            }
            $perfil[$campo] = $valores_por_defecto[$campo];
            $campos_faltantes[] = $etiqueta;
            continue;
        }
        $perfil[$campo] = $valor;
    }

    if (
        $normalizar_lifnr($perfil['LIFNR'])
        !== $normalizar_lifnr((string) $_SESSION['proveedor_sap_id'])
    ) {
        throw new UnexpectedValueException('El perfil devuelto por SAP no corresponde al proveedor autenticado.');
    }
} catch (SapApiException $e) {
    error_log('SAP supplier profile request failed: ' . $e->getMessage());
    $error = 'No fue posible cargar el perfil desde SAP. Inténtelo de nuevo más tarde.';
} catch (InvalidArgumentException $e) {
    error_log('SAP supplier profile configuration is invalid: ' . $e->getMessage());
    $error = 'La configuración de SAP no es válida.';
} catch (JsonException $e) {
    error_log('SAP supplier profile response was not valid JSON: ' . $e->getMessage());
    $error = 'SAP devolvió una respuesta inválida para el perfil.';
} catch (UnexpectedValueException $e) {
    error_log($e->getMessage());
    $error = 'SAP devolvió datos inválidos para el perfil del proveedor.';
} catch (RuntimeException $e) {
    error_log('SAP supplier profile request failed: ' . $e->getMessage());
    $error = 'No fue posible contactar el servicio SAP para cargar el perfil.';
}

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

require_once '../templates/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="h4 mb-0">Perfil del proveedor</h2>
    </div>
    <div class="card-body">
        <p class="text-muted">Estos datos provienen de SAP y no se pueden modificar desde el portal.</p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo $escape($error); ?></div>
        <?php else: ?>
            <?php if ($campos_faltantes !== []): ?>
                <div class="alert alert-warning">
                    SAP no devolvió estos datos del perfil: <?php echo $escape(implode(', ', $campos_faltantes)); ?>.
                    Puede solicitar al administrador SAP que revise el servicio de perfil.
                </div>
            <?php endif; ?>
            <div class="row">
                <?php foreach ($campos as $campo => $etiqueta): ?>
                    <div class="col-md-6 mb-3">
                        <label for="perfil_<?php echo $escape($campo); ?>" class="form-label"><?php echo $escape($etiqueta); ?></label>
                        <?php if ($campo === 'STRAS'): ?>
                            <textarea class="form-control" id="perfil_<?php echo $escape($campo); ?>" rows="3" readonly><?php echo $escape($perfil[$campo]); ?></textarea>
                        <?php else: ?>
                            <input
                                type="text"
                                class="form-control"
                                id="perfil_<?php echo $escape($campo); ?>"
                                value="<?php echo $escape($perfil[$campo]); ?>"
                                readonly
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="../index.php" class="btn btn-secondary">Volver al estado de cuenta</a>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
