<?php
session_start();

if (!isset($_SESSION['proveedor_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $factura_id = filter_input(INPUT_POST, 'factura_id', FILTER_VALIDATE_INT);
    $empresa_id = filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT);
    
    if (!$factura_id || !$empresa_id) {
        header("Location: facturas.php?empresa_id={$empresa_id}&error=" . urlencode('Datos inválidos.'));
        exit();
    }

    if (isset($_FILES['imagen_factura']) && $_FILES['imagen_factura']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['imagen_factura']['tmp_name'];
        $file_name = $_FILES['imagen_factura']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $upload_dir = __DIR__ . '/uploads/facturas/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Nombre único para evitar errores
            $new_filename = 'factura_' . $factura_id . '_' . time() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $dest_path)) {
                try {
                    $stmt = $pdo->prepare("UPDATE facturas SET imagen_url = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $factura_id]);
                    
                    header("Location: facturas.php?empresa_id={$empresa_id}&success=" . urlencode('Foto de factura subida exitosamente.'));
                    exit();
                } catch (PDOException $e) {
                    header("Location: facturas.php?empresa_id={$empresa_id}&error=" . urlencode('Error al guardar en base de datos.'));
                    exit();
                }
            } else {
                header("Location: facturas.php?empresa_id={$empresa_id}&error=" . urlencode('Error al mover el archivo subido.'));
                exit();
            }
        } else {
            header("Location: facturas.php?empresa_id={$empresa_id}&error=" . urlencode('Formato de archivo no permitido. Solo JPG, PNG o PDF.'));
            exit();
        }
    } else {
        header("Location: facturas.php?empresa_id={$empresa_id}&error=" . urlencode('No se seleccionó ningún archivo o hubo un error en la subida.'));
        exit();
    }
}
header("Location: ../index.php");
