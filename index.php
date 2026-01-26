<?php
session_start();

// Verificar si el proveedor está logueado, si no, redirigir a la página de login
if (!isset($_SESSION['proveedor_id'])) {
    header("Location: public/login.php");
    exit();
}

// configuración de la base de datos
require_once 'config/database.php';
// cabecera de la página
require_once 'templates/header.php';

$proveedor_id = $_SESSION['proveedor_id'];

// --- Dashboard ---
try {
    // Se buscan facturas del proveedor que estén 'no_pagada' y cuya fecha de vencimiento ya pasó, a través de las empresas.
    $stmt_update_vencidas = $pdo->prepare(
        "UPDATE facturas f 
         JOIN clientes c ON f.cliente_id = c.id
         JOIN empresas_cliente ec ON c.empresa_id = ec.id
         SET f.estado = 'vencida' 
         WHERE ec.proveedor_id = ? AND f.estado = 'no_pagada' AND f.fecha_vencimiento < CURDATE()"
    );
    $stmt_update_vencidas->execute([$proveedor_id]);

    // Total Facturado
    $stmt_total = $pdo->prepare("SELECT SUM(f.total) FROM facturas f JOIN clientes c ON f.cliente_id = c.id JOIN empresas_cliente ec ON c.empresa_id = ec.id WHERE ec.proveedor_id = ?");
    $stmt_total->execute([$proveedor_id]);
    $total_facturado = $stmt_total->fetchColumn() ?: 0;

    // Órdenes Vencidas
    $stmt_vencidas = $pdo->prepare("SELECT COUNT(f.id) FROM facturas f JOIN clientes c ON f.cliente_id = c.id JOIN empresas_cliente ec ON c.empresa_id = ec.id WHERE ec.proveedor_id = ? AND f.estado = 'vencida'");
    $stmt_vencidas->execute([$proveedor_id]);
    $ordenes_vencidas = $stmt_vencidas->fetchColumn() ?: 0;

    // Total de Empresas Cliente
    $stmt_clientes = $pdo->prepare("SELECT COUNT(id) FROM empresas_cliente WHERE proveedor_id = ?");
    $stmt_clientes->execute([$proveedor_id]);
    $total_empresas = $stmt_clientes->fetchColumn() ?: 0;

    // Obtener la lista de empresas cliente
    $stmt = $pdo->prepare(
        "SELECT ec.id, ec.nombre, ec.email, ec.telefono FROM empresas_cliente ec
         JOIN empresa_proveedores ep ON ec.id = ep.empresa_id
         WHERE ep.proveedor_id = ? ORDER BY ec.nombre ASC"
    );
    $stmt->execute([$proveedor_id]);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error al cargar los datos del dashboard: ' . $e->getMessage() . '</div>';
}

// Mostrar mensajes de éxito o error pasados por URL
$success_msg = $_GET['success'] ?? null;
$error_msg = $_GET['error'] ?? null;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Dashboard de <?php echo htmlspecialchars($_SESSION['proveedor_nombre']); ?></h2>
</div>

<div class="row mb-4">
    <div class="col-md-3"><div class="card text-white bg-primary"><div class="card-body"><h5 class="card-title"><?php echo number_format($total_facturado, 2); ?> $</h5><p class="card-text">Total en Órdenes</p></div></div></div>
    <div class="col-md-3"><div class="card text-white bg-danger"><div class="card-body"><h5 class="card-title"><?php echo $ordenes_vencidas; ?></h5><p class="card-text">Órdenes Vencidas</p></div></div></div>
    <div class="col-md-3"><div class="card text-dark bg-light"><div class="card-body"><h5 class="card-title"><?php echo $total_empresas; ?></h5><p class="card-text">Total de Empresas Cliente</p></div></div></div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Listado de Empresas Cliente</span>
        <a href="public/clientes.php?action=add" class="btn btn-primary btn-sm">Agregar Nueva Empresa</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email de Contacto</th>
                        <th>Teléfono de Contacto</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($empresas)): ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay empresas cliente registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($empresas as $empresa): ?>
                            <tr>
                                <td>
                                    <a href="public/facturas.php?empresa_id=<?php echo $empresa['id']; ?>"><?php echo htmlspecialchars($empresa['nombre']); ?></a>
                                </td>
                                <td><?php echo htmlspecialchars($empresa['email']); ?></td>
                                <td><?php echo htmlspecialchars($empresa['telefono']); ?></td>
                                <td class="text-end">
                                    <a href="public/facturas.php?empresa_id=<?php echo $empresa['id']; ?>" class="btn btn-sm btn-info">Ver Órdenes</a>
                                    <a href="public/clientes.php?action=edit&id=<?php echo $empresa['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <a href="public/clientes.php?action=delete&id=<?php echo $empresa['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta empresa y todos sus clientes y facturas asociadas?');">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once '../templates/footer.php';
?>