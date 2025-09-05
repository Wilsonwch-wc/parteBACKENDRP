-- ====================================================================
-- MIGRACIÓN 002: Relacionar tabla productos con categorias
-- Proyecto: Sistema de Gestión de Ropa
-- Fecha: 2025-09-01
-- Descripción: Agrega foreign key categoria_id a productos y mapea
--              las categorías existentes con las categorías oficiales
-- ====================================================================

-- 1. Agregar campo categoria_id a la tabla productos
ALTER TABLE `productos` 
ADD COLUMN `categoria_id` INT(11) DEFAULT NULL 
AFTER `categoria`;

-- 2. Agregar foreign key constraint
ALTER TABLE `productos` 
ADD CONSTRAINT `fk_productos_categoria` 
FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- 3. Crear índice para optimizar consultas
ALTER TABLE `productos` 
ADD INDEX `idx_categoria_id` (`categoria_id`);

-- 4. Mapear categorías existentes con las nuevas categorías oficiales
-- Mapear gorras y sombreros de hombre
UPDATE `productos` SET `categoria_id` = 1 WHERE 
    `categoria` IN ('GORRA ADULTO', 'BOINAS HOMBRE', 'GUANTE HOMBRE', 'GUANTES PROMO HOMBRE')
    OR `categoria` LIKE '%HOMBRE%';

-- Mapear gorras y sombreros de mujer  
UPDATE `productos` SET `categoria_id` = 2 WHERE 
    `categoria` IN ('G.DAMA CON PIEL', 'GUANTES DAMA-JUVENIL', 'OFERTA DE DAMA')
    OR `categoria` LIKE '%DAMA%' OR `categoria` LIKE '%MUJER%';

-- Mapear gorras y sombreros de niños
UPDATE `productos` SET `categoria_id` = 3 WHERE 
    `categoria` IN ('G.NINO', 'gorras de nena con aplique', 'gorras de ninos', 'GORRO DE LANA BEBE', 
                    'gorros de lana ninos', 'GUANTE DE NINOS', 'guantes de nino', 'GUANTE JUVENIL', 'ninos')
    OR `categoria` LIKE '%NIÑO%' OR `categoria` LIKE '%NINO%' OR `categoria` LIKE '%NENA%' 
    OR `categoria` LIKE '%BEBE%' OR `categoria` LIKE '%JUVENIL%';

-- Mapear pilusos (incluir boinas que son similares)
UPDATE `productos` SET `categoria_id` = 5 WHERE 
    `categoria` IN ('PILUSOS', 'BOINAS', 'ROCKY')
    OR `categoria` LIKE '%PILUSO%' OR `categoria` LIKE '%BOINA%';

-- Mapear sombreros generales (gorros, gorros con piel, etc.)
UPDATE `productos` SET `categoria_id` = 9 WHERE 
    `categoria` IN ('gorras', 'GORRO', 'GORRO CON PIEL', 'GORROS DE LANA', 'PASAMONTANAS')
    OR (`categoria_id` IS NULL AND (`categoria` LIKE '%GORRO%' OR `categoria` LIKE '%GORRA%'));

-- Mapear productos de invierno generales a sombreros si no tienen categoría específica
UPDATE `productos` SET `categoria_id` = 9 WHERE 
    `categoria` IN ('BUFANDAS', 'BUFANDAS HOMBRE', 'CHALINAS', 'CUELLO ADULTO', 'CUELLOS INFINITOS', 
                    'GUANTES', 'GUANTES AO2O', 'GUANTES DE MOTO', 'INVIERNO', 'RUANAS')
    AND `categoria_id` IS NULL;

-- 5. Para productos que no se mapearon, asignar a "Sombreros" como categoría por defecto
UPDATE `productos` SET `categoria_id` = 9 WHERE `categoria_id` IS NULL AND `estado` = 1;

-- 6. Agregar comentario al campo para documentación
ALTER TABLE `productos` 
MODIFY COLUMN `categoria_id` INT(11) DEFAULT NULL 
COMMENT 'Foreign key hacia tabla categorias. NULL = sin categoría asignada';

-- 7. Agregar comentario explicativo
ALTER TABLE `productos` 
MODIFY COLUMN `categoria` VARCHAR(100) NOT NULL 
COMMENT 'Categoría legacy (texto libre). Usar categoria_id para nuevas funcionalidades';
