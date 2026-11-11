<?php
session_start();

if (!isset($_SESSION['proveedor_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $clave_actual = $_POST['clave_actual'] ?? '';
    $clave_nueva = $_POST['clave_nueva'] ?? '';
    $confirmar_clave = $_POST['confirmar_clave'] ?? '';

    if (!is_string($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'La solicitud expiró. Recargue la página e inténtelo nuevamente.';
    } elseif (!is_string($clave_actual) || !is_string($clave_nueva) || !is_string($confirmar_clave)) {
        $error = 'Los datos enviados no son válidos.';
    } elseif ($clave_actual === '' || $clave_nueva === '' || $confirmar_clave === '') {
        $error = 'Complete todos los campos de contraseña.';
    } elseif (strlen($clave_nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif (!hash_equals($clave_nueva, $confirmar_clave)) {
        $error = 'La confirmación no coincide con la nueva contraseña.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT clave_estandar FROM proveedores WHERE id = ? AND activo = 1');
            $stmt->execute([$_SESSION['proveedor_id']]);
            $stored_password = $stmt->fetchColumn();

            if ($stored_password === false) {
                $error = 'No se encontró una cuenta activa para este proveedor.';
            } else {
                $stored_password = (string) $stored_password;
                $password_info = password_get_info($stored_password);
                $current_password_valid = $stored_password !== ''
                    && ($password_info['algoName'] !== 'unknown'
                        ? password_verify($clave_actual, $stored_password)
                        : hash_equals($stored_password, $clave_actual));

                if (!$current_password_valid) {
                    $error = 'La contraseña actual no es correcta.';
                } else {
                    $hashed_password = password_hash($clave_nueva, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE proveedores SET clave_estandar = ? WHERE id = ?');
                    $stmt->execute([$hashed_password, $_SESSION['proveedor_id']]);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $success = 'La contraseña se actualizó correctamente.';
                }
            }
        } catch (PDOException $e) {
            error_log('Database error while changing provider password: ' . $e->getMessage());
            $error = 'No se pudo actualizar la contraseña. Inténtelo de nuevo más tarde.';
        }
    }
}

require_once '../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h2 class="h4 mb-0">Cambiar contraseña</h2>
            </div>
            <div class="card-body">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($success !== ''): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <p class="text-muted">
                    Si aún utiliza la clave inicial, ingrese su número de proveedor como contraseña actual.
                </p>
                <form action="cambiar_clave.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label for="clave_actual" class="form-label">Contraseña actual</label>
                        <input type="password" class="form-control" id="clave_actual" name="clave_actual" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label for="clave_nueva" class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control" id="clave_nueva" name="clave_nueva" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmar_clave" class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" class="form-control" id="confirmar_clave" name="confirmar_clave" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
