<?php
session_start();

// Si el proveedor ya está logueado redirige a la página principal
if (isset($_SESSION['proveedor_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["rif"])) || empty(trim($_POST["password"]))) {
        $error = "Por favor, ingrese su RIF y contraseña.";
    } else {
        $rif = trim($_POST["rif"]);
        $password = trim($_POST["password"]);

        try {
            $sql = "SELECT id, nombre, rif, password FROM proveedores WHERE rif = :rif";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['rif' => $rif]);

            if ($stmt->rowCount() == 1) {
                $proveedor = $stmt->fetch();
                // Comprobación de la contraseña 
                if ($password === $proveedor['password']) {
                    // Contraseña correcta, iniciar sesión
                    $_SESSION['proveedor_id'] = $proveedor['id'];
                    $_SESSION['proveedor_nombre'] = $proveedor['nombre'];
                    
                    header("Location: ../index.php");
                    exit();
                } else {
                    $error = "La contraseña que ingresaste no es válida.";
                }
            } else {
                $error = "No se encontró ninguna cuenta con ese RIF.";
            }
        } catch (PDOException $e) {
            $error = "Error del sistema. Inténtelo de nuevo más tarde.";
        }
    }
}

require_once '../templates/header_login.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header text-center">
                <h3>Inicio de Sesión</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form action="login.php" method="post">
                    <div class="mb-3">
                        <label for="rif" class="form-label">RIF</label>
                        <input type="text" name="rif" id="rif" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Ingresar</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <p>¿No tienes una cuenta? <a href="registro_proveedor.php">Regístrate aquí</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>