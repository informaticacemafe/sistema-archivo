<?php
// Verificar sesión y autenticación
if (!isset($_SESSION)) {
    session_start();
}

// Tiempo de expiración de sesión (30 minutos)
$timeout_duration = 1800;

// Verificar si está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

// Verificar timeout de sesión
if (isset($_SESSION['login_time'])) {
    $elapsed_time = time() - $_SESSION['login_time'];
    
    if ($elapsed_time > $timeout_duration) {
        // Sesión expirada
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=1');
        exit();
    }
}

// Actualizar tiempo de última actividad
$_SESSION['login_time'] = time();

// Función para verificar permisos por rol
function tienePermiso($roles_permitidos) {
    if (!is_array($roles_permitidos)) {
        $roles_permitidos = array($roles_permitidos);
    }
    return in_array($_SESSION['rol'], $roles_permitidos);
}

// Función para registrar auditoría
function registrarAuditoria($tabla, $id_registro, $campo, $valor_anterior, $valor_nuevo, $accion = 'UPDATE') {
    global $conexion;
    
    $usuario_id = $_SESSION['usuario_id'];
    
    $stmt = $conexion->prepare("INSERT INTO auditoria (tabla, id_registro, campo, valor_anterior, valor_nuevo, usuario_id, accion) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssds", $tabla, $id_registro, $campo, $valor_anterior, $valor_nuevo, $usuario_id, $accion);
    $stmt->execute();
    $stmt->close();
}
?>
