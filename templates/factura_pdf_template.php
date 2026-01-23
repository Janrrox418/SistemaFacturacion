<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?php echo htmlspecialchars($data['numero_factura']); ?></title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            font-size: 12px;
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }
        .header, .footer {
            width: 100%;
            text-align: center;
            position: fixed;
        }
        .header { top: 0px; }
        .footer { bottom: 0px; font-size: 10px; color: #777; }
        .invoice-header {
            margin-top: 40px;
            margin-bottom: 40px;
        }
        .invoice-header h1 {
            font-size: 24px;
            margin: 0;
            color: #007bff;
        }
        .invoice-header .logo {
            max-width: 150px; /* Ajusta el tamaño de tu logo */
            color: #007bff;
        }
        .invoice-details, .company-details {
            width: 48%;
            display: inline-block;
            vertical-align: top;
        }
        .company-details {
            text-align: right;
        }
        .company-details h2 {
            margin: 0;
            font-size: 16px;
        }
        .bill-to {
            margin-top: 40px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .total-section {
            margin-top: 20px;
            float: right;
            width: 40%;
        }
        .total-section table {
            width: 100%;
        }
        .total-section td {
            border: none;
        }
        .total-section .total {
            font-weight: bold;
            font-size: 1.2em;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="invoice-header">
            <div class="invoice-details">
                <?php if (!empty($data['proveedor_logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($data['proveedor_logo_url']); ?>" alt="Logo Empresa" class="logo"><br>
                <?php endif; ?>
                <h1>FACTURA</h1>
                <p><strong>Nº Factura:</strong> <?php echo htmlspecialchars($data['numero_factura']); ?></p>
                <p><strong>Fecha de Emisión:</strong> <?php echo date("d/m/Y", strtotime($data['fecha_emision'])); ?></p>
                <p><strong>Fecha de Vencimiento:</strong> <?php echo date("d/m/Y", strtotime($data['fecha_vencimiento'])); ?></p>
            </div>
            <div class="company-details">
                <h2><?php echo htmlspecialchars($data['proveedor_nombre']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($data['proveedor_direccion'])); ?></p>
                <p>Tel: <?php echo htmlspecialchars($data['proveedor_telefono']); ?></p>
                <p>Email: <?php echo htmlspecialchars($data['proveedor_email']); ?></p>
                <p>RIF: <?php echo htmlspecialchars($data['proveedor_rif']); ?></p>
            </div>
        </div>

        <div class="bill-to">
            <h3>Facturar a:</h3>
            <p><strong><?php echo htmlspecialchars($data['cliente_nombre']); ?></strong></p>
            <p><?php echo nl2br(htmlspecialchars($data['cliente_direccion'])); ?></p>
            <p>Tel: <?php echo htmlspecialchars($data['cliente_telefono']); ?></p>
            <p>Email: <?php echo htmlspecialchars($data['cliente_email']); ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['descripcion']); ?></td>
                    <td class="text-right"><?php echo htmlspecialchars($item['cantidad']); ?></td>
                    <td class="text-right"><?php echo number_format($item['precio_unitario'], 2, ',', '.'); ?> $</td>
                    <td class="text-right"><?php echo number_format($item['cantidad'] * $item['precio_unitario'], 2, ',', '.'); ?> $</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            <table>
                <tr>
                    <td><strong>TOTAL:</strong></td>
                    <td class="text-right total"><?php echo number_format($data['total'], 2, ',', '.'); ?> $</td>
                </tr>
            </table>
        </div>

        <div style="clear: both; margin-top: 50px;">
            <h4>Notas / Términos y Condiciones:</h4>
            <p>Gracias por su negocio. Por favor, realice el pago antes de la fecha de vencimiento. Para cualquier consulta, contáctenos.</p>
        </div>
    </div>

</body>
</html>