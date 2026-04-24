<?php
session_start();
require_once 'conexion.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (!empty($username) && !empty($password)) {
        $stmt = $conexion->prepare("SELECT id_usuario, username, password, nombre, apellido, rol, activo FROM usuarios WHERE username = ? AND activo = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows == 1) {
            $usuario = $resultado->fetch_assoc();
            
            if (password_verify($password, $usuario['password'])) {
                // Login exitoso
                $_SESSION['usuario_id'] = $usuario['id_usuario'];
                $_SESSION['username'] = $usuario['username'];
                $_SESSION['nombre_completo'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                $_SESSION['rol'] = $usuario['rol'];
                $_SESSION['login_time'] = time();
                
                // Registrar sesión
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $stmt_sesion = $conexion->prepare("INSERT INTO sesiones (usuario_id, ip_address, user_agent) VALUES (?, ?, ?)");
                $stmt_sesion->bind_param("iss", $usuario['id_usuario'], $ip, $user_agent);
                $stmt_sesion->execute();
                $_SESSION['id_sesion'] = $conexion->insert_id;
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
        $stmt->close();
    } else {
        $error = 'Por favor complete todos los campos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Sistema de Gestión de Historias Clínicas</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Sistema de Gestión</h1>
                <h2>Historias Clínicas</h2>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
            </form>
            
            <div class="login-footer">
                <p>Sistema de Gestión de HC v1.0</p>
            </div>
        </div>
    </div>
</body>
</html>
