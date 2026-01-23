<?php
session_start();

if (!isset($_SESSION['proveedor_id'])) {
    header("Location: login.php");
    exit();
}

// Definir acciones para clientes
$action_cliente = $_GET['action_cliente'] ?? null; // add, edit, delete

require_once '../config/database.php';

$proveedor_id = $_SESSION['proveedor_id'];
$empresa_id = filter_input(INPUT_GET, 'empresa_id', FILTER_VALIDATE_INT);

// Si no hay un ID de empresa, no podemos continuar.
if (!$empresa_id) {
    header("Location: index.php");
    exit();
}

// Verifica que la empresa cliente pertenece al proveedor
try {
    $stmt = $pdo->prepare(
        "SELECT ec.* FROM empresas_cliente ec JOIN empresa_proveedores ep ON ec.id = ep.empresa_id WHERE ec.id = ? AND ep.proveedor_id = ?"
    );
    $stmt->execute([$empresa_id, $proveedor_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        // Si la empresa no existe o no pertenece al proveedor, se sale.
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error al verificar la empresa: " . $e->getMessage());
}

$stmt_facturas = $pdo->prepare(
    "SELECT f.*, c.id as cliente_id_factura, c.nombre as cliente_nombre_final, c.email as cliente_email_final
     FROM facturas f 
     JOIN clientes c ON f.cliente_id = c.id 
     WHERE c.empresa_id = ? AND f.proveedor_id = ?
     ORDER BY f.fecha_emision DESC"
);
$stmt_facturas->execute([$empresa_id, $proveedor_id]);
$todas_las_facturas = $stmt_facturas->fetchAll(PDO::FETCH_ASSOC);

// Separar facturas por secciones
$facturas_proceso = [];
$facturas_pagadas = [];
$facturas_vencidas = [];

foreach ($todas_las_facturas as $f) {
    if ($f['estado'] === 'pagada') {
        $facturas_pagadas[] = $f;
    } elseif ($f['estado'] === 'vencida') {
        $facturas_vencidas[] = $f;
    } else {
        $facturas_proceso[] = $f; // 'no_pagada' equivale a En Proceso
    }
}

require_once '../templates/header.php';

$success_msg = $_GET['success'] ?? null;
$error_msg = $_GET['error'] ?? null;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Órdenes de Compra de: <?php echo htmlspecialchars($empresa['nombre']); ?></h2>
        <a href="index.php" class="btn btn-sm btn-secondary">Volver a Empresas</a>
    </div>
</div>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-light">
        <ul class="nav nav-tabs card-header-tabs" id="facturasTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="proceso-tab" data-bs-toggle="tab" data-bs-target="#proceso" type="button" role="tab" aria-controls="proceso" aria-selected="true">En Proceso (<?php echo count($facturas_proceso); ?>)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pagada-tab" data-bs-toggle="tab" data-bs-target="#pagada" type="button" role="tab" aria-controls="pagada" aria-selected="false">Pagadas (<?php echo count($facturas_pagadas); ?>)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vencida-tab" data-bs-toggle="tab" data-bs-target="#vencida" type="button" role="tab" aria-controls="vencida" aria-selected="false">Vencidas (<?php echo count($facturas_vencidas); ?>)</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="facturasTabsContent">
            
            <?php 
            // Función auxiliar para renderizar la tabla
            function renderFacturasTable($facturas, $empresa_id, $tipo) {
                if (empty($facturas)) {
                    echo '<p class="text-center py-3 text-muted">No hay órdenes en esta sección.</p>';
                    return;
                }
                ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead>
                            <tr>
                                <th># Orden</th>
                                <th>Cliente Final</th>
                                <th>Fecha Emisión</th>
                                <th>Total</th>
                                <th>Factura</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facturas as $factura): ?>
                                <?php
                                    $row_class = '';
                                    if ($factura['estado'] === 'pagada') {
                                        $row_class = 'table-success'; // Verde
                                    } elseif ($factura['estado'] === 'vencida') {
                                        $row_class = 'table-danger'; // Rojo
                                    } elseif ($factura['estado'] === 'no_pagada') {
                                        $row_class = 'table-warning'; // Naranja
                                    }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo htmlspecialchars($factura['numero_factura']); ?></td>
                                    <td><?php echo htmlspecialchars($factura['cliente_nombre_final']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($factura['fecha_emision'])); ?></td>
                                    <td><?php echo number_format($factura['total'], 2); ?> $</td>
                                    <td>
                                        <?php if (!empty($factura['imagen_url'])): ?>
                                            <a href="uploads/facturas/<?php echo htmlspecialchars($factura['imagen_url']); ?>" target="_blank" class="badge bg-primary text-decoration-none">Ver</a>
                                            <a href="uploads/facturas/<?php echo htmlspecialchars($factura['imagen_url']); ?>" download class="badge bg-info text-decoration-none">Descargar</a>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin factura</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <!-- Botón Subir Foto -->
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#uploadModal" data-factura-id="<?php echo $factura['id']; ?>">
                                            Subir Factura
                                        </button>

                                        <!-- Botón Enviar a Empresa -->
                                        <a href="enviar_factura.php?factura_id=<?php echo $factura['id']; ?>&empresa_id=<?php echo $empresa_id; ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Enviar la Orden de Compra y la Factura adjunta por correo a la Empresa?');">
                                            Enviar
                                        </a>
                                        
                                        <a href="generar_pdf.php?factura_id=<?php echo $factura['id']; ?>" class="btn btn-sm btn-info">PDF Orden</a>                                    
                                        <a href="eliminar_factura.php?factura_id=<?php echo $factura['id']; ?>&empresa_id=<?php echo $empresa_id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta factura?');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            }
            ?>

            <div class="tab-pane fade show active" id="proceso" role="tabpanel" aria-labelledby="proceso-tab">
                <?php renderFacturasTable($facturas_proceso, $empresa_id, 'proceso'); ?>
            </div>
            <div class="tab-pane fade" id="pagada" role="tabpanel" aria-labelledby="pagada-tab">
                <?php renderFacturasTable($facturas_pagadas, $empresa_id, 'pagada'); ?>
            </div>
            <div class="tab-pane fade" id="vencida" role="tabpanel" aria-labelledby="vencida-tab">
                <?php renderFacturasTable($facturas_vencidas, $empresa_id, 'vencida'); ?>
            </div>

        </div>
    </div>
</div>

<!-- Modal para la subida de Foto -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="subir_factura_imagen.php" method="POST" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title" id="uploadModalLabel">Subir Foto de Factura</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="empresa_id" value="<?php echo $empresa_id; ?>">
            <input type="hidden" name="factura_id" id="modal_factura_id" value="">
            
            <div class="mb-3">
                <label for="imagen_factura" class="form-label">Seleccione la imagen o PDF</label>
                <input class="form-control" type="file" id="imagen_factura" name="imagen_factura" accept=".jpg,.jpeg,.png,.pdf" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Subir</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
    // Script para pasar el ID de la factura al modal
    var uploadModal = document.getElementById('uploadModal');
    uploadModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var facturaId = button.getAttribute('data-factura-id');
        var modalInput = uploadModal.querySelector('#modal_factura_id');
        modalInput.value = facturaId;
    });
</script>

<?php

require_once '../templates/footer.php';
?>