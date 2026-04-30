<?php
session_start();
require_once 'conexion.php';

$numeropaciente = isset($_GET['numeropaciente']) ? trim($_GET['numeropaciente']) : '';
$excluir_id = isset($_GET['excluir_id']) ? intval($_GET['excluir_id']) : 0;

header('Content-Type: application/json; charset=utf-8');

if (empty($numeropaciente) || !is_numeric($numeropaciente)) {
    echo json_encode(['existe' => false]);
    exit();
}

$valor = intval($numeropaciente);

if ($excluir_id > 0) {
    $stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE numeropaciente = ? AND id_paciente != ?");
    $stmt->bind_param("ii", $valor, $excluir_id);
} else {
    $stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE numeropaciente = ?");
    $stmt->bind_param("i", $valor);
}
$stmt->execute();
$resultado = $stmt->get_result();

$existe = $resultado->num_rows > 0;
$stmt->close();

echo json_encode(['existe' => $existe]);
