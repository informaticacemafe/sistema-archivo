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

// Función para registrar eventos en el log de actividades (narrativo, con contexto)
// $tipo_entidad: 'paciente', 'hc', 'movimiento', 'fuente'
// $id_entidad: ID del registro afectado
// $accion: 'CREAR', 'EDITAR', 'ELIMINAR'
// $resumen: texto legible describiendo el evento
// $detalle_anterior: array asociativo con valores previos (o null)
// $detalle_nuevo: array asociativo con valores nuevos (o null)
function registrarLog($tipo_entidad, $id_entidad, $accion, $resumen, $detalle_anterior = null, $detalle_nuevo = null) {
    global $conexion;
    
    $usuario_id = $_SESSION['usuario_id'];
    $detalle_anterior_json = $detalle_anterior !== null ? json_encode($detalle_anterior, JSON_UNESCAPED_UNICODE) : null;
    $detalle_nuevo_json = $detalle_nuevo !== null ? json_encode($detalle_nuevo, JSON_UNESCAPED_UNICODE) : null;
    
    $stmt = $conexion->prepare("INSERT INTO log_actividades (usuario_id, tipo_entidad, id_entidad, accion, resumen, detalle_anterior, detalle_nuevo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $usuario_id, $tipo_entidad, $id_entidad, $accion, $resumen, $detalle_anterior_json, $detalle_nuevo_json);
    $stmt->execute();
    $id_log = $conexion->insert_id;
    $stmt->close();
    
    return $id_log;
}
?>
