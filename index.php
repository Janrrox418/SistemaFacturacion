<?php
session_start();

if (
    !isset($_SESSION['proveedor_id'], $_SESSION['proveedor_sap_id'], $_SESSION['proveedor_bukrs'])
) {
    header('Location: public/login.php');
    exit();
}

require_once 'config/database.php';
require_once 'config/sap.php';
require_once 'services/SapApiService.php';

$proveedor_sap_id = (string) $_SESSION['proveedor_sap_id'];
$sociedad = (string) $_SESSION['proveedor_bukrs'];
$fecha_inicio = date('Y-m-d', strtotime('-30 days'));
$fecha_fin = date('Y-m-d');
$error = '';
$partidas_abiertas = [];
$partidas_cerradas = [];
$partidas_no_disponibles = [];
$consultado = false;
$csrf_token = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$es_lista = static fn(array $values): bool => $values === []
    || array_keys($values) === range(0, count($values) - 1);
$normalizar_lifnr = static function (string $lifnr): string {
    $lifnr = trim($lifnr);
    $normalizado = ltrim($lifnr, '0');
    return $normalizado === '' ? $lifnr : $normalizado;
};
$formatear_fecha = static function ($value): string {
    $date = (string) $value;
    $format = strlen($date) === 8 ? '!Ymd' : '!Y-m-d';
    $parsed = DateTimeImmutable::createFromFormat($format, $date);
    return $parsed !== false && $parsed->format(strlen($date) === 8 ? 'Ymd' : 'Y-m-d') === $date
        ? $parsed->format('d/m/Y')
        : $date;
};
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    || (isset($_GET['fecha_inicio'], $_GET['fecha_fin']))
) {
    $es_post = $_SERVER['REQUEST_METHOD'] === 'POST';
    $consultado = true;
    $fecha_inicio_origen = $es_post ? $_POST : $_GET;
    $fecha_inicio = is_string($fecha_inicio_origen['fecha_inicio'] ?? null)
        ? trim($fecha_inicio_origen['fecha_inicio'])
        : '';
    $fecha_fin = is_string($fecha_inicio_origen['fecha_fin'] ?? null)
        ? trim($fecha_inicio_origen['fecha_fin'])
        : '';
    $submitted_csrf_token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $fecha_inicio_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_inicio);
    $fecha_fin_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_fin);
    $fechas_validas = $fecha_inicio_obj !== false
        && $fecha_inicio_obj->format('Y-m-d') === $fecha_inicio
        && $fecha_fin_obj !== false
        && $fecha_fin_obj->format('Y-m-d') === $fecha_fin;

    if ($es_post && !hash_equals($csrf_token, $submitted_csrf_token)) {
        $error = 'La solicitud expiró. Recargue la página e inténtelo nuevamente.';
    } elseif (!$fechas_validas) {
        $error = 'Seleccione fechas de inicio y fin válidas.';
    } elseif ($fecha_inicio_obj > $fecha_fin_obj) {
        $error = 'La fecha de inicio no puede ser posterior a la fecha de fin.';
    } else {
        try {
            $sap_data = (new SapApiService(
                SAP_API_URL,
                SAP_API_USERNAME,
                SAP_API_PASSWORD
            ))->patchQuery([
                'sap-client' => SAP_API_CLIENT,
                'BUKRS' => $sociedad,
                'LIFNR' => $proveedor_sap_id,
                'FECHAI' => $fecha_inicio_obj->format('Ymd'),
                'FECHAF' => $fecha_fin_obj->format('Ymd'),
            ]);
            if (!is_array($sap_data)) {
                throw new UnexpectedValueException('SAP devolvió una estructura inválida para el estado de cuenta.');
            }
            if ($es_lista($sap_data)) {
                if (count($sap_data) !== 1 || !is_array($sap_data[0])) {
                    throw new UnexpectedValueException('SAP devolvió una estructura inválida para el estado de cuenta.');
                }
                $sap_data = $sap_data[0];
            }

            if (
                !is_array($sap_data)
                || !isset($sap_data['OPEN'], $sap_data['CLOSE'])
                || !is_array($sap_data['OPEN'])
                || !is_array($sap_data['CLOSE'])
                || !$es_lista($sap_data['OPEN'])
                || !$es_lista($sap_data['CLOSE'])
            ) {
                throw new UnexpectedValueException('SAP devolvió una estructura inválida para el estado de cuenta.');
            }

            foreach (['OPEN', 'CLOSE'] as $tipo) {
                foreach ($sap_data[$tipo] as $partida) {
                    $campos_requeridos = ['BUKRS', 'LIFNR', 'BELNR', 'GJAHR', 'BUDAT', 'WRBTR', 'WAERS'];
                    if ($tipo === 'CLOSE') {
                        $campos_requeridos[] = 'AUGBL';
                        $campos_requeridos[] = 'AUGDT';
                    }

                    foreach ($campos_requeridos as $campo) {
                        if (!is_array($partida) || !isset($partida[$campo]) || !is_scalar($partida[$campo])) {
                            throw new UnexpectedValueException('SAP devolvió una partida con datos incompletos.');
                        }
                    }
                    if (!is_numeric($partida['WRBTR'])) {
                        throw new UnexpectedValueException('SAP devolvió un importe inválido.');
                    }
                    if (
                        !preg_match('/^\d{1,20}$/', (string) $partida['BELNR'])
                        || !preg_match('/^\d{4}$/', (string) $partida['GJAHR'])
                        || (isset($partida['SGTXT']) && !is_scalar($partida['SGTXT']))
                    ) {
                        throw new UnexpectedValueException('SAP devolvió identificadores o texto inválidos para una partida.');
                    }
                    $ebeln = is_scalar($partida['EBELN'] ?? null)
                        ? trim((string) $partida['EBELN'])
                        : '';
                    if ($ebeln === '' || !preg_match('/^\d{10}$/', $ebeln)) {
                        $ekko = $partida['EKKO'] ?? null;
                        $ebeln = is_array($ekko) && is_scalar($ekko['EBELN'] ?? null)
                            ? trim((string) $ekko['EBELN'])
                            : '';
                    }
                    if ($ebeln === '' || !preg_match('/^\d{10}$/', $ebeln)) {
                        $descripcion = is_scalar($partida['SGTXT'] ?? null)
                            ? (string) $partida['SGTXT']
                            : '';
                        $ebeln = preg_match('/\b\d{10}\b/', $descripcion, $matches) === 1
                            ? $matches[0]
                            : '';
                    }
                    $partida['EBELN'] = $ebeln;

                    if (
                        trim((string) $partida['BUKRS']) !== $sociedad
                        || $normalizar_lifnr((string) $partida['LIFNR']) !== $normalizar_lifnr($proveedor_sap_id)
                    ) {
                        continue;
                    }
                    if ($ebeln === '') {
                        $partida['ESTADO_PARTIDA'] = $tipo === 'OPEN' ? 'Abierta' : 'Compensada';
                        $partidas_no_disponibles[] = $partida;
                    } elseif ($tipo === 'OPEN') {
                        $partidas_abiertas[] = $partida;
                    } else {
                        $partidas_cerradas[] = $partida;
                    }
                }
            }
        } catch (SapApiException $e) {
            error_log('SAP account statement request failed: ' . $e->getMessage());
            $status_code = $e->getHttpStatusCode();
            $error = $status_code === null
                ? 'No fue posible contactar el servicio SAP. Inténtelo de nuevo más tarde.'
                : "SAP devolvió un error (HTTP {$status_code}).";
        } catch (JsonException $e) {
            error_log('SAP account statement response was not valid JSON: ' . $e->getMessage());
            $error = 'SAP devolvió una respuesta con un formato no válido.';
        } catch (UnexpectedValueException $e) {
            error_log($e->getMessage());
            $error = 'SAP devolvió datos inválidos para el estado de cuenta.';
        } catch (RuntimeException $e) {
            error_log('SAP account statement request failed: ' . $e->getMessage());
            $error = 'No fue posible contactar el servicio SAP. Inténtelo de nuevo más tarde.';
        }
    }
}

$facturas_cargadas = [];
try {
    $stmt_facturas = $pdo->prepare(
        'SELECT belnr, gjahr, nombre_original
         FROM proveedor_facturas_sap
         WHERE bukrs = ? AND lifnr = ?'
    );
    $stmt_facturas->execute([$sociedad, $proveedor_sap_id]);
    foreach ($stmt_facturas->fetchAll(PDO::FETCH_ASSOC) as $factura) {
        $facturas_cargadas[$factura['gjahr'] . ':' . $factura['belnr']] = $factura['nombre_original'];
    }
} catch (PDOException $e) {
    error_log('Could not load SAP invoice attachments: ' . $e->getMessage());
    $error = 'No se pudo consultar el registro de facturas. Verifique que se haya aplicado la migración de facturas SAP.';
}

$firma_documento = static function (array $partida) use ($csrf_token, $sociedad, $proveedor_sap_id): string {
    $documento = implode('|', [
        $sociedad,
        $proveedor_sap_id,
        (string) $partida['BELNR'],
        (string) $partida['GJAHR'],
        (string) $partida['EBELN'],
    ]);
    return hash_hmac('sha256', $documento, $csrf_token);
};

require_once 'templates/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h2>Estado de cuenta</h2>
        <p class="text-muted mb-0">
            Proveedor: <?php echo $escape($proveedor_sap_id); ?>
            <span class="mx-2">|</span>
            Sociedad: <?php echo $escape($sociedad); ?>
        </p>
    </div>
</div>

<?php if (isset($_GET['success']) && is_string($_GET['success'])): ?>
    <div class="alert alert-success"><?php echo $escape($_GET['success']); ?></div>
<?php endif; ?>
<?php if (isset($_GET['error']) && is_string($_GET['error'])): ?>
    <div class="alert alert-danger"><?php echo $escape($_GET['error']); ?></div>
<?php endif; ?>

<div id="partidas-abiertas" class="card mb-4">
    <div class="card-header">
        <h3 class="h5 mb-0">Buscar partidas por fecha</h3>
    </div>
    <div class="card-body">
        <form action="index.php#resultado-partidas-abiertas" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrf_token); ?>">
            <div class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <label for="fecha_inicio" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo $escape($fecha_inicio); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="fecha_fin" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo $escape($fecha_fin); ?>" required>
                </div>
                <div class="col-md-4 mb-3 d-grid">
                    <button type="submit" class="btn btn-primary">Consultar partidas</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?php echo $escape($error); ?></div>
<?php endif; ?>

<?php if ($consultado && $error === ''): ?>
    <section id="resultado-partidas-abiertas" class="card mb-4">
        <div class="card-header">
            Partidas abiertas (<?php echo count($partidas_abiertas); ?>)
        </div>
        <div class="card-body">
            <?php if ($partidas_abiertas === []): ?>
                <p class="text-center text-muted mb-0">SAP no encontró partidas abiertas en el período seleccionado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Orden de compra</th>
                                <th>Ejercicio</th>
                                <th>Fecha contable</th>
                                <th>Descripción</th>
                                <th class="text-end">Importe</th>
                                <th>Factura</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidas_abiertas as $partida): ?>
                                <?php
                                $clave_factura = $partida['GJAHR'] . ':' . $partida['BELNR'];
                                $tiene_factura = isset($facturas_cargadas[$clave_factura]);
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($partida['EBELN'] !== ''): ?>
                                            <?php echo $escape($partida['EBELN']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $escape($partida['GJAHR']); ?></td>
                                    <td><?php echo $escape($formatear_fecha($partida['BUDAT'])); ?></td>
                                    <td><?php echo $escape($partida['SGTXT'] ?? ''); ?></td>
                                    <td class="text-end"><?php echo number_format((float) $partida['WRBTR'], 2, ',', '.'); ?> <?php echo $escape($partida['WAERS']); ?></td>
                                    <td>
                                        <?php if ($tiene_factura): ?>
                                            <a href="public/descargar_factura_sap.php?belnr=<?php echo rawurlencode((string) $partida['BELNR']); ?>&amp;gjahr=<?php echo rawurlencode((string) $partida['GJAHR']); ?>">
                                                <?php echo $escape($facturas_cargadas[$clave_factura]); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Sin archivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($partida['EBELN'] === ''): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="SAP no devolvió el número de orden de compra">
                                                OC no disponible
                                            </button>
                                        <?php else: ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#uploadInvoiceModal"
                                                data-belnr="<?php echo $escape($partida['BELNR']); ?>"
                                                data-gjahr="<?php echo $escape($partida['GJAHR']); ?>"
                                                data-ebeln="<?php echo $escape($partida['EBELN']); ?>"
                                                data-document-signature="<?php echo $escape($firma_documento($partida)); ?>"
                                            ><?php echo $tiene_factura ? 'Reemplazar factura' : 'Cargar factura'; ?></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="partidas-cerradas" class="card mb-4">
        <div class="card-header">
            Partidas compensadas (<?php echo count($partidas_cerradas); ?>)
        </div>
        <div class="card-body">
            <?php if ($partidas_cerradas === []): ?>
                <p class="text-center text-muted mb-0">SAP no encontró partidas compensadas en el período seleccionado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Orden de compra</th>
                                <th>Ejercicio</th>
                                <th>Fecha contable</th>
                                <th>Documento de compensación</th>
                                <th>Fecha de compensación</th>
                                <th class="text-end">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidas_cerradas as $partida): ?>
                                <tr>
                                    <td>
                                        <?php if ($partida['EBELN'] !== ''): ?>
                                            <?php echo $escape($partida['EBELN']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $escape($partida['GJAHR']); ?></td>
                                    <td><?php echo $escape($formatear_fecha($partida['BUDAT'])); ?></td>
                                    <td><?php echo $escape($partida['AUGBL']); ?></td>
                                    <td><?php echo $escape($formatear_fecha($partida['AUGDT'])); ?></td>
                                    <td class="text-end"><?php echo number_format((float) $partida['WRBTR'], 2, ',', '.'); ?> <?php echo $escape($partida['WAERS']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="partidas-no-disponibles" class="card">
        <div class="card-header">
            Partidas no disponibles (<?php echo count($partidas_no_disponibles); ?>)
        </div>
        <div class="card-body">
            <?php if ($partidas_no_disponibles === []): ?>
                <p class="text-center text-muted mb-0">Todas las partidas tienen una orden de compra disponible.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Documento</th>
                                <th>Ejercicio</th>
                                <th>Estado</th>
                                <th>Fecha contable</th>
                                <th>Descripción</th>
                                <th class="text-end">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidas_no_disponibles as $partida): ?>
                                <tr>
                                    <td><?php echo $escape($partida['BELNR']); ?></td>
                                    <td><?php echo $escape($partida['GJAHR']); ?></td>
                                    <td><?php echo $escape($partida['ESTADO_PARTIDA']); ?></td>
                                    <td><?php echo $escape($formatear_fecha($partida['BUDAT'])); ?></td>
                                    <td><?php echo $escape($partida['SGTXT'] ?? ''); ?></td>
                                    <td class="text-end"><?php echo number_format((float) $partida['WRBTR'], 2, ',', '.'); ?> <?php echo $escape($partida['WAERS']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<div class="modal fade" id="uploadInvoiceModal" tabindex="-1" aria-labelledby="uploadInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="public/subir_factura_sap.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="uploadInvoiceModalLabel">Cargar factura</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrf_token); ?>">
                    <input type="hidden" name="belnr" id="upload_invoice_belnr">
                    <input type="hidden" name="gjahr" id="upload_invoice_gjahr">
                    <input type="hidden" name="oc" id="upload_invoice_oc">
                    <input type="hidden" name="document_signature" id="upload_invoice_signature">
                    <input type="hidden" name="fecha_inicio" value="<?php echo $escape($fecha_inicio); ?>">
                    <input type="hidden" name="fecha_fin" value="<?php echo $escape($fecha_fin); ?>">
                    <p class="text-muted">Orden de compra: <strong id="upload_invoice_oc_label"></strong></p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="invoice_number" class="form-label">Número de factura</label>
                            <input class="form-control" type="text" id="invoice_number" name="factura_numero" maxlength="50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="invoice_net" class="form-label">Monto neto</label>
                            <input class="form-control" type="number" id="invoice_net" name="neto" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="invoice_date" class="form-label">Fecha del documento</label>
                            <input class="form-control" type="date" id="invoice_date" name="fecha_documento" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_payment_method" class="form-label">Método de pago</label>
                            <input class="form-control" type="text" id="invoice_payment_method" name="metodo_pago" maxlength="20" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="invoice_tax_indicator" class="form-label">Indicador de impuestos</label>
                            <input class="form-control" type="text" id="invoice_tax_indicator" name="indicador_impuesto" maxlength="20" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Ítem a facturar</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addInvoiceItem">Agregar posición</button>
                        </div>
                        <div id="invoiceItems"></div>
                    </div>
                    <p class="text-muted">Adjunte un archivo PDF de hasta 10 MB.</p>
                    <div class="mb-3">
                        <label for="invoice_file" class="form-label">Archivo de factura</label>
                        <input class="form-control" type="file" id="invoice_file" name="factura" accept=".pdf,application/pdf" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="debug_sap_response" name="debug_sap_response" value="1">
                        <label class="form-check-label" for="debug_sap_response">
                            Mostrar respuesta cruda de SAP (diagnóstico; detiene el guardado local)
                        </label>
                        <div class="form-text">Úselo una sola vez: SAP podría registrar la factura aunque no se guarde en el portal.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="submitInvoiceButton">Guardar factura</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="invoiceUploadLoading" class="invoice-loading-overlay" role="status" aria-live="polite" aria-atomic="true" hidden>
    <div class="invoice-loading-content">
        <div class="spinner-border text-primary" aria-hidden="true"></div>
        <p class="mb-0 mt-3">Procesando información, por favor espere...</p>
    </div>
</div>

<script>
const invoiceItems = document.getElementById('invoiceItems');
let invoiceItemIndex = 0;

function addInvoiceItem() {
    const item = document.createElement('div');
    item.className = 'row align-items-end mb-2';
    item.innerHTML = `
        <div class="col-md-4">
            <label class="form-label">Posición OC</label>
            <input class="form-control" type="text" name="posiciones[${invoiceItemIndex}][POSOC]" inputmode="numeric" pattern="[0-9]{1,5}" maxlength="5" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Unidad</label>
            <input class="form-control" type="text" name="posiciones[${invoiceItemIndex}][MEINS]" maxlength="3" pattern="[A-Za-z0-9]{1,3}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Cantidad</label>
            <input class="form-control" type="number" name="posiciones[${invoiceItemIndex}][MENGE]" min="0.001" step="0.001" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Importe neto</label>
            <input class="form-control" type="number" name="posiciones[${invoiceItemIndex}][NETWR]" min="0.01" step="0.01" required>
        </div>
        <div class="col-md-2 d-grid">
            <button type="button" class="btn btn-outline-danger" aria-label="Quitar posición">Quitar</button>
        </div>`;
    item.querySelector('button').addEventListener('click', () => {
        item.remove();
        if (invoiceItems.children.length === 0) {
            addInvoiceItem();
        }
    });
    invoiceItems.appendChild(item);
    invoiceItemIndex += 1;
}

document.getElementById('addInvoiceItem').addEventListener('click', addInvoiceItem);
addInvoiceItem();

document.getElementById('uploadInvoiceModal').addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    document.getElementById('upload_invoice_belnr').value = button.dataset.belnr;
    document.getElementById('upload_invoice_gjahr').value = button.dataset.gjahr;
    document.getElementById('upload_invoice_oc').value = button.dataset.ebeln;
    document.getElementById('upload_invoice_oc_label').textContent = button.dataset.ebeln;
    document.getElementById('upload_invoice_signature').value = button.dataset.documentSignature;
});

const invoiceUploadForm = document.querySelector('#uploadInvoiceModal form');
invoiceUploadForm.addEventListener('submit', (event) => {
    if (invoiceUploadForm.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }

    invoiceUploadForm.dataset.submitting = 'true';
    invoiceUploadForm.setAttribute('aria-busy', 'true');
    document.getElementById('submitInvoiceButton').disabled = true;
    document.getElementById('invoiceUploadLoading').hidden = false;
});
</script>

<?php require_once 'templates/footer.php'; ?>
