<?php
/**
 * Cargador de variables de entorno
 * Carga las variables del archivo .env de forma segura
 */

class EnvLoader {
    private static $loaded = false;
    private static $env = [];
    
    /**
     * Cargar variables de entorno desde archivo .env
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }
        
        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }
        
        if (!file_exists($path)) {
            error_log("Archivo .env no encontrado en: $path");
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parsear línea KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remover comillas si existen
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                self::$env[$key] = $value;
                
                // También establecer en $_ENV y putenv para compatibilidad
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Obtener variable de entorno
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$env[$key]) ? self::$env[$key] : $default;
    }
    
    /**
     * Verificar si una variable existe
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$env[$key]);
    }
    
    /**
     * Obtener todas las variables cargadas
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$env;
    }
}

/**
 * Función helper para obtener variables de entorno
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}

// Cargar automáticamente al incluir este archivo
EnvLoader::load();
?>