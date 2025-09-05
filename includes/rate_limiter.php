<?php
/**
 * Rate Limiter para controlar el número de peticiones
 * Implementa rate limiting basado en IP y tipo de petición
 */

class RateLimiter {
    private $conn;
    private $cache_dir;
    
    public function __construct() {
        $this->conn = getDB();
        $this->cache_dir = dirname(__DIR__) . '/cache/';
        
        // Crear directorio de cache si no existe
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        
        // Crear tabla de rate limiting si no existe
        $this->createRateLimitTable();
    }
    
    /**
     * Crear tabla para rate limiting
     */
    private function createRateLimitTable() {
        if (!$this->conn) return;
        
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            request_type VARCHAR(50) NOT NULL,
            request_count INT DEFAULT 1,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ip_type (ip_address, request_type),
            INDEX idx_window (window_start)
        )";
        
        $this->conn->query($sql);
    }
    
    /**
     * Verificar rate limit para login
     */
    public function checkLoginLimit($ip, $username = null) {
        $max_attempts = env('MAX_LOGIN_ATTEMPTS', 5);
        $window = env('LOGIN_LOCKOUT_TIME', 900); // 15 minutos
        
        return $this->checkLimit($ip, 'login', $max_attempts, $window);
    }
    
    /**
     * Verificar rate limit para API
     */
    public function checkApiLimit($ip) {
        $max_requests = env('API_RATE_LIMIT', 100);
        $window = env('API_RATE_WINDOW', 60); // 1 minuto
        
        return $this->checkLimit($ip, 'api', $max_requests, $window);
    }
    
    /**
     * Verificar rate limit general
     */
    public function checkGeneralLimit($ip) {
        $max_requests = env('RATE_LIMIT_REQUESTS', 60);
        $window = env('RATE_LIMIT_WINDOW', 60); // 1 minuto
        
        return $this->checkLimit($ip, 'general', $max_requests, $window);
    }
    
    /**
     * Verificar límite genérico
     */
    private function checkLimit($ip, $type, $max_requests, $window_seconds) {
        if (!$this->conn) {
            // Fallback a cache de archivos si no hay DB
            return $this->checkLimitFile($ip, $type, $max_requests, $window_seconds);
        }
        
        // Limpiar registros antiguos
        $this->cleanOldRecords($window_seconds);
        
        // Obtener o crear registro
        $stmt = $this->conn->prepare(
            "SELECT request_count, window_start FROM rate_limits 
             WHERE ip_address = ? AND request_type = ? 
             AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        
        $stmt->bind_param('ssi', $ip, $type, $window_seconds);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Registro existe, verificar límite
            if ($row['request_count'] >= $max_requests) {
                $stmt->close();
                return false;
            }
            
            // Incrementar contador
            $update_stmt = $this->conn->prepare(
                "UPDATE rate_limits SET request_count = request_count + 1 
                 WHERE ip_address = ? AND request_type = ? 
                 AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)"
            );
            $update_stmt->bind_param('ssi', $ip, $type, $window_seconds);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Crear nuevo registro
            $insert_stmt = $this->conn->prepare(
                "INSERT INTO rate_limits (ip_address, request_type, request_count) 
                 VALUES (?, ?, 1)"
            );
            $insert_stmt->bind_param('ss', $ip, $type);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        
        $stmt->close();
        return true;
    }
    
    /**
     * Rate limiting usando archivos como fallback
     */
    private function checkLimitFile($ip, $type, $max_requests, $window_seconds) {
        $filename = $this->cache_dir . 'rate_' . md5($ip . '_' . $type) . '.json';
        $now = time();
        
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true);
            
            // Verificar si la ventana ha expirado
            if ($now - $data['window_start'] > $window_seconds) {
                // Nueva ventana
                $data = ['window_start' => $now, 'count' => 1];
            } else {
                // Misma ventana, incrementar
                if ($data['count'] >= $max_requests) {
                    return false;
                }
                $data['count']++;
            }
        } else {
            // Primer request
            $data = ['window_start' => $now, 'count' => 1];
        }
        
        file_put_contents($filename, json_encode($data));
        return true;
    }
    
    /**
     * Limpiar registros antiguos
     */
    private function cleanOldRecords($window_seconds) {
        if (!$this->conn) return;
        
        $stmt = $this->conn->prepare(
            "DELETE FROM rate_limits 
             WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->bind_param('i', $window_seconds);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Obtener información de rate limit para una IP
     */
    public function getRateLimitInfo($ip, $type) {
        if (!$this->conn) return null;
        
        $stmt = $this->conn->prepare(
            "SELECT request_count, window_start, 
             TIMESTAMPDIFF(SECOND, window_start, NOW()) as elapsed 
             FROM rate_limits 
             WHERE ip_address = ? AND request_type = ?
             ORDER BY window_start DESC LIMIT 1"
        );
        
        $stmt->bind_param('ss', $ip, $type);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Resetear rate limit para una IP y tipo
     */
    public function resetRateLimit($ip, $type) {
        if (!$this->conn) return false;
        
        $stmt = $this->conn->prepare(
            "DELETE FROM rate_limits WHERE ip_address = ? AND request_type = ?"
        );
        $stmt->bind_param('ss', $ip, $type);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
}
?>