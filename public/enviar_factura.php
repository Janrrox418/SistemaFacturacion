<?php
session_start();

// Usar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Usar las clases de Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['proveedor_id'])) {
    // Si no está logueado, no puede enviar correos.
    http_response_code(403);
    die('Acceso denegado');
}

// Cargar dependencias de Composer
require_once '../vendor/autoload.php';
require_once '../config/database.php';

$proveedor_id = $_SESSION['proveedor_id'];
$factura_id = filter_input(INPUT_GET, 'factura_id', FILTER_VALIDATE_INT);

if (!$factura_id) {
    header("Location: index.php?error=" . urlencode('ID de factura no válido.'));
    exit();
}

try {
    //Obtener todos los datos necesarios (factura, cliente, proveedor)
    $sql = "SELECT 
                f.*, 
                ec.id as empresa_id,
                ec.email as empresa_email,
                ec.nombre as empresa_nombre,
                c.nombre as cliente_nombre, c.email as cliente_email, c.telefono as cliente_telefono, c.direccion as cliente_direccion,
                p.nombre as proveedor_nombre, p.rif as proveedor_rif, p.email as proveedor_email, p.telefono as proveedor_telefono, p.direccion as proveedor_direccion, p.logo_url as proveedor_logo
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN empresas_cliente ec ON c.empresa_id = ec.id
            JOIN proveedores p ON f.proveedor_id = p.id
            WHERE f.id = ? AND p.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id, $proveedor_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data || empty($data['empresa_email'])) {
        // Si no se encuentra la factura o la empresa no tiene email
        $empresa_id_redirect = filter_input(INPUT_GET, 'empresa_id', FILTER_VALIDATE_INT);
        header("Location: facturas.php?empresa_id={$empresa_id_redirect}&error=" . urlencode('Error: La factura no existe, no le pertenece o la empresa no tiene email.'));
        exit();
    }

    //Generar el PDF en memoria (es igual a generar_pdf.php)
    $stmt_items = $pdo->prepare("SELECT * FROM factura_items WHERE factura_id = ?");
    $stmt_items->execute([$factura_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    include '../templates/factura_pdf_template.php';
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf_content = $dompdf->output(); // Obtener el PDF como una cadena de texto
    $filename = "Factura-" . $data['numero_factura'] . ".pdf";

    $mail = new PHPMailer(true);

   
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // Servidor SMTP
    $mail->SMTPAuth   = true;
    $mail->Username   = 'toyarturo813@gmail.com'; // Correo que se usara SMTP
    $mail->Password   = 'kxzp ycrn tbzf bcna'; // contraseña de aplicación SMTP
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Remitente y destinatarios
    $mail->setFrom($data['proveedor_email'], $data['proveedor_nombre']);
    // Se Envia a la empresa
    $mail->addAddress($data['empresa_email'], $data['empresa_nombre']);
    $mail->addReplyTo($data['proveedor_email'], $data['proveedor_nombre']);

    // Contenido del correo
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Orden de Compra ' . htmlspecialchars($data['numero_factura']) . ' - ' . htmlspecialchars($data['proveedor_nombre']);
    $mail->Body    = "Hola " . htmlspecialchars($data['empresa_nombre']) . ",<br><br>" .
                     "Adjunto encontrarás la orden de compra con el número <strong>" . htmlspecialchars($data['numero_factura']) . "</strong> correspondiente al cliente final: " . htmlspecialchars($data['cliente_nombre']) . ".<br><br>" .
                     "Gracias por tu negocio.<br><br>" .
                     "Saludos,<br>" .
                     htmlspecialchars($data['proveedor_nombre']);
    $mail->AltBody = 'Adjunto encontrarás la orden de compra ' . htmlspecialchars($data['numero_factura']) . '.';

    // Adjuntar la FOTO si existe
    if (!empty($data['imagen_url'])) {
        $image_path = __DIR__ . '/uploads/facturas/' . $data['imagen_url'];
        if (file_exists($image_path)) {
            $mail->addAttachment($image_path, 'Foto_Orden_' . $data['numero_factura'] . '.' . pathinfo($data['imagen_url'], PATHINFO_EXTENSION));
        }
    }

    // Adjuntar el PDF generado en memoria
    $mail->addStringAttachment($pdf_content, $filename, 'base64', 'application/pdf');

    $mail->send();

    header("Location: facturas.php?empresa_id={$data['empresa_id']}&success=" . urlencode('Factura enviada por correo exitosamente.'));
    exit();

} catch (Exception $e) {
    // Si algo falla (BD, PDF o email), redirigir con un mensaje de error
    $empresa_id_redirect = isset($data['empresa_id']) ? $data['empresa_id'] : filter_input(INPUT_GET, 'empresa_id', FILTER_VALIDATE_INT);
    header("Location: facturas.php?empresa_id={$empresa_id_redirect}&error=" . urlencode("Error al enviar el correo: {$mail->ErrorInfo}"));
    exit();
}
?>
