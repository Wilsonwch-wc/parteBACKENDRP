<?php
require_once 'db.php';
// No necesitamos checkPermission aquí ya que es la página de login

$conn = getDB();
if (!$conn) {
    die("Error de conexión con la base de datos");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, role FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: index.php");
    } else {
        $error = "Usuario o contraseña inválidos";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background-color: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            margin: 1rem auto;
        }
        .login-title {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            text-align: center;
        }
        .form-control {
            padding: 0.8rem;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        .btn-login {
            padding: 0.8rem;
            font-size: 1.1rem;
            background-color: #0d6efd;
            border: none;
        }
        .btn-login:hover {
            background-color: #0b5ed7;
        }
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="login-container">
                    <h3 class="login-title">Iniciar Sesión</h3>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   required 
                                   autocomplete="username"
                                   placeholder="Ingrese su usuario">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required
                                   autocomplete="current-password"
                                   placeholder="Ingrese su contraseña">
                        </div>
                        <button type="submit" class="btn btn-primary btn-login w-100">
                            Ingresar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
