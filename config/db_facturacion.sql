-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 19-01-2026 a las 16:39:39
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `db_facturacion`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `empresa_id`, `nombre`, `email`, `telefono`, `direccion`) VALUES
(8, 5, 'Cliente SAP ', '@cliente-sap.com', NULL, NULL),
(13, 7, 'Cliente SAP J-40454981-1', 'j-40454981-1@cliente-sap.com', NULL, NULL),
(14, 7, 'Cliente SAP J-41106035-6', 'j-41106035-6@cliente-sap.com', NULL, NULL),
(15, 7, 'Cliente SAP J-29766789-0', 'j-29766789-0@cliente-sap.com', NULL, NULL),
(16, 7, 'Cliente SAP 0003500002', '0003500002@cliente-sap.com', NULL, NULL),
(17, 7, 'Cliente SAP ', '@cliente-sap.com', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas_cliente`
--

CREATE TABLE `empresas_cliente` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `sap_api_url` varchar(255) DEFAULT NULL,
  `sap_api_username` varchar(100) DEFAULT NULL,
  `sap_api_password` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empresas_cliente`
--

INSERT INTO `empresas_cliente` (`id`, `proveedor_id`, `nombre`, `email`, `telefono`, `direccion`, `fecha_registro`, `sap_api_url`, `sap_api_username`, `sap_api_password`) VALUES
(3, 1, 'Alan', 'm.arrieche@zagasystem.com', '04132145332', 'Cabudare', '2025-10-25 16:58:50', NULL, NULL, NULL),
(5, 3, 'BEL C.A.', 'contacto@bel.com', '0212-9876543', 'Calle Falsa 123, La Candelaria, Caracas', '2025-11-12 14:45:27', NULL, NULL, NULL),
(6, 3, 'Ebenezer S.R.L.', 'info@ebenezer.net', '0251-1122334', 'Av. Bolívar, Centro Comercial, Valencia', '2025-11-12 14:45:27', NULL, NULL, NULL),
(7, 3, 'Agroinlaca C.A.', 'ventas@agroinlaca.com', '0251-5566778', 'Zona Industrial, Barquisimeto', '2025-11-12 14:45:27', 'http://aps4dapp.agroinlaca.com:9100/sap/bc/srt/zjson_portal?sap-client=400&FECHAI=20250701&FECHAF=20251231', 'ABAP01', 'inicio666'),
(10, 1, 'Agroinlaca C.A.', 'alejandrohv1120@gmail.com', '04145605485', 'cabudare', '2026-01-19 15:14:39', 'http://aps4dapp.agroinlaca.com:9100/sap/bc/srt/zjson_portal?sap-client=400&FECHAI=20250701&FECHAF=20251231', 'ABAP01', 'inicio666');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresa_proveedores`
--

CREATE TABLE `empresa_proveedores` (
  `empresa_id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empresa_proveedores`
--

INSERT INTO `empresa_proveedores` (`empresa_id`, `proveedor_id`) VALUES
(3, 1),
(5, 3),
(6, 3),
(7, 3),
(10, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas`
--

CREATE TABLE `facturas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `numero_factura` varchar(50) NOT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('pagada','no_pagada','vencida') NOT NULL DEFAULT 'no_pagada',
  `total` decimal(15,2) NOT NULL,
  `imagen_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `facturas`
--

INSERT INTO `facturas` (`id`, `cliente_id`, `proveedor_id`, `numero_factura`, `fecha_emision`, `fecha_vencimiento`, `estado`, `total`, `imagen_url`) VALUES
(23, 13, NULL, '4500004560', '2025-08-11', '2025-09-10', 'vencida', 0.00, NULL),
(24, 13, NULL, '4500004561', '2025-08-11', '2025-09-10', 'vencida', 0.00, NULL),
(25, 14, NULL, '4500004562', '2025-08-12', '2025-09-11', 'vencida', 0.00, NULL),
(26, 15, NULL, '4500004563', '2025-08-21', '2025-09-20', 'vencida', 0.00, NULL),
(27, 15, NULL, '4500004564', '2025-08-22', '2025-09-21', 'vencida', 0.00, NULL),
(28, 16, NULL, '4500004565', '2025-09-17', '2025-10-17', 'vencida', 0.00, NULL),
(29, 17, NULL, '4700000236', '2025-09-17', '2025-10-17', 'vencida', 0.00, NULL),
(30, 17, NULL, '4700000237', '2025-09-17', '2025-10-17', 'vencida', 0.00, NULL),
(34, 21, 1, '4500004560', '2025-08-11', '2025-09-10', 'vencida', 0.00, NULL),
(35, 21, 1, '4500004561', '2025-08-11', '2025-09-10', 'vencida', 0.00, NULL),
(36, 22, 1, '4500004562', '2025-08-12', '2025-09-11', 'vencida', 0.00, NULL),
(37, 23, 1, '4500004563', '2025-08-21', '2025-09-20', 'vencida', 0.00, NULL),
(38, 23, 1, '4500004564', '2025-08-22', '2025-09-21', 'vencida', 0.00, NULL),
(39, 24, 1, '4500004565', '2025-09-17', '2025-10-17', 'vencida', 0.00, NULL),
(40, 25, 1, '4700000236', '2025-09-17', '2025-10-17', 'vencida', 0.00, NULL),
(41, 25, 1, '4700000237', '2025-09-17', '2025-10-17', 'vencida', 0.00, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura_items`
--

CREATE TABLE `factura_items` (
  `id` int(11) NOT NULL,
  `factura_id` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `factura_items`
--

INSERT INTO `factura_items` (`id`, `factura_id`, `descripcion`, `cantidad`, `precio_unitario`) VALUES
(29, 23, 'BENTAZONE', 500, 0.00),
(30, 24, 'BENTAZONE', 500, 0.00),
(31, 25, 'UREA (SACO 50KG)', 830, 0.00),
(32, 26, 'INDUCIDO FORD', 1, 0.00),
(33, 27, 'INDUCIDO FORD', 1, 0.00),
(34, 28, 'PENDIMETHALIN', 500, 0.00),
(35, 29, 'PENDIMETHALIN', 3, 0.00),
(36, 30, 'PENDIMETHALIN', 3, 0.00),
(37, 34, 'BENTAZONE', 500, 0.00),
(38, 35, 'BENTAZONE', 500, 0.00),
(39, 36, 'UREA (SACO 50KG)', 830, 0.00),
(40, 37, 'INDUCIDO FORD', 1, 0.00),
(41, 38, 'INDUCIDO FORD', 1, 0.00),
(42, 39, 'PENDIMETHALIN', 500, 0.00),
(43, 40, 'PENDIMETHALIN', 3, 0.00),
(44, 41, 'PENDIMETHALIN', 3, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `rif` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `nombre`, `rif`, `password`, `email`, `telefono`, `direccion`, `logo_url`, `fecha_registro`) VALUES
(1, 'Alan S.A', 'J-12345678-9', '1234', 'contactoalansa@gmail.com', '+58 414 248 95 20', 'Calle Principal 123, Oficina 4B', 'logo_1_1762443704.webp', '2025-10-25 15:39:40'),
(3, 'Zaga LLC', 'J-12345678-10', '123456', 'proveedor@ejemplo.com', '0212-1234567', 'Av. Principal, Edif. Central, Caracas', NULL, '2025-11-12 14:45:27'),
(4, 'ByMessi', 'J-28585613-3', '1234', 'l.hernandez@zagasystem.com', NULL, NULL, NULL, '2025-11-13 15:01:28');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Indices de la tabla `empresas_cliente`
--
ALTER TABLE `empresas_cliente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clientes_ibfk_proveedor` (`proveedor_id`);

--
-- Indices de la tabla `empresa_proveedores`
--
ALTER TABLE `empresa_proveedores`
  ADD PRIMARY KEY (`empresa_id`,`proveedor_id`),
  ADD KEY `proveedor_id` (`proveedor_id`);

--
-- Indices de la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_factura_proveedor_unique` (`numero_factura`,`proveedor_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `factura_items`
--
ALTER TABLE `factura_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `factura_id` (`factura_id`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rif` (`rif`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `empresas_cliente`
--
ALTER TABLE `empresas_cliente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `facturas`
--
ALTER TABLE `facturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de la tabla `factura_items`
--
ALTER TABLE `factura_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_cliente` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `empresas_cliente`
--
ALTER TABLE `empresas_cliente`
  ADD CONSTRAINT `empresas_cliente_ibfk_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `empresa_proveedores`
--
ALTER TABLE `empresa_proveedores`
  ADD CONSTRAINT `empresa_proveedores_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_cliente` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `empresa_proveedores_ibfk_2` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `factura_items`
--
ALTER TABLE `factura_items`
  ADD CONSTRAINT `factura_items_ibfk_1` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
