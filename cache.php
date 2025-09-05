<?php
class Cache {
    private $cache_path = 'cache/';
    private $cache_time = 3600; // 1 hora
    private $max_size = 100 * 1024 * 1024; // 100MB límite

    public function __construct() {
        if (!is_dir($this->cache_path)) {
            mkdir($this->cache_path, 0777, true);
        }
    }

    public function get($key) {
        $file = $this->cache_path . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < $this->cache_time) {
            return unserialize(file_get_contents($file));
        }
        return false;
    }

    public function set($key, $data) {
        // Verificar tamaño del caché
        if ($this->getCacheSize() > $this->max_size) {
            $this->cleanup();
        }

        $file = $this->cache_path . md5($key) . '.cache';
        try {
            return file_put_contents($file, serialize($data));
        } catch (Exception $e) {
            error_log("Error al escribir caché: " . $e->getMessage());
            return false;
        }
    }

    public function delete($key) {
        $file = $this->cache_path . md5($key) . '.cache';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }

    public function clear() {
        $files = glob($this->cache_path . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    private function getCacheSize() {
        $size = 0;
        foreach (glob($this->cache_path . "*.cache") as $file) {
            $size += filesize($file);
        }
        return $size;
    }

    private function cleanup() {
        $files = glob($this->cache_path . "*.cache");
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        while ($this->getCacheSize() > $this->max_size && !empty($files)) {
            unlink(array_shift($files));
        }
    }
} 