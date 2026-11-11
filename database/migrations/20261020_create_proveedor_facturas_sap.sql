CREATE TABLE IF NOT EXISTS proveedor_facturas_sap (
    bukrs VARCHAR(4) NOT NULL,
    lifnr VARCHAR(10) NOT NULL,
    belnr VARCHAR(20) NOT NULL,
    gjahr CHAR(4) NOT NULL,
    archivo VARCHAR(40) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bukrs, lifnr, belnr, gjahr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
