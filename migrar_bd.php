<?php
/**
 * MIGRADOR DE BASE DE DATOS
 * 
 * Este archivo ejecuta las migraciones necesarias para actualizar
 * la base de datos del sistema de gestión de ropa.
 * 
 * INSTRUCCIONES DE USO:
 * 1. Ejecutar desde el navegador: http://tu-dominio/antero/migrar_bd.php
 * 2. O ejecutar desde línea de comandos: php migrar_bd.php
 * 
 * IMPORTANTE: Hacer backup de la base de datos antes de ejecutar
 * 
 * @author Sistema de Gestión de Ropa
 * @version 1.0
 * @date 2025-01-21
 */

// Configuración estricta de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

// Verificar permisos de administrador (solo en entorno web)
if (isset($_SERVER['REQUEST_METHOD'])) {
    checkPermission('admin');
}

/**
 * Clase para manejar migraciones de base de datos
 */
class DatabaseMigrator {
    
    private $conn;
    private $migrationsPath;
    
    public function __construct() {
        $this->conn = getDB();
        $this->migrationsPath = __DIR__ . '/migrations/';
        
        if (!$this->conn) {
            throw new Exception("Error de conexión a la base de datos");
        }
        
        // Crear tabla de migraciones si no existe
        $this->createMigrationsTable();
    }
    
    /**
     * Crear tabla para trackear migraciones ejecutadas
     */
    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) NOT NULL,
            `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `migration_unique` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$this->conn->query($sql)) {
            throw new Exception("Error creando tabla migrations: " . $this->conn->error);
        }
    }
    
    /**
     * Obtener migraciones ya ejecutadas
     */
    private function getExecutedMigrations() {
        $result = $this->conn->query("SELECT migration FROM migrations");
        $executed = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $executed[] = $row['migration'];
            }
        }
        
        return $executed;
    }
    
    /**
     * Obtener archivos de migración disponibles
     */
    private function getAvailableMigrations() {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }
        
        $files = glob($this->migrationsPath . '*.sql');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = basename($file);
        }
        
        sort($migrations); // Ejecutar en orden alfabético
        return $migrations;
    }
    
    /**
     * Ejecutar una migración específica
     */
    private function executeMigration($migrationFile) {
        $filePath = $this->migrationsPath . $migrationFile;
        
        if (!file_exists($filePath)) {
            throw new Exception("Archivo de migración no encontrado: $migrationFile");
        }
        
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new Exception("Error leyendo archivo de migración: $migrationFile");
        }
        
        // Separar queries por punto y coma (manejo básico de múltiples queries)
        $queries = explode(';', $sql);
        $executedQueries = 0;
        
        // Iniciar transacción para rollback en caso de error
        $this->conn->begin_transaction();
        
        try {
            foreach ($queries as $query) {
                $query = trim($query);
                if (empty($query) || substr($query, 0, 2) === '--') {
                    continue; // Saltar comentarios y líneas vacías
                }
                
                if (!$this->conn->query($query)) {
                    throw new Exception("Error ejecutando query: " . $this->conn->error . "\nQuery: $query");
                }
                $executedQueries++;
            }
            
            // Registrar migración como ejecutada
            $stmt = $this->conn->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->bind_param("s", $migrationFile);
            if (!$stmt->execute()) {
                throw new Exception("Error registrando migración: " . $this->conn->error);
            }
            
            $this->conn->commit();
            return $executedQueries;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Ejecutar todas las migraciones pendientes
     */
    public function runMigrations() {
        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();
        $pending = array_diff($available, $executed);
        
        $results = [];
        
        if (empty($pending)) {
            $results[] = [
                'status' => 'info',
                'message' => 'No hay migraciones pendientes por ejecutar.'
            ];
            return $results;
        }
        
        foreach ($pending as $migration) {
            try {
                $queriesCount = $this->executeMigration($migration);
                $results[] = [
                    'status' => 'success',
                    'message' => "✅ Migración '$migration' ejecutada correctamente ($queriesCount queries)."
                ];
            } catch (Exception $e) {
                $results[] = [
                    'status' => 'error',
                    'message' => "❌ Error ejecutando migración '$migration': " . $e->getMessage()
                ];
                // Detener ejecución si hay un error
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Obtener status de migraciones
     */
    public function getStatus() {
        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();
        $pending = array_diff($available, $executed);
        
        return [
            'executed' => $executed,
            'available' => $available,
            'pending' => $pending,
            'total_available' => count($available),
            'total_executed' => count($executed),
            'total_pending' => count($pending)
        ];
    }
}

// Ejecutar migraciones
try {
    $migrator = new DatabaseMigrator();
    
    // Si se ejecuta desde línea de comandos
    if (php_sapi_name() === 'cli') {
        echo "\n=== MIGRADOR DE BASE DE DATOS ===\n\n";
        
        $status = $migrator->getStatus();
        echo "Migraciones disponibles: {$status['total_available']}\n";
        echo "Migraciones ejecutadas: {$status['total_executed']}\n";
        echo "Migraciones pendientes: {$status['total_pending']}\n\n";
        
        if ($status['total_pending'] > 0) {
            echo "Ejecutando migraciones...\n\n";
            $results = $migrator->runMigrations();
            
            foreach ($results as $result) {
                echo $result['message'] . "\n";
            }
        }
        
        echo "\n=== MIGRACIÓN COMPLETADA ===\n";
        
    } else {
        // Ejecutar desde navegador web
        include 'includes/header.php';
        ?>
        
        <div class="container mt-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">🔧 Migrador de Base de Datos</h4>
                        </div>
                        <div class="card-body">
                            
                            <?php
                            $status = $migrator->getStatus();
                            ?>
                            
                            <div class="alert alert-info">
                                <strong>Estado actual:</strong><br>
                                📦 Migraciones disponibles: <strong><?= $status['total_available'] ?></strong><br>
                                ✅ Migraciones ejecutadas: <strong><?= $status['total_executed'] ?></strong><br>
                                ⏳ Migraciones pendientes: <strong><?= $status['total_pending'] ?></strong>
                            </div>
                            
                            <?php if (isset($_POST['execute_migrations'])): ?>
                                <div class="mt-4">
                                    <h5>Resultados de la migración:</h5>
                                    <?php
                                    $results = $migrator->runMigrations();
                                    foreach ($results as $result):
                                        $alertClass = $result['status'] === 'error' ? 'alert-danger' : 
                                                     ($result['status'] === 'success' ? 'alert-success' : 'alert-info');
                                    ?>
                                        <div class="alert <?= $alertClass ?>" role="alert">
                                            <?= htmlspecialchars($result['message']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($status['total_pending'] > 0): ?>
                                <form method="POST" class="mt-4">
                                    <div class="alert alert-warning">
                                        <strong>⚠️ IMPORTANTE:</strong> Se recomienda hacer un backup de la base de datos antes de ejecutar las migraciones.
                                    </div>
                                    
                                    <h5>Migraciones pendientes:</h5>
                                    <ul>
                                        <?php foreach ($status['pending'] as $pending): ?>
                                            <li><code><?= htmlspecialchars($pending) ?></code></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    
                                    <button type="submit" name="execute_migrations" class="btn btn-primary btn-lg" 
                                            onclick="return confirm('¿Estás seguro de que quieres ejecutar las migraciones? Es recomendable hacer un backup primero.')">
                                        🚀 Ejecutar Migraciones
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <strong>✅ Todo actualizado!</strong> No hay migraciones pendientes.
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        include 'includes/footer.php';
    }
    
} catch (Exception $e) {
    if (php_sapi_name() === 'cli') {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        die("Error en el migrador: " . htmlspecialchars($e->getMessage()));
    }
}
?>
