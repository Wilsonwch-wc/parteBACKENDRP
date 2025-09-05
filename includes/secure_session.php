<?php
/**
 * Manejador de sesiones seguras
 * Implementa configuraciones avanzadas de seguridad para sesiones
 */

class SecureSession {
    private static $instance = null;
    private $session_lifetime;
    private $regenerate_interval;
    private $max_idle_time;
    
    private function __construct() {
        $this->session_lifetime = env('SESSION_LIFETIME', 3600); // 1 hora por defecto
        $this->regenerate_interval = env('SESSION_REGENERATE_INTERVAL', 300); // 5 minutos
        $this->max_idle_time = env('SESSION_MAX_IDLE_TIME', 1800); // 30 minutos
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializar sesión segura
     */
    public function start() {
        // Configurar parámetros de sesión antes de iniciar
        $this->configureSession();
        
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Validar sesión existente
        $this->validateSession();
        
        // Regenerar ID si es necesario
        $this->handleRegeneration();
        
        // Actualizar timestamp de actividad
        $this->updateActivity();
    }
    
    /**
     * Configurar parámetros de sesión
     */
    private function configureSession() {
        // Configurar nombre de sesión personalizado
        session_name(env('SESSION_NAME', 'ANTERO_SESS'));
        
        // Configurar parámetros de cookies
        session_set_cookie_params([
            'lifetime' => $this->session_lifetime,
            'path' => '/',
            'domain' => env('SESSION_DOMAIN', ''),
            'secure' => env('SESSION_SECURE', false), // true en producción con HTTPS
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        // Configurar directorio de sesiones si está especificado
        $session_path = env('SESSION_SAVE_PATH', '');
        if (!empty($session_path)) {
            if (!is_dir($session_path)) {
                mkdir($session_path, 0700, true);
            }
            session_save_path($session_path);
        }
        
        // Configurar garbage collection
        ini_set('session.gc_maxlifetime', $this->session_lifetime);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);
        
        // Usar solo cookies para sesiones
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_trans_sid', 0);
        
        // Configurar entropy para mayor seguridad
        ini_set('session.entropy_length', 32);
        ini_set('session.hash_function', 'sha256');
    }
    
    /**
     * Validar sesión existente
     */
    private function validateSession() {
        // Verificar si la sesión ha expirado
        if (isset($_SESSION['created_at'])) {
            $session_age = time() - $_SESSION['created_at'];
            if ($session_age > $this->session_lifetime) {
                $this->destroy('Sesión expirada por tiempo de vida');
                return;
            }
        }
        
        // Verificar tiempo de inactividad
        if (isset($_SESSION['last_activity'])) {
            $idle_time = time() - $_SESSION['last_activity'];
            if ($idle_time > $this->max_idle_time) {
                $this->destroy('Sesión expirada por inactividad');
                return;
            }
        }
        
        // Verificar IP del usuario (opcional, puede causar problemas con proxies)
        if (env('SESSION_CHECK_IP', false) && isset($_SESSION['user_ip'])) {
            if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
                $this->destroy('IP de sesión no coincide');
                return;
            }
        }
        
        // Verificar User-Agent (detección básica de hijacking)
        if (env('SESSION_CHECK_USER_AGENT', true) && isset($_SESSION['user_agent'])) {
            $current_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($_SESSION['user_agent'] !== $current_agent) {
                $this->destroy('User-Agent de sesión no coincide');
                return;
            }
        }
    }
    
    /**
     * Manejar regeneración de ID de sesión
     */
    private function handleRegeneration() {
        $should_regenerate = false;
        
        // Regenerar en el primer acceso
        if (!isset($_SESSION['created_at'])) {
            $should_regenerate = true;
        }
        // Regenerar periódicamente
        elseif (isset($_SESSION['last_regeneration'])) {
            $time_since_regen = time() - $_SESSION['last_regeneration'];
            if ($time_since_regen > $this->regenerate_interval) {
                $should_regenerate = true;
            }
        }
        
        if ($should_regenerate) {
            $this->regenerateId();
        }
    }
    
    /**
     * Regenerar ID de sesión
     */
    public function regenerateId() {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
        
        // Log de regeneración para auditoría
        if (env('LOG_SESSION_EVENTS', false)) {
            error_log("Sesión regenerada - ID: " . session_id() . " - IP: " . $_SERVER['REMOTE_ADDR']);
        }
    }
    
    /**
     * Actualizar timestamp de actividad
     */
    private function updateActivity() {
        $_SESSION['last_activity'] = time();
        
        // Establecer timestamps iniciales si no existen
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
    }
    
    /**
     * Destruir sesión de forma segura
     */
    public function destroy($reason = 'Logout manual') {
        // Log del evento
        if (env('LOG_SESSION_EVENTS', false)) {
            $user_id = $_SESSION['user_id'] ?? 'Anónimo';
            error_log("Sesión destruida - Usuario: $user_id - Razón: $reason - IP: " . $_SERVER['REMOTE_ADDR']);
        }
        
        // Limpiar variables de sesión
        $_SESSION = [];
        
        // Eliminar cookie de sesión
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        // Destruir sesión
        session_destroy();
    }
    
    /**
     * Verificar si la sesión es válida
     */
    public function isValid() {
        return session_status() === PHP_SESSION_ACTIVE && 
               isset($_SESSION['user_id']) && 
               isset($_SESSION['created_at']) && 
               isset($_SESSION['last_activity']);
    }
    
    /**
     * Obtener información de la sesión
     */
    public function getInfo() {
        if (!$this->isValid()) {
            return null;
        }
        
        return [
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'created_at' => $_SESSION['created_at'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'last_regeneration' => $_SESSION['last_regeneration'] ?? null,
            'time_remaining' => $this->getTimeRemaining(),
            'idle_time' => time() - ($_SESSION['last_activity'] ?? time())
        ];
    }
    
    /**
     * Obtener tiempo restante de sesión
     */
    public function getTimeRemaining() {
        if (!isset($_SESSION['created_at'])) {
            return 0;
        }
        
        $elapsed = time() - $_SESSION['created_at'];
        return max(0, $this->session_lifetime - $elapsed);
    }
    
    /**
     * Extender sesión (renovar tiempo de vida)
     */
    public function extend() {
        if ($this->isValid()) {
            $_SESSION['created_at'] = time();
            $this->updateActivity();
            
            // Log de extensión
            if (env('LOG_SESSION_EVENTS', false)) {
                $user_id = $_SESSION['user_id'] ?? 'Anónimo';
                error_log("Sesión extendida - Usuario: $user_id - IP: " . $_SERVER['REMOTE_ADDR']);
            }
            
            return true;
        }
        return false;
    }
    
    /**
     * Limpiar sesiones expiradas (para ejecutar periódicamente)
     */
    public static function cleanupExpiredSessions() {
        // Forzar garbage collection
        session_gc();
        
        // Log de limpieza
        if (env('LOG_SESSION_EVENTS', false)) {
            error_log("Limpieza de sesiones expiradas ejecutada");
        }
    }
    
    /**
     * Obtener estadísticas de sesión
     */
    public function getStats() {
        return [
            'session_lifetime' => $this->session_lifetime,
            'regenerate_interval' => $this->regenerate_interval,
            'max_idle_time' => $this->max_idle_time,
            'current_session_valid' => $this->isValid(),
            'session_id' => session_id(),
            'session_status' => session_status()
        ];
    }
}

// Función helper para uso fácil
function secure_session_start() {
    return SecureSession::getInstance()->start();
}

function secure_session_destroy($reason = 'Logout manual') {
    return SecureSession::getInstance()->destroy($reason);
}

function secure_session_extend() {
    return SecureSession::getInstance()->extend();
}

function secure_session_info() {
    return SecureSession::getInstance()->getInfo();
}
?>