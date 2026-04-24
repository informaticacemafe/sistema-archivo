<?php
session_start();
require_once 'conexion.php';

// Actualizar fecha de logout en sesiones
if (isset($_SESSION['id_sesion'])) {
    $stmt = $conexion->prepare("UPDATE sesiones SET fecha_logout = NOW() WHERE id_sesion = ?");
    $stmt->bind_param("i", $_SESSION['id_sesion']);
    $stmt->execute();
    $stmt->close();
}

// Destruir sesión
session_unset();
session_destroy();

// Redirigir al login
header('Location: index.php');
exit();
?>
