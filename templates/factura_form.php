<div class="card">
    <div class="card-header">
        <h3><?php echo $action === 'edit' ? 'Editar Factura' : 'Crear Nueva Factura'; ?></h3>
        <p>Para el cliente: <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong></p>
    </div>
    <div class="card-body">
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="POST" action="facturas.php?cliente_id=<?php echo $cliente_id; ?>" enctype="multipart/form-data">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="factura_id" value="<?php echo $factura['id']; ?>">
                <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($factura['imagen_url'] ?? ''); ?>">
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="numero_factura" class="form-label">Número de Factura</label>
                    <input type="text" class="form-control" id="numero_factura" name="numero_factura" value="<?php echo htmlspecialchars($factura['numero_factura'] ?? 'FACT-'.time()); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="fecha_emision" class="form-label">Fecha de Emisión</label>
                    <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" value="<?php echo htmlspecialchars($factura['fecha_emision'] ?? date('Y-m-d')); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                    <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" value="<?php echo htmlspecialchars($factura['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+30 days'))); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="no_pagada" <?php echo ($factura['estado'] ?? 'no_pagada') === 'no_pagada' ? 'selected' : ''; ?>>No Pagada</option>
                        <option value="pagada" <?php echo ($factura['estado'] ?? '') === 'pagada' ? 'selected' : ''; ?>>Pagada</option>
                        <option value="vencida" <?php echo ($factura['estado'] ?? '') === 'vencida' ? 'selected' : ''; ?>>Vencida</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="total" class="form-label">Total de Factura</label>
                    <input type="number" class="form-control" id="total" name="total" value="<?php echo htmlspecialchars($factura['total'] ?? '0.00'); ?>" step="0.01" min="0" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="imagen_factura" class="form-label">Subir Imagen de Factura (Opcional)</label>
                    <input type="file" class="form-control" id="imagen_factura" name="imagen_factura" accept="image/*">
                    <?php if ($action === 'edit' && !empty($factura['imagen_url'])): ?>
                        <div class="mt-2">
                            <small>PDF actual: 
                                <a href="uploads/facturas/<?php echo htmlspecialchars($factura['imagen_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($factura['imagen_url']); ?>
                                </a>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'Actualizar Factura' : 'Guardar Factura'; ?></button>
                <a href="facturas.php?cliente_id=<?php echo $cliente_id; ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
});
</script>