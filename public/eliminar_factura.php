<?php
session_start();

if (!isset($_SESSION['proveedor_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';

$proveedor_id = $_SESSION['proveedor_id'];
$factura_id = filter_input(INPUT_GET, 'factura_id', FILTER_VALIDATE_INT);
$empresa_id = filter_input(INPUT_GET, 'empresa_id', FILTER_VALIDATE_INT);

if (!$factura_id || !$empresa_id) {
    header("Location: index.php?error=" . urlencode('Datos insuficientes para eliminar la orden de compra.'));
    exit();
}

try {
    // Verificar que la factura pertenece al proveedor antes de borrarla.
    // Esto se hace uniendo las tablas hasta llegar al proveedor.
    $sql_check = "SELECT f.id FROM facturas f
                  JOIN clientes c ON f.cliente_id = c.id 
                  JOIN empresa_proveedores ep ON c.empresa_id = ep.empresa_id
                  WHERE f.id = ? AND ep.proveedor_id = ?";
    
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$factura_id, $proveedor_id]);

    if ($stmt_check->fetch()) {
        // Si la factura existe y pertenece al proveedor, se procede a eliminar.
        $sql_delete = "DELETE FROM facturas WHERE id = ?";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute([$factura_id]);

        header("Location: facturas.php?empresa_id={$empresa_id}&success=" . urlencode('Orden de compra eliminada exitosamente.'));
        exit();
    } else {
        throw new Exception('No tiene permiso para eliminar esta orden o la orden no existe.');
    }
} catch (Exception $e) {
    header("Location: facturas.php?empresa_id={$empresa_id}&error=" . urlencode('Error al eliminar la orden de compra: ' . $e->getMessage()));
    exit();
}