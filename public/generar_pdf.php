<?php
session_start();

if (!isset($_SESSION['proveedor_id'])) {
    // Si no está logueado, no puede generar PDFs.
    http_response_code(403);
    die('Acceso denegado');
}
// Cargar el autoloader de Composer
require_once '../vendor/autoload.php';

// clases de Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

require_once '../config/database.php';

$proveedor_id = $_SESSION['proveedor_id'];
$factura_id = filter_input(INPUT_GET, 'factura_id', FILTER_VALIDATE_INT);

if (!$factura_id) {
    http_response_code(400);
    die('ID de factura no válido.');
}

try {
    //Obtener los datos de la factura, cliente y proveedor en una sola consulta
    $sql = "SELECT 
                f.*, 
                c.nombre as cliente_nombre, c.email as cliente_email, c.telefono as cliente_telefono, c.direccion as cliente_direccion,
                p.nombre as proveedor_nombre, p.rif as proveedor_rif, p.email as proveedor_email, p.telefono as proveedor_telefono, p.direccion as proveedor_direccion, p.logo_url as proveedor_logo
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN proveedores p ON f.proveedor_id = p.id
            WHERE f.id = ? AND p.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id, $proveedor_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        // La factura no existe o no pertenece a este proveedor
        http_response_code(404);
        die('Factura no encontrada o no tiene permiso para verla.');
    }

    // Construir la URL absoluta del logo para Dompdf
    if (!empty($data['proveedor_logo'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); // 
        $data['proveedor_logo_url'] = $protocol . $host . $path . '/img/' . $data['proveedor_logo'];
    } else {
        $data['proveedor_logo_url'] = null;
    }

    //Obtener los ítems de la factura
    $stmt_items = $pdo->prepare("SELECT * FROM factura_items WHERE factura_id = ?");
    $stmt_items->execute([$factura_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    //Generar el HTML de la factura usando una plantilla
    ob_start(); // Inicia el buffer de salida

    include '../templates/factura_pdf_template.php';
    $html = ob_get_clean(); // Obtiene el contenido del buffer y lo limpia

    //Configurar Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // Permite cargar imágenes/CSS externos si los hay

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);

    $dompdf->setPaper('A4', 'portrait');

    //Renderizar el HTML como PDF
    $dompdf->render();

    $filename = "Factura-" . $data['numero_factura'] . ".pdf";
    // El true en el segundo argumento hace que el PDF se descargue en lugar de mostrarse en el navegador
    $dompdf->stream($filename, ["Attachment" => true]);

} catch (Exception $e) {
    // Usamos Exception general porque Dompdf puede lanzar sus propias excepciones
    http_response_code(500);
    die('Error al generar el PDF: ' . $e->getMessage());
}