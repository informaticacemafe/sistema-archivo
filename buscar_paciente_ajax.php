<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

header('Content-Type: application/json');

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($busqueda) < 2) {
    echo json_encode(array());
    exit();
}

$buscar_param = "%{$busqueda}%";

$stmt = $conexion->prepare("
    SELECT id_paciente, 
           CONCAT(apellido, ', ', nombre) as nombre_completo,
           CONCAT(tipo_documento, ' ', numero_documento) as documento,
           numero_documento
    FROM pacientes 
    WHERE numero_documento LIKE ? 
       OR CONCAT(apellido, ' ', nombre) LIKE ? 
       OR CONCAT(nombre, ' ', apellido) LIKE ?
    ORDER BY apellido, nombre
    LIMIT 10
");

$stmt->bind_param("sss", $buscar_param, $buscar_param, $buscar_param);
$stmt->execute();
$resultado = $stmt->get_result();

$pacientes = array();
while ($row = $resultado->fetch_assoc()) {
    $pacientes[] = $row;
}

echo json_encode($pacientes);
?>