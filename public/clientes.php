<?php
session_start();

// si el proveedor no está logueado redirige al login
if (!isset($_SESSION['proveedor_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';

$action = $_GET['action'] ?? 'list'; 
$proveedor_id = $_SESSION['proveedor_id'];
$cliente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// variables para el formulario
$nombre = '';
$email = '';
$telefono = '';
$direccion = '';
$sap_api_url = '';
$sap_api_username = '';
$sap_api_password = '';
$error = '';

// Lógica para Agregar 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_post = $_POST['action'];
    $cliente_id_post = $_POST['cliente_id'] ?? null;

    
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $sap_api_url = trim($_POST['sap_api_url']);
    $sap_api_username = trim($_POST['sap_api_username']);
    $sap_api_password = trim($_POST['sap_api_password']);

    if (empty($nombre) || empty($email)) {
        $error = 'El nombre y el email son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo electrónico no es válido.';
    } else {
        try { // nueva empresa cliente
            if ($action_post === 'add') {
                //Verificar si la empresa ya existe globalmente (por email)
                $stmt_check = $pdo->prepare("SELECT id FROM empresas_cliente WHERE email = ?");
                $stmt_check->execute([$email]);
                $existing_empresa_id = $stmt_check->fetchColumn();

                $pdo->beginTransaction();

                if ($existing_empresa_id) {
                    // Si existe, usamos ese ID
                    $new_empresa_id = $existing_empresa_id;
                    
                    // Verificar si ya está asociada a este proveedor para no duplicar error
                    $stmt_link_check = $pdo->prepare("SELECT 1 FROM empresa_proveedores WHERE empresa_id = ? AND proveedor_id = ?");
                    $stmt_link_check->execute([$new_empresa_id, $proveedor_id]);
                    if ($stmt_link_check->fetch()) {
                        throw new Exception("Esta empresa ya se encuentra en su lista de clientes.");
                    }
                } else {
                    // Si no existe se crea
                    $sql = "INSERT INTO empresas_cliente (proveedor_id, nombre, email, telefono, direccion, sap_api_url, sap_api_username, sap_api_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$proveedor_id, $nombre, $email, $telefono, $direccion, $sap_api_url, $sap_api_username, $sap_api_password]);
                    $new_empresa_id = $pdo->lastInsertId();
                }

                // Asociar al proveedor que la creó en la tabla pivote
                $stmt_assoc = $pdo->prepare("INSERT INTO empresa_proveedores (empresa_id, proveedor_id) VALUES (?, ?)");
                $stmt_assoc->execute([$new_empresa_id, $proveedor_id]);
                $pdo->commit();
                header("Location: index.php?success=" . urlencode('Empresa agregada exitosamente.'));
                exit();
            } elseif ($action_post === 'edit' && $cliente_id_post) {

                // Para la edición solo actualizamos la contraseña si se ha introducido una nueva
                $stmt_pass = $pdo->prepare("SELECT sap_api_password FROM empresas_cliente WHERE id = ?");
                $stmt_pass->execute([$cliente_id_post]);
                $existing_password = $stmt_pass->fetchColumn();

                if (empty($sap_api_password)) {
                    $password_to_save = $existing_password;
                } else {
                    $password_to_save = $sap_api_password;
                }

                // Actualizar empresa cliente existente
                $sql = "UPDATE empresas_cliente ec JOIN empresa_proveedores ep ON ec.id = ep.empresa_id SET ec.nombre = ?, ec.email = ?, ec.telefono = ?, ec.direccion = ?, ec.sap_api_url = ?, ec.sap_api_username = ?, ec.sap_api_password = ? WHERE ec.id = ? AND ep.proveedor_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $email, $telefono, $direccion, $sap_api_url, $sap_api_username, $password_to_save, $cliente_id_post, $proveedor_id]);
                header("Location: index.php?success=" . urlencode('Cliente actualizado exitosamente.'));
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Error al guardar la empresa. Es posible que el email ya exista. ' . $e->getMessage();
        }
    }
}

// Lógica para Editar y Eliminar 
if ($action === 'edit' && $cliente_id) {
    $stmt = $pdo->prepare("SELECT ec.* FROM empresas_cliente ec JOIN empresa_proveedores ep ON ec.id = ep.empresa_id WHERE ec.id = ? AND ep.proveedor_id = ?");
    $stmt->execute([$cliente_id, $proveedor_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cliente) {
        $nombre = $cliente['nombre'];
        $email = $cliente['email'];
        $telefono = $cliente['telefono'];
        $direccion = $cliente['direccion'];
        $sap_api_url = $cliente['sap_api_url'];
        $sap_api_username = $cliente['sap_api_username'];
        $sap_api_password = $cliente['sap_api_password'];
    } else {
        // Si el cliente no existe o no pertenece al proveedor redirige
        header("Location: index.php?error=" . urlencode('Empresa no encontrada.'));
        exit();
    }
} elseif ($action === 'delete' && $cliente_id) {
    try {
        $pdo->beginTransaction();
        //Obtener todos los IDs de clientes finales de esta empresa
        $stmt_clientes = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id = ?");
        $stmt_clientes->execute([$cliente_id]);
        $clientes_ids = $stmt_clientes->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($clientes_ids)) {
            // Solo elimina facturas que pertenezcan a ESTE proveedor
            $in_placeholders = implode(',', array_fill(0, count($clientes_ids), '?'));
            
            // Combinamos los IDs de clientes con el ID del proveedor para la consulta
            $params = array_merge($clientes_ids, [$proveedor_id]);
            
            $stmt_delete_facturas = $pdo->prepare("DELETE FROM facturas WHERE cliente_id IN ($in_placeholders) AND proveedor_id = ?");
            $stmt_delete_facturas->execute($params);
        }
        // Solo se elimina el vínculo no se borra la empresa de la BD
        // para no afectar a otros proveedores que la usen
        $stmt_delete_empresa = $pdo->prepare("DELETE FROM empresa_proveedores WHERE empresa_id = ? AND proveedor_id = ?");
        $stmt_delete_empresa->execute([$cliente_id, $proveedor_id]);

        $pdo->commit();
        header("Location: index.php?success=" . urlencode('Empresa eliminada exitosamente.'));
        exit();
    } catch (PDOException $e) {
        header("Location: index.php?error=" . urlencode('Error al eliminar el cliente. Puede tener facturas asociadas.'));
        exit();
    }
}

require_once '../templates/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?php echo ($action === 'edit') ? 'Editar Empresa Cliente' : 'Agregar Nueva Empresa Cliente'; ?></h2>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="clientes.php" method="POST">
            <!-- Campos ocultos para la lógica de POST -->
            <input type="hidden" name="action" value="<?php echo ($action === 'edit') ? 'edit' : 'add'; ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre de la Empresa</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="mb-3">
                <label for="telefono" class="form-label">Teléfono</label>
                <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>">
            </div>

            <div class="mb-3">
                <label for="direccion" class="form-label">Dirección</label>
                <textarea class="form-control" id="direccion" name="direccion" rows="3"><?php echo htmlspecialchars($direccion); ?></textarea>
            </div>
            
            <hr>
            <h4>Configuración de API SAP</h4>
            <div class="mb-3">
                <label for="sap_api_url" class="form-label">URL Base de la API SAP</label>
                <input type="url" class="form-control" id="sap_api_url" name="sap_api_url" value="<?php echo htmlspecialchars($sap_api_url); ?>" placeholder="Ej: http://aps4dapp.agroinlaca.com:9100/sap/bc/srt/zjson_portal?sap-client=400">
            </div>
            <div class="mb-3">
                <label for="sap_api_username" class="form-label">Usuario API SAP</label>
                <input type="text" class="form-control" id="sap_api_username" name="sap_api_username" value="<?php echo htmlspecialchars($sap_api_username); ?>">
            </div>
            <div class="mb-3">
                <label for="sap_api_password" class="form-label">Contraseña API SAP</label>
                <input type="password" class="form-control" id="sap_api_password" name="sap_api_password" value="" placeholder="Dejar en blanco para no cambiar">
            </div>

            <button type="submit" class="btn btn-primary"><?php echo ($action === 'edit') ? 'Actualizar Empresa' : 'Guardar Empresa'; ?></button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>