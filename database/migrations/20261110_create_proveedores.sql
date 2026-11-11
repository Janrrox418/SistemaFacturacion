CREATE TABLE IF NOT EXISTS proveedores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    proveedor_sap_id VARCHAR(10) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    rif VARCHAR(20) NOT NULL,
    clave_estandar VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proveedores_proveedor_sap_id (proveedor_sap_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
