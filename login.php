<?php
require_once 'db.php'; // Ya incluye secure_session_start()
require_once 'includes/rate_limiter.php';

// Funciones reutilizables definidas si no existen
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
    }
}

if (!function_exists('record_login_attempt')) {
    function record_login_attempt($username) {
        $conn = getDB();
        $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (?, ?, NOW())");
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("ss", $username, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('check_login_attempts')) {
    function check_login_attempts($username) {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                              WHERE username = ? AND ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("ss", $username, $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['attempts'] >= 5; // Bloquear si hay 5 o más intentos
    }
}

// Conexión a la base de datos
$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

$error = "";
$is_blocked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar rate limiting para login
    $rateLimiter = new RateLimiter();
    if (!$rateLimiter->checkLoginLimit($_SERVER['REMOTE_ADDR'])) {
        $error = "Demasiados intentos de login. Intente más tarde.";
    } else {
        // Verificación del token CSRF
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Error de seguridad: token inválido";
        } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Verificar intentos de login
        if (check_login_attempts($username)) {
            $error = "Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.";
            $is_blocked = true;
        } else {
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Login exitoso
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_login'] = date('Y-m-d H:i:s');
                    
                    // Regenerar ID de sesión por seguridad
                    SecureSession::getInstance()->regenerateId();
                    
                    // Limpiar intentos fallidos
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['last_attempt_time']);
                    
                    // Log de login exitoso
                    if (env('LOG_SESSION_EVENTS', false)) {
                        error_log("Login exitoso - Usuario: {$user['username']} - IP: " . $_SERVER['REMOTE_ADDR']);
                    }
                    
                    header("Location: index.php");
                    exit();
                }
            }
            // Registrar intento fallido
            record_login_attempt($username);
            
            // Log de intento fallido
            if (env('LOG_SESSION_EVENTS', false)) {
                error_log("Login fallido - Usuario: $username - IP: " . $_SERVER['REMOTE_ADDR']);
            }
            
            $error = "Usuario o contraseña incorrectos";
        }
    }
    }
}

// Generar token CSRF
generate_csrf_token();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Galería de Gorras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a, #4a4a4a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Arial', sans-serif;
        }
        .login-container {
            background: #fff;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        .login-title {
            color: #ff4500;
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 2rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .form-control {
            border-radius: 25px;
            padding: 0.9rem;
            font-size: 1.1rem;
            border: 2px solid #ced4da;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: #ff4500;
            box-shadow: none;
        }
        .btn-login {
            background: #ff4500;
            border: none;
            border-radius: 25px;
            padding: 0.9rem;
            font-size: 1.2rem;
            font-weight: bold;
            transition: background 0.3s ease;
        }
        .btn-login:hover {
            background: #e03d00;
        }
        .input-group-text {
            background: transparent;
            border: 2px solid #ced4da;
            border-radius: 0 25px 25px 0;
            cursor: pointer;
        }
        .alert {
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .cap-icon {
            position: absolute;
            top: -50px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 3rem;
            color: #ff4500;
            opacity: 0.2;
        }
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            .login-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <i class="bi bi-cap cap-icon"></i>
        <h1 class="login-title">Accesorios</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" <?php if ($is_blocked) echo 'style="display:none;"'; ?>>
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label for="username" class="form-label fw-bold">Usuario</label>
                <input type="text" class="form-control" id="username" name="username" required placeholder="Ingresa tu usuario">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label fw-bold">Contraseña</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Ingresa tu contraseña">
                    <span class="input-group-text" id="togglePassword">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-login w-100">Ingresar</button>
        </form>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        togglePassword.addEventListener('click', () => {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            toggleIcon.classList.toggle('bi-eye');
            toggleIcon.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>