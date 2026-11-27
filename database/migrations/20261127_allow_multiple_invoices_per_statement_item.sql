ALTER TABLE proveedor_facturas_sap
    DROP PRIMARY KEY,
    ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (id),
    ADD COLUMN orden_compra VARCHAR(10) NULL AFTER gjahr,
    ADD COLUMN factura_numero VARCHAR(50) NULL AFTER orden_compra,
    ADD COLUMN monto_neto DECIMAL(20, 2) NULL AFTER factura_numero,
    ADD COLUMN moneda CHAR(5) NULL AFTER monto_neto,
    ADD COLUMN fecha_documento DATE NULL AFTER moneda,
    ADD KEY idx_proveedor_facturas_documento (bukrs, lifnr, belnr, gjahr),
    ADD KEY idx_proveedor_facturas_orden (bukrs, lifnr, orden_compra);
