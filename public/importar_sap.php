<?php
session_start();

if (!isset($_SESSION['proveedor_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';

$proveedor_id = $_SESSION['proveedor_id'];
$success_msg = '';
$error_msg = '';

// Obtener las empresas cliente del proveedor para el selector
$stmt_empresas_cliente = $pdo->prepare(
    "SELECT ec.id, ec.nombre FROM empresas_cliente ec
     JOIN empresa_proveedores ep ON ec.id = ep.empresa_id
     WHERE ep.proveedor_id = ? ORDER BY ec.nombre ASC"
);
$stmt_empresas_cliente->execute([$proveedor_id]);
$empresas_cliente_list = $stmt_empresas_cliente->fetchAll(PDO::FETCH_ASSOC);

$selected_empresa_id = $_POST['empresa_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';

    if (empty($fecha_inicio) || empty($fecha_fin)) {
        $error_msg = "Debe seleccionar una fecha de inicio y una fecha de fin.";
    } else {
        if (empty($selected_empresa_id)) {
            $error_msg = "Debe seleccionar una Empresa Cliente para la importación.";
        } else {

            // Obtener la configuración de la API para la empresa seleccionada
            $stmt_api_config = $pdo->prepare(
                "SELECT ec.sap_api_url, ec.sap_api_username, ec.sap_api_password 
                 FROM empresas_cliente ec JOIN empresa_proveedores ep ON ec.id = ep.empresa_id
                 WHERE ec.id = ? AND ep.proveedor_id = ?"
            );
            $stmt_api_config->execute([$selected_empresa_id, $proveedor_id]);
            $api_config = $stmt_api_config->fetch(PDO::FETCH_ASSOC);

            if (!$api_config || empty($api_config['sap_api_url']) || empty($api_config['sap_api_username']) || empty($api_config['sap_api_password'])) {
                $error_msg = "La Empresa Cliente seleccionada no tiene configurada la URL, usuario o contraseña de la API SAP.";
            } else {
        // Formatear las fechas al formato YYYYMMDD que requiere la API
        $fecha_inicio_api = date('Ymd', strtotime($fecha_inicio));
        $fecha_fin_api = date('Ymd', strtotime($fecha_fin));

        // CONFIGURACIÓN DE LA API 
            $api_url = $api_config['sap_api_url'] . "&FECHAI={$fecha_inicio_api}&FECHAF={$fecha_fin_api}";
            $username = $api_config['sap_api_username'];
            $password = $api_config['sap_api_password'];

            // Inicializar cURL
            $ch = curl_init();

            // Opciones de cURL
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password"); 
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); 

            // Ejecutar la petición
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);

            // Procesar la respuesta
            if ($curl_error) {
                $error_msg = "Error de cURL al conectar con la API: " . $curl_error;
            } elseif ($http_code !== 200) {
                $error_msg = "La API devolvió un error. Código HTTP: " . $http_code . ". Respuesta: " . htmlspecialchars($response);
            } else {
                $data = json_decode($response, true);

                // Ajustamos la validación a la nueva estructura del JSON.
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    $pdo->beginTransaction();
                    try {
                        $clientes_importados = 0;
                        $facturas_importadas = 0;

                        foreach ($data as $entry) {
                            // Validar la entrada
                            if (!isset($entry['EKKO']) || !isset($entry['EKPO'])) {
                                continue; 
                            }
                            $doc = $entry['EKKO'];
                            $items_sap = $entry['EKPO'];

                            // --- MAPEO DE DATOS ---

                            // El RIF del cliente final viene en LIFNR_RFC o LIFNR
                            $sap_cliente_rif = !empty($entry['LIFNR_RFC']) ? $entry['LIFNR_RFC'] : $doc['LIFNR'];
                            // El RIF de la empresa a la que pertenece la factura (ej. Agroinlaca)
                            
                            // Generamos datos para el cliente final a partir de su RIF
                            $cliente_final_email = strtolower($sap_cliente_rif) . '@cliente-sap.com'; // Email temporal único
                            $cliente_final_nombre = 'Cliente SAP ' . $sap_cliente_rif; // Nombre temporal

                            $factura_numero = $doc['EBELN'];
                            // La fecha viene como YYYYMMDD, la convertimos a YYYY-MM-DD
                            $factura_fecha_emision = date('Y-m-d', strtotime($doc['BEDAT']));
                            // La fecha de vencimiento la calculamos a 30 días.
                            $factura_fecha_vencimiento = date('Y-m-d', strtotime($factura_fecha_emision . ' +30 days'));

                            // Calculamos el total a partir de los ítems.
                            $factura_total = 0;
                            $factura_items = [];
                            foreach ($items_sap as $item_sap) {
                                $precio_unitario = 0; // No hay precio en el JSON, se asigna 0.
                                $cantidad = (float)($item_sap['MENGE'] ?? 0);
                                $factura_total += $cantidad * $precio_unitario;
                                $factura_items[] = [
                                    'descripcion' => $item_sap['TXZ01'],
                                    'cantidad' => $cantidad,
                                    'precio_unitario' => $precio_unitario
                                ];
                            }
                            // --- FIN DEL MAPEO ---

                            // 1. Insertar o encontrar el CLIENTE FINAL por email dentro de la EMPRESA seleccionada
                            // Usamos $selected_empresa_id directamente
                            $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ? AND empresa_id = ?");
                            $stmt->execute([$cliente_final_email, $selected_empresa_id]);
                            $cliente_id = $stmt->fetchColumn();

                            if (!$cliente_id) {
                                $sql = "INSERT INTO clientes (empresa_id, nombre, email) VALUES (?, ?, ?)";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$selected_empresa_id, $cliente_final_nombre, $cliente_final_email]);
                                $cliente_id = $pdo->lastInsertId();
                                $clientes_importados++;
                            }

                            // 2. Insertar la factura
                            $sql = "INSERT INTO facturas (cliente_id, proveedor_id, numero_factura, fecha_emision, fecha_vencimiento, estado, total) VALUES (?, ?, ?, ?, ?, 'no_pagada', ?)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$cliente_id, $proveedor_id, $factura_numero, $factura_fecha_emision, $factura_fecha_vencimiento, $factura_total]);
                            $factura_id = $pdo->lastInsertId();
                            $facturas_importadas++;

                            // 3. Insertar los ítems de la factura
                            if (!empty($factura_items)) {
                                $sql_items = "INSERT INTO factura_items (factura_id, descripcion, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
                                $stmt_items = $pdo->prepare($sql_items);
                                foreach ($factura_items as $item) {
                                    $stmt_items->execute([
                                        $factura_id, 
                                        $item['descripcion'], 
                                        $item['cantidad'], 
                                        $item['precio_unitario']
                                    ]);
                                }
                            }
                        }

                        $pdo->commit();
                        $success_msg = "Importación desde SAP completada. Clientes nuevos: $clientes_importados. Facturas nuevas: $facturas_importadas.";

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_msg = "Error durante la importación: " . $e->getMessage();
                    }
                } else {
                    $error_msg = 'La respuesta de la API no es un JSON válido o no tiene la estructura esperada (array de objetos con clave "EKKO").';
                }
            }
        }
    }
    }
}

require_once '../templates/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Importar Documentos desde SAP</h2>
    </div>
    <div class="card-body">
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form action="importar_sap.php" method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="empresa_id" class="form-label">Empresa Cliente para Importar</label>
                    <select class="form-select" id="empresa_id" name="empresa_id" required>
                        <option value="">Seleccione una empresa</option>
                        <?php foreach ($empresas_cliente_list as $empresa_cliente): ?>
                            <option value="<?php echo htmlspecialchars($empresa_cliente['id']); ?>" <?php echo ($selected_empresa_id == $empresa_cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($empresa_cliente['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <p class="text-muted">
                El proceso se conectará a la API de SAP para buscar órdenes de compra en el rango de fechas seleccionado.
                Las órdenes se asociarán a la "Empresa Cliente" seleccionada y se crearán los "Clientes Finales" si no existen.
                Se permite que diferentes proveedores importen la misma orden de compra.
            </p>

            <button type="submit" class="btn btn-primary" onclick="this.disabled=true;this.innerHTML='Importando...';this.form.submit();">
                <i class="fas fa-download me-2"></i> Iniciar Importación desde SAP
            </button>
            <a href="../index.php" class="btn btn-secondary">Volver al inicio</a>
        </form>
    </div>
</div>

<?php
require_once '../templates/footer.php';
?>
