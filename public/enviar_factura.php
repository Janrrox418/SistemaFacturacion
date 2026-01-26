<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['proveedor_id'])) {
    http_response_code(403);
    die('Acceso denegado');
}

// Usamos __DIR__ para que las rutas sean relativas al archivo actual
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$proveedor_id = $_SESSION['proveedor_id'];
$factura_id = filter_input(INPUT_GET, 'factura_id', FILTER_VALIDATE_INT);

if (!$factura_id) {
    header("Location: ../index.php?error=" . urlencode('ID de factura no válido.'));
    exit();
}

try {
    $sql = "SELECT f.*, ec.id as empresa_id, ec.email as empresa_email, ec.nombre as empresa_nombre, 
                   c.nombre as cliente_nombre, p.nombre as proveedor_nombre, p.email as proveedor_email 
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN empresas_cliente ec ON c.empresa_id = ec.id
            JOIN proveedores p ON f.proveedor_id = p.id
            WHERE f.id = ? AND p.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id, $proveedor_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data || empty($data['empresa_email'])) {
        throw new Exception('La factura no existe o la empresa no tiene email.');
    }

    // Generar PDF
    $stmt_items = $pdo->prepare("SELECT * FROM factura_items WHERE factura_id = ?");
    $stmt_items->execute([$factura_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    include __DIR__ . '/../templates/factura_pdf_template.php';
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf_content = $dompdf->output();
    $filename = "Factura-" . $data['numero_factura'] . ".pdf";

    // Enviar Correo
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'toyarturo813@gmail.com';
    $mail->Password   = 'kxzp ycrn tbzf bcna'; // Tu contraseña de aplicación
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($data['proveedor_email'], $data['proveedor_nombre']);
    $mail->addAddress($data['empresa_email'], $data['empresa_nombre']);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Orden de Compra ' . $data['numero_factura'];
    $mail->Body    = "Adjunto encontrarás la orden de compra emitida por <strong>" . $data['proveedor_nombre'] . "</strong>.";

    // Adjuntar PDF
    $mail->addStringAttachment($pdf_content, $filename, 'base64', 'application/pdf');

    $mail->send();
    header("Location: facturas.php?empresa_id={$data['empresa_id']}&success=" . urlencode('Enviado con éxito.'));
    exit();

} catch (Exception $e) {
    $error_msg = $e->getMessage();
    if (isset($mail)) $error_msg .= " (Mail Error: " . $mail->ErrorInfo . ")";
    
    $redirect_id = $data['empresa_id'] ?? 0;
    header("Location: facturas.php?empresa_id=$redirect_id&error=" . urlencode($error_msg));
    exit();
} // Aquí termina el catch
?>