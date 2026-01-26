<?php
session_start();

if (!isset($_SESSION['proveedor_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';

$proveedor_id = $_SESSION['proveedor_id'];
$error = '';
$success = '';

// Cargar datos actuales del proveedor
try {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
    $stmt->execute([$proveedor_id]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar los datos del proveedor: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $rif = trim($_POST['rif']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $logo_actual = $proveedor['logo_url'];

    // Manejo de la subida del logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/img/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        // Borrar logo anterior si existe
        if (!empty($logo_actual) && file_exists($upload_dir . $logo_actual)) {
            unlink($upload_dir . $logo_actual);
        }
        $file_info = pathinfo($_FILES['logo']['name']);
        $new_filename = 'logo_' . $proveedor_id . '_' . time() . '.' . $file_info['extension'];
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_filename)) {
            $logo_actual = $new_filename;
        } else {
            $error = "Error al subir el nuevo logo.";
        }
    }

    if (empty($error)) {
        try {
            $sql = "UPDATE proveedores SET nombre = ?, rif = ?, email = ?, telefono = ?, direccion = ?, logo_url = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $rif, $email, $telefono, $direccion, $logo_actual, $proveedor_id]);
            
            // Actualizar nombre en la sesión
            $_SESSION['proveedor_nombre'] = $nombre;
            $success = "Perfil actualizado exitosamente.";
            // Recargar los datos para mostrarlos actualizados en el formulario
            $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
            $stmt->execute([$proveedor_id]);
            $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $error = "Error al actualizar el perfil. El RIF o Email podrían estar duplicados.";
        }
    }
}

require_once '../templates/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Perfil del Proveedor</h2>
    </div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <form action="perfil_proveedor.php" method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3"><label for="nombre" class="form-label">Nombre</label><input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($proveedor['nombre']); ?>" required></div>
                    <div class="mb-3"><label for="rif" class="form-label">RIF</label><input type="text" class="form-control" id="rif" name="rif" value="<?php echo htmlspecialchars($proveedor['rif']); ?>" required></div>
                    <div class="mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($proveedor['email']); ?>" required></div>
                    <div class="mb-3"><label for="telefono" class="form-label">Teléfono</label><input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($proveedor['telefono']); ?>"></div>
                    <div class="mb-3"><label for="direccion" class="form-label">Dirección</label><textarea class="form-control" id="direccion" name="direccion" rows="3"><?php echo htmlspecialchars($proveedor['direccion']); ?></textarea></div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="logo" class="form-label">Logo de la Empresa</label>
                        <?php if (!empty($proveedor['logo_url'])): ?>
                            <img src="img/<?php echo htmlspecialchars($proveedor['logo_url']); ?>" alt="Logo actual" class="img-thumbnail mb-2" style="max-width: 150px;">
                            <p class="text-muted small">Logo actual</p>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                        <div class="form-text">Subir un nuevo logo lo reemplazará.</div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
            <a href="../index.php" class="btn btn-secondary">Volver</a>
        </form>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>