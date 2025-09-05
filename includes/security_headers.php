<?php
/**
 * Headers de seguridad HTTP
 * Implementa headers de seguridad estándar para proteger la aplicación
 */

class SecurityHeaders {
    private static $applied = false;
    
    /**
     * Aplicar todos los headers de seguridad
     */
    public static function apply() {
        if (self::$applied) {
            return; // Evitar aplicar headers múltiples veces
        }
        
        // Verificar que no se hayan enviado headers aún
        if (headers_sent()) {
            error_log('Advertencia: Intentando aplicar headers de seguridad después de que se enviaron headers');
            return;
        }
        
        self::setContentSecurityPolicy();
        self::setXFrameOptions();
        self::setXContentTypeOptions();
        self::setXSSProtection();
        self::setReferrerPolicy();
        self::setPermissionsPolicy();
        self::setStrictTransportSecurity();
        self::setExpectCT();
        self::removeServerInfo();
        self::setCacheControl();
        
        self::$applied = true;
    }
    
    /**
     * Content Security Policy (CSP)
     */
    private static function setContentSecurityPolicy() {
        $csp_enabled = env('CSP_ENABLED', true);
        if (!$csp_enabled) return;
        
        $csp_directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "child-src 'none'",
            "frame-src 'none'",
            "worker-src 'none'",
            "manifest-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests"
        ];
        
        // Permitir configuración personalizada desde .env
        $custom_csp = env('CSP_CUSTOM', '');
        if (!empty($custom_csp)) {
            $csp_policy = $custom_csp;
        } else {
            $csp_policy = implode('; ', $csp_directives);
        }
        
        // Usar report-only en desarrollo
        $csp_report_only = env('CSP_REPORT_ONLY', false);
        $header_name = $csp_report_only ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        
        header($header_name . ': ' . $csp_policy);
    }
    
    /**
     * X-Frame-Options
     */
    private static function setXFrameOptions() {
        $frame_options = env('X_FRAME_OPTIONS', 'DENY');
        header('X-Frame-Options: ' . $frame_options);
    }
    
    /**
     * X-Content-Type-Options
     */
    private static function setXContentTypeOptions() {
        header('X-Content-Type-Options: nosniff');
    }
    
    /**
     * X-XSS-Protection
     */
    private static function setXSSProtection() {
        $xss_protection = env('X_XSS_PROTECTION', '1; mode=block');
        header('X-XSS-Protection: ' . $xss_protection);
    }
    
    /**
     * Referrer-Policy
     */
    private static function setReferrerPolicy() {
        $referrer_policy = env('REFERRER_POLICY', 'strict-origin-when-cross-origin');
        header('Referrer-Policy: ' . $referrer_policy);
    }
    
    /**
     * Permissions-Policy (anteriormente Feature-Policy)
     */
    private static function setPermissionsPolicy() {
        $permissions_enabled = env('PERMISSIONS_POLICY_ENABLED', true);
        if (!$permissions_enabled) return;
        
        $permissions = [
            'camera=()' => env('PERMISSIONS_CAMERA', false),
            'microphone=()' => env('PERMISSIONS_MICROPHONE', false),
            'geolocation=()' => env('PERMISSIONS_GEOLOCATION', false),
            'payment=()' => env('PERMISSIONS_PAYMENT', false),
            'usb=()' => env('PERMISSIONS_USB', false),
            'magnetometer=()' => env('PERMISSIONS_MAGNETOMETER', false),
            'accelerometer=()' => env('PERMISSIONS_ACCELEROMETER', false),
            'gyroscope=()' => env('PERMISSIONS_GYROSCOPE', false),
            'fullscreen=(self)' => env('PERMISSIONS_FULLSCREEN', true),
            'picture-in-picture=()' => env('PERMISSIONS_PIP', false)
        ];
        
        $enabled_permissions = [];
        foreach ($permissions as $permission => $enabled) {
            if ($enabled) {
                $enabled_permissions[] = $permission;
            }
        }
        
        if (!empty($enabled_permissions)) {
            header('Permissions-Policy: ' . implode(', ', $enabled_permissions));
        }
    }
    
    /**
     * Strict-Transport-Security (HSTS)
     */
    private static function setStrictTransportSecurity() {
        $hsts_enabled = env('HSTS_ENABLED', false);
        if (!$hsts_enabled || !self::isHTTPS()) return;
        
        $max_age = env('HSTS_MAX_AGE', 31536000); // 1 año por defecto
        $include_subdomains = env('HSTS_INCLUDE_SUBDOMAINS', true);
        $preload = env('HSTS_PRELOAD', false);
        
        $hsts_value = 'max-age=' . $max_age;
        
        if ($include_subdomains) {
            $hsts_value .= '; includeSubDomains';
        }
        
        if ($preload) {
            $hsts_value .= '; preload';
        }
        
        header('Strict-Transport-Security: ' . $hsts_value);
    }
    
    /**
     * Expect-CT
     */
    private static function setExpectCT() {
        $expect_ct_enabled = env('EXPECT_CT_ENABLED', false);
        if (!$expect_ct_enabled || !self::isHTTPS()) return;
        
        $max_age = env('EXPECT_CT_MAX_AGE', 86400); // 24 horas
        $enforce = env('EXPECT_CT_ENFORCE', false);
        $report_uri = env('EXPECT_CT_REPORT_URI', '');
        
        $expect_ct_value = 'max-age=' . $max_age;
        
        if ($enforce) {
            $expect_ct_value .= ', enforce';
        }
        
        if (!empty($report_uri)) {
            $expect_ct_value .= ', report-uri="' . $report_uri . '"';
        }
        
        header('Expect-CT: ' . $expect_ct_value);
    }
    
    /**
     * Remover información del servidor
     */
    private static function removeServerInfo() {
        $remove_server_info = env('REMOVE_SERVER_INFO', true);
        if (!$remove_server_info) return;
        
        // Remover header Server si es posible
        header_remove('Server');
        header_remove('X-Powered-By');
        
        // Agregar header personalizado
        $custom_server = env('CUSTOM_SERVER_HEADER', '');
        if (!empty($custom_server)) {
            header('Server: ' . $custom_server);
        }
    }
    
    /**
     * Control de caché
     */
    private static function setCacheControl() {
        $cache_control_enabled = env('CACHE_CONTROL_ENABLED', true);
        if (!$cache_control_enabled) return;
        
        // Para páginas con datos sensibles
        if (self::isSensitivePage()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        } else {
            // Para recursos estáticos
            $cache_max_age = env('CACHE_MAX_AGE', 3600);
            header('Cache-Control: public, max-age=' . $cache_max_age);
        }
    }
    
    /**
     * Verificar si es HTTPS
     */
    private static function isHTTPS() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        );
    }
    
    /**
     * Verificar si es una página sensible
     */
    private static function isSensitivePage() {
        $sensitive_pages = [
            'login.php',
            'admin',
            'dashboard',
            'profile',
            'settings',
            'password',
            'account'
        ];
        
        $current_page = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($sensitive_pages as $page) {
            if (strpos($current_page, $page) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Aplicar headers específicos para API
     */
    public static function applyAPIHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        
        // CORS ya manejado en api_catalogo.php
    }
    
    /**
     * Aplicar headers para descarga de archivos
     */
    public static function applyDownloadHeaders($filename, $content_type = 'application/octet-stream') {
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    
    /**
     * Obtener información de headers aplicados
     */
    public static function getAppliedHeaders() {
        return [
            'applied' => self::$applied,
            'https' => self::isHTTPS(),
            'sensitive_page' => self::isSensitivePage(),
            'headers_sent' => headers_sent()
        ];
    }
}

// Función helper para aplicar headers automáticamente
function apply_security_headers() {
    SecurityHeaders::apply();
}

// Auto-aplicar headers si está habilitado
if (env('AUTO_APPLY_SECURITY_HEADERS', true)) {
    SecurityHeaders::apply();
}
?>