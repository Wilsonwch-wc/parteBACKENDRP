<?php
/**
 * Validador de archivos seguro
 * Implementa validaciones estrictas para subida de archivos
 */

class FileValidator {
    private $allowed_extensions;
    private $allowed_mime_types;
    private $max_file_size;
    private $upload_path;
    
    public function __construct() {
        $this->allowed_extensions = explode(',', env('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,webp'));
        $this->max_file_size = env('MAX_FILE_SIZE', 5242880); // 5MB por defecto
        $this->upload_path = env('UPLOAD_PATH', 'uploads/productos/');
        
        // MIME types permitidos (más restrictivo)
        $this->allowed_mime_types = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/webp'
        ];
    }
    
    /**
     * Validar archivo subido
     */
    public function validateFile($file) {
        $errors = [];
        
        // Verificar si hay errores en la subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Verificar tamaño
        if ($file['size'] > $this->max_file_size) {
            $errors[] = 'El archivo es demasiado grande. Máximo permitido: ' . 
                       $this->formatBytes($this->max_file_size);
        }
        
        if ($file['size'] === 0) {
            $errors[] = 'El archivo está vacío';
        }
        
        // Verificar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            $errors[] = 'Extensión no permitida. Permitidas: ' . 
                       implode(', ', $this->allowed_extensions);
        }
        
        // Verificar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_mime_types)) {
            $errors[] = 'Tipo de archivo no permitido: ' . $mime_type;
        }
        
        // Verificar que el MIME type coincida con la extensión
        if (!$this->validateMimeExtensionMatch($mime_type, $extension)) {
            $errors[] = 'El tipo de archivo no coincide con la extensión';
        }
        
        // Verificar contenido del archivo (magic bytes)
        if (!$this->validateFileContent($file['tmp_name'], $extension)) {
            $errors[] = 'El contenido del archivo no es válido para una imagen';
        }
        
        // Verificar nombre de archivo
        if (!$this->validateFileName($file['name'])) {
            $errors[] = 'Nombre de archivo no válido';
        }
        
        // Escanear por contenido malicioso
        if ($this->containsMaliciousContent($file['tmp_name'])) {
            $errors[] = 'El archivo contiene contenido potencialmente malicioso';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mime_type,
            'extension' => $extension,
            'size' => $file['size']
        ];
    }
    
    /**
     * Validar que MIME type coincida con extensión
     */
    private function validateMimeExtensionMatch($mime_type, $extension) {
        $valid_combinations = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp']
        ];
        
        return isset($valid_combinations[$extension]) && 
               in_array($mime_type, $valid_combinations[$extension]);
    }
    
    /**
     * Validar contenido del archivo usando magic bytes
     */
    private function validateFileContent($file_path, $extension) {
        $handle = fopen($file_path, 'rb');
        if (!$handle) return false;
        
        $header = fread($handle, 12);
        fclose($handle);
        
        // Magic bytes para diferentes formatos
        $magic_bytes = [
            'jpg' => ['\xFF\xD8\xFF'],
            'jpeg' => ['\xFF\xD8\xFF'],
            'png' => ['\x89\x50\x4E\x47\x0D\x0A\x1A\x0A'],
            'webp' => ['RIFF', 'WEBP']
        ];
        
        if (!isset($magic_bytes[$extension])) {
            return false;
        }
        
        foreach ($magic_bytes[$extension] as $magic) {
            if (strpos($header, $magic) === 0) {
                return true;
            }
            // Para WebP, verificar tanto RIFF como WEBP
            if ($extension === 'webp' && 
                (strpos($header, 'RIFF') === 0 && strpos($header, 'WEBP') !== false)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validar nombre de archivo
     */
    private function validateFileName($filename) {
        // Verificar longitud
        if (strlen($filename) > 255) {
            return false;
        }
        
        // Verificar caracteres peligrosos
        $dangerous_chars = ['<', '>', ':', '"', '|', '?', '*', '\0', '\n', '\r'];
        foreach ($dangerous_chars as $char) {
            if (strpos($filename, $char) !== false) {
                return false;
            }
        }
        
        // Verificar nombres reservados en Windows
        $reserved_names = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 
                          'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 
                          'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 
                          'LPT7', 'LPT8', 'LPT9'];
        
        $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
        if (in_array(strtoupper($name_without_ext), $reserved_names)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Escanear contenido malicioso
     */
    private function containsMaliciousContent($file_path) {
        $content = file_get_contents($file_path, false, null, 0, 8192); // Leer primeros 8KB
        
        // Patrones sospechosos
        $malicious_patterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\(/i',
            '/base64_decode/i',
            '/shell_exec/i',
            '/system\(/i',
            '/exec\(/i',
            '/passthru/i',
            '/file_get_contents/i',
            '/file_put_contents/i',
            '/fopen/i',
            '/fwrite/i'
        ];
        
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                error_log("Contenido malicioso detectado en archivo: " . $file_path . " - Patrón: " . $pattern);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generar nombre de archivo seguro
     */
    public function generateSecureFileName($original_name) {
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return $timestamp . '_' . $random . '.' . $extension;
    }
    
    /**
     * Obtener mensaje de error de subida
     */
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'El archivo excede el tamaño máximo permitido por el servidor';
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo excede el tamaño máximo permitido por el formulario';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió parcialmente';
            case UPLOAD_ERR_NO_FILE:
                return 'No se subió ningún archivo';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en disco';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la subida del archivo';
            default:
                return 'Error desconocido en la subida del archivo';
        }
    }
    
    /**
     * Formatear bytes a formato legible
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Crear directorio de subida si no existe
     */
    public function ensureUploadDirectory() {
        $full_path = dirname(__DIR__) . '/' . $this->upload_path;
        
        if (!is_dir($full_path)) {
            if (!mkdir($full_path, 0755, true)) {
                throw new Exception('No se pudo crear el directorio de subida: ' . $full_path);
            }
        }
        
        // Verificar permisos de escritura
        if (!is_writable($full_path)) {
            throw new Exception('El directorio de subida no tiene permisos de escritura: ' . $full_path);
        }
        
        return $full_path;
    }
}
?>