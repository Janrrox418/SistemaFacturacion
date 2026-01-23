<?php
require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $rif = trim($_POST['rif']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $telefono = null;
    $direccion = null; 

    if (empty($nombre) || empty($rif) || empty($email) || empty($password)) {
        $error = "Los campos Nombre, RIF, Email y Contraseña son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del correo electrónico no es válido.";
    } else {
        try {
            // Verificar si el RIF o el Email ya existen
            $stmt = $pdo->prepare("SELECT id FROM proveedores WHERE rif = ? OR email = ?");
            $stmt->execute([$rif, $email]);
            if ($stmt->fetch()) {
                $error = "Ya existe un proveedor con ese RIF o Email.";
            } else {
                $sql = "INSERT INTO proveedores (nombre, rif, email, password, telefono, direccion) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql); 
                $stmt->execute([$nombre, $rif, $email, $password, $telefono, $direccion]);
                $success = "¡Registro exitoso! Ahora puedes iniciar sesión.";
            }
        } catch (PDOException $e) {
            $error = "Error del sistema al intentar registrar. Por favor, inténtelo de nuevo.";
        }
    }
}

require_once '../templates/header_login.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header text-center">
                <h3>Registro de Proveedor</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-primary">Ir a Inicio de Sesión</a>
                    </div>
                <?php else: ?>
                <form action="registro_proveedor.php" method="post">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de la Empresa</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="rif" class="form-label">RIF</label>
                        <input type="text" name="rif" id="rif" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <!-- Los campos opcionales como teléfono y dirección se pueden agregar aquí
                         o mejor aún, en la pantalla de "Mi Perfil" después del registro.
                    -->
                     <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Registrarse</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia Sesión aquí</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>