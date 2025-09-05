-- ====================================================================
-- MIGRACIÓN 001: Agregar tabla categorías y campo temporada
-- Proyecto: Sistema de Gestión de Ropa
-- Fecha: 2025-01-21
-- Descripción: Crea tabla categorías con las categorías especificadas
--              y agrega campo temporada (ENUM) a la tabla productos
-- ====================================================================

-- Crear tabla categorias
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar categorías predefinidas
INSERT INTO `categorias` (`nombre`, `descripcion`) VALUES
('Gorras hombre', 'Gorras y sombreros para hombre'),
('Gorras mujer', 'Gorras y sombreros para mujer'),
('Gorras niños', 'Gorras y sombreros para niños'),
('Gorras camar', 'Gorras estilo cámara'),
('Pilusos', 'Sombreros tipo piluso'),
('Riñoneras', 'Riñoneras y carteras de cintura'),
('Mochilas', 'Mochilas de diferentes tamaños'),
('Billeteras', 'Billeteras y carteras'),
('Sombreros', 'Sombreros de diferentes estilos')
ON DUPLICATE KEY UPDATE 
  descripcion = VALUES(descripcion),
  fecha_actualizacion = CURRENT_TIMESTAMP;

-- Agregar campo temporada a la tabla productos (si no existe)
ALTER TABLE `productos` 
ADD COLUMN IF NOT EXISTS `temporada` ENUM('verano', 'invierno') DEFAULT NULL 
COMMENT 'Temporada del producto: verano o invierno';

-- Agregar índices para optimizar consultas
ALTER TABLE `productos` 
ADD INDEX IF NOT EXISTS `idx_temporada` (`temporada`);

ALTER TABLE `productos` 
ADD INDEX IF NOT EXISTS `idx_categoria_temporada` (`categoria`, `temporada`);

-- Comentarios para documentación
ALTER TABLE `categorias` COMMENT = 'Tabla de categorías de productos predefinidas';
ALTER TABLE `productos` MODIFY COLUMN `temporada` ENUM('verano', 'invierno') DEFAULT NULL 
COMMENT 'Temporada del producto: verano para productos de clima cálido, invierno para clima frío';
