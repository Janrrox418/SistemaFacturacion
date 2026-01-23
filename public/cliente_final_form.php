<div class="card mb-4">
    <div class="card-header">
        // Formulario para agregar o editar un cliente final
        <h3><?php echo ($action_cliente === 'edit') ? 'Editar Cliente Final' : 'Agregar Nuevo Cliente Final'; ?></h3>
    </div>
    <div class="card-body">
        <?php if (!empty($error_cliente)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_cliente); ?></div>
        <?php endif; ?>

        <form action="facturas.php?empresa_id=<?php echo $empresa_id; ?>" method="POST">
            <input type="hidden" name="action_cliente" value="<?php echo $action_cliente; ?>">
            <?php if ($action_cliente === 'edit'): ?>
                <input type="hidden" name="cliente_id" value="<?php echo $cliente_id_form; ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre Completo</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_cliente_form); ?>" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email_cliente_form); ?>" required>
            </div>

            <div class="mb-3">
                <label for="telefono" class="form-label">Teléfono</label>
                <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono_cliente_form); ?>">
            </div>

            <div class="mb-3">
                <label for="direccion" class="form-label">Dirección</label>
                <textarea class="form-control" id="direccion" name="direccion" rows="3"><?php echo htmlspecialchars($direccion_cliente_form); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo ($action_cliente === 'edit') ? 'Actualizar Cliente' : 'Guardar Cliente'; ?></button>
            <a href="facturas.php?empresa_id=<?php echo $empresa_id; ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>