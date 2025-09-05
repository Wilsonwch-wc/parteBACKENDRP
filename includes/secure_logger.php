<?php
/**
 * Sistema de logs seguros
 * Maneja el registro de eventos de seguridad de forma segura
 */

class SecureLogger {
    private static $instance = null;
    private $log_path;
    private $max_log_size;
    private $log_rotation;
    private $log_level;
    
    private function __construct() {
        $this->log_path = env('LOG_PATH', dirname(__DIR__) . '/logs/');
        $this->max_log_size = env('LOG_MAX_SIZE', 10485760); // 10MB por defecto
        $this->log_rotation = env('LOG_ROTATION', true);
        $this->log_level = env('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR, CRITICAL
        
        $this->ensureLogDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Asegurar que el directorio de logs existe y es seguro
     */
    private function ensureLogDirectory() {
        if (!is_dir($this->log_path)) {
            if (!mkdir($this->log_path, 0750, true)) {
                throw new Exception('No se pudo crear el directorio de logs: ' . $this->log_path);
            }
        }
        
        // Crear archivo .htaccess para proteger los logs
        $htaccess_path = $this->log_path . '.htaccess';
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents($htaccess_path, $htaccess_content);
        }
        
        // Crear archivo index.php vacío para evitar listado de directorios
        $index_path = $this->log_path . 'index.php';
        if (!file_exists($index_path)) {
            file_put_contents($index_path, '<?php // Acceso denegado');
        }
    }
    
    /**
     * Registrar evento de seguridad
     */
    public function logSecurity($message, $level = 'INFO', $context = []) {
        $this->log('security', $message, $level, $context);
    }
    
    /**
     * Registrar evento de autenticación
     */
    public function logAuth($message, $level = 'INFO', $context = []) {
        $this->log('auth', $message, $level, $context);
    }
    
    /**
     * Registrar evento de aplicación
     */
    public function logApp($message, $level = 'INFO', $context = []) {
        $this->log('app', $message, $level, $context);
    }
    
    /**
     * Registrar evento de error
     */
    public function logError($message, $context = []) {
        $this->log('error', $message, 'ERROR', $context);
    }
    
    /**
     * Método principal de logging
     */
    private function log($type, $message, $level = 'INFO', $context = []) {
        // Verificar si el nivel de log está habilitado
        if (!$this->isLevelEnabled($level)) {
            return;
        }
        
        $log_file = $this->log_path . $type . '_' . date('Y-m-d') . '.log';
        
        // Verificar rotación de logs
        if ($this->log_rotation && file_exists($log_file)) {
            $this->checkLogRotation($log_file);
        }
        
        // Preparar entrada de log
        $log_entry = $this->formatLogEntry($message, $level, $context);
        
        // Escribir al archivo de forma segura
        $this->writeToFile($log_file, $log_entry);
    }
    
    /**
     * Formatear entrada de log
     */
    private function formatLogEntry($message, $level, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getClientIp();
        $user_id = $_SESSION['user_id'] ?? 'Anónimo';
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 0, 100);
        $request_uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        
        // Sanitizar mensaje para evitar inyección de logs
        $message = $this->sanitizeLogMessage($message);
        
        $entry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'ip' => $ip,
            'user_id' => $user_id,
            'message' => $message,
            'user_agent' => $user_agent,
            'request_uri' => $request_uri
        ];
        
        // Agregar contexto si existe
        if (!empty($context)) {
            $entry['context'] = $this->sanitizeContext($context);
        }
        
        return json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    /**
     * Sanitizar mensaje de log
     */
    private function sanitizeLogMessage($message) {
        // Remover caracteres de control y saltos de línea
        $message = preg_replace('/[\x00-\x1F\x7F]/', '', $message);
        
        // Limitar longitud
        $message = substr($message, 0, 1000);
        
        // Escapar caracteres especiales para JSON
        return $message;
    }
    
    /**
     * Sanitizar contexto
     */
    private function sanitizeContext($context) {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            // Evitar logging de información sensible
            if (in_array(strtolower($key), ['password', 'token', 'secret', 'key', 'auth'])) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_string($value) ? substr($value, 0, 500) : $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Obtener IP del cliente de forma segura
     */
    private function getClientIp() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Si hay múltiples IPs, tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validar que sea una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    }
    
    /**
     * Verificar si el nivel de log está habilitado
     */
    private function isLevelEnabled($level) {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];
        $current_level = $levels[$this->log_level] ?? 1;
        $message_level = $levels[$level] ?? 1;
        
        return $message_level >= $current_level;
    }
    
    /**
     * Verificar y realizar rotación de logs
     */
    private function checkLogRotation($log_file) {
        if (filesize($log_file) > $this->max_log_size) {
            $backup_file = $log_file . '.' . time() . '.bak';
            rename($log_file, $backup_file);
            
            // Comprimir archivo de backup si está disponible gzip
            if (function_exists('gzencode')) {
                $compressed = gzencode(file_get_contents($backup_file));
                file_put_contents($backup_file . '.gz', $compressed);
                unlink($backup_file);
            }
        }
    }
    
    /**
     * Escribir al archivo de forma segura
     */
    private function writeToFile($file_path, $content) {
        $handle = fopen($file_path, 'a');
        if ($handle) {
            if (flock($handle, LOCK_EX)) {
                fwrite($handle, $content);
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanOldLogs($days = 30) {
        $files = glob($this->log_path . '*.log*');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
    
    /**
     * Obtener estadísticas de logs
     */
    public function getStats() {
        $files = glob($this->log_path . '*.log');
        $total_size = 0;
        $file_count = count($files);
        
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
        
        return [
            'log_path' => $this->log_path,
            'file_count' => $file_count,
            'total_size' => $total_size,
            'total_size_formatted' => $this->formatBytes($total_size),
            'max_log_size' => $this->max_log_size,
            'log_level' => $this->log_level
        ];
    }
    
    /**
     * Formatear bytes
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Funciones helper para uso fácil
function log_security($message, $level = 'INFO', $context = []) {
    SecureLogger::getInstance()->logSecurity($message, $level, $context);
}

function log_auth($message, $level = 'INFO', $context = []) {
    SecureLogger::getInstance()->logAuth($message, $level, $context);
}

function log_app($message, $level = 'INFO', $context = []) {
    SecureLogger::getInstance()->logApp($message, $level, $context);
}

function log_error($message, $context = []) {
    SecureLogger::getInstance()->logError($message, $context);
}
?>