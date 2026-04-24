<?php
session_start();
require_once 'conexion.php';

$numero_hc = isset($_GET['numero_hc']) ? trim($_GET['numero_hc']) : '';
$id_fuente = isset($_GET['id_fuente']) ? intval($_GET['id_fuente']) : 0;
$excluir_id = isset($_GET['excluir_id']) ? intval($_GET['excluir_id']) : 0;

header('Content-Type: application/json; charset=utf-8');

if (empty($numero_hc) || $id_fuente <= 0) {
    echo json_encode(['existe' => false]);
    exit();
}

if ($excluir_id > 0) {
    $stmt = $conexion->prepare("SELECT id_historia FROM historias_clinicas WHERE numero_hc = ? AND id_fuente = ? AND id_historia != ?");
    $stmt->bind_param("sii", $numero_hc, $id_fuente, $excluir_id);
} else {
    $stmt = $conexion->prepare("SELECT id_historia FROM historias_clinicas WHERE numero_hc = ? AND id_fuente = ?");
    $stmt->bind_param("si", $numero_hc, $id_fuente);
}
$stmt->execute();
$resultado = $stmt->get_result();

$existe = $resultado->num_rows > 0;
$stmt->close();

echo json_encode(['existe' => $existe]);
