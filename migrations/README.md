# MIGRACIÓN 001: CATEGORÍAS Y TEMPORADAS

## Descripción
Esta migración agrega soporte para:
- **Tabla `categorias`**: Categorías predefinidas con gestión estructurada
- **Campo `temporada`**: ENUM('verano', 'invierno') en la tabla `productos`

## Categorías incluidas
1. **Gorras hombre** - Gorras y sombreros para hombre
2. **Gorras mujer** - Gorras y sombreros para mujer
3. **Gorras niños** - Gorras y sombreros para niños
4. **Gorras camar** - Gorras estilo cámara
5. **Pilusos** - Sombreros tipo piluso
6. **Riñoneras** - Riñoneras y carteras de cintura
7. **Mochilas** - Mochilas de diferentes tamaños
8. **Billeteras** - Billeteras y carteras
9. **Sombreros** - Sombreros de diferentes estilos

## Instrucciones de instalación

### Paso 1: Ejecutar las migraciones
```bash
# Opción 1: Desde el navegador web
http://tu-dominio/antero/migrar_bd.php

# Opción 2: Desde línea de comandos
cd c:\xampp\htdocs\antero
php migrar_bd.php
```

### Paso 2: Verificar instalación
La migración creará:
- ✅ Tabla `categorias` con las categorías predefinidas
- ✅ Campo `temporada` en tabla `productos`
- ✅ Índices optimizados para consultas

## Uso en el código

### Incluir el helper
```php
require_once 'helpers/categorias_temporadas.php';
```

### Obtener categorías
```php
// Obtener todas las categorías disponibles
$categorias = obtenerTodasLasCategorias();

// Obtener solo las categorías de la nueva tabla
$categoriasNuevas = obtenerCategoriasNuevas();

// Generar select HTML
echo generarSelectCategorias('categoria', $categoriaSeleccionada, true, ['class' => 'form-control']);
```

### Trabajar con temporadas
```php
// Obtener temporadas disponibles
$temporadas = obtenerTemporadas();

// Validar temporada
if (esTemporadaValida($temporada)) {
    // La temporada es válida
}

// Generar select HTML para temporadas
echo generarSelectTemporadas('temporada', $temporadaSeleccionada, true, ['class' => 'form-control']);
```

### Filtrar productos
```php
// Filtrar productos por categoría y temporada
$productos = obtenerProductosFiltrados('Gorras hombre', 'verano', 20, 0);

// Obtener estadísticas
$stats = obtenerEstadisticasCategoriasTemporadas();
```

## Ejemplos de uso en formularios

### Formulario de producto con categorías y temporadas
```html
<div class="mb-3">
    <label for="categoria" class="form-label">Categoría</label>
    <?= generarSelectCategorias('categoria', null, false, [
        'id' => 'categoria',
        'class' => 'form-control',
        'required' => 'required'
    ]); ?>
</div>

<div class="mb-3">
    <label for="temporada" class="form-label">Temporada</label>
    <?= generarSelectTemporadas('temporada', null, true, [
        'id' => 'temporada',
        'class' => 'form-control'
    ]); ?>
</div>
```

### Filtros de búsqueda
```html
<div class="row">
    <div class="col-md-6">
        <label for="filtro_categoria">Filtrar por categoría:</label>
        <?= generarSelectCategorias('filtro_categoria', $_GET['categoria'] ?? null, true, [
            'id' => 'filtro_categoria',
            'class' => 'form-control',
            'onchange' => 'filtrarProductos()'
        ]); ?>
    </div>
    <div class="col-md-6">
        <label for="filtro_temporada">Filtrar por temporada:</label>
        <?= generarSelectTemporadas('filtro_temporada', $_GET['temporada'] ?? null, true, [
            'id' => 'filtro_temporada',
            'class' => 'form-control',
            'onchange' => 'filtrarProductos()'
        ]); ?>
    </div>
</div>
```

## Compatibilidad hacia atrás

El sistema mantiene **compatibilidad completa** con el código existente:

- ✅ El campo `categoria` como texto libre sigue funcionando
- ✅ Todas las consultas existentes continúan operando normalmente
- ✅ Los formularios actuales no requieren modificaciones inmediatas
- ✅ Se puede migrar gradualmente al nuevo sistema

## Migración de datos legacy

Para migrar las categorías existentes del campo de texto libre a la nueva tabla:

```php
require_once 'helpers/categorias_temporadas.php';

$resultado = migrarCategoriasLegacy();
if ($resultado['success']) {
    echo "Migradas: " . $resultado['migradas'];
    echo "Duplicadas: " . $resultado['duplicadas'];
}
```

## Base de datos

### Tabla `categorias`
```sql
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Campo `temporada` en tabla `productos`
```sql
ALTER TABLE `productos` 
ADD COLUMN `temporada` ENUM('verano', 'invierno') DEFAULT NULL;
```

## Soporte

Para consultas sobre esta migración:
- Verificar que la base de datos sea `ropa-nueva`
- Confirmar que el usuario de MySQL tiene permisos CREATE y ALTER
- Revisar logs de errores en caso de fallos

---
**Versión:** 1.0  
**Fecha:** 2025-01-21  
**Autor:** Sistema de Gestión de Ropa
