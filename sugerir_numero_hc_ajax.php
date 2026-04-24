<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

$id_fuente = intval($_GET['id_fuente'] ?? 0);
$id_paciente = intval($_GET['id_paciente'] ?? 0);

if ($id_fuente <= 0) {
    echo json_encode(['sugerencia' => '', 'formato' => '']);
    exit();
}

// Obtener datos de la fuente
$stmt = $conexion->prepare("SELECT formato_numeracion FROM fuentes WHERE id_fuente = ? AND activo = 1");
$stmt->bind_param("i", $id_fuente);
$stmt->execute();
$res = $stmt->get_result();
$fuente = $res->fetch_assoc();
$stmt->close();

if (!$fuente) {
    echo json_encode(['sugerencia' => '', 'formato' => '']);
    exit();
}

$formato = $fuente['formato_numeracion'];
$sugerencia = '';

switch ($formato) {
    case 'dni':
        // Usar el DNI del paciente como número de HC
        if ($id_paciente > 0) {
            $stmt = $conexion->prepare("SELECT numero_documento FROM pacientes WHERE id_paciente = ?");
            $stmt->bind_param("i", $id_paciente);
            $stmt->execute();
            $res = $stmt->get_result();
            $pac = $res->fetch_assoc();
            $stmt->close();
            if ($pac) {
                $sugerencia = $pac['numero_documento'];
            }
        }
        break;

    case 'letra_autoincremental':
        // Primera letra del apellido del paciente + número autoincremental para esa letra en esa fuente
        if ($id_paciente > 0) {
            $stmt = $conexion->prepare("SELECT apellido FROM pacientes WHERE id_paciente = ?");
            $stmt->bind_param("i", $id_paciente);
            $stmt->execute();
            $res = $stmt->get_result();
            $pac = $res->fetch_assoc();
            $stmt->close();

            if ($pac) {
                // Obtener la primera letra del apellido (convertida a mayúscula sin acentos)
                $apellido = mb_strtoupper($pac['apellido'], 'UTF-8');
                $primera_letra = mb_substr($apellido, 0, 1, 'UTF-8');

                // Normalizar letras con acento a su equivalente sin acento
                $map = ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'];
                $primera_letra = strtr($primera_letra, $map);

                // Contar cuántos números ya existen para esa letra en esa fuente
                // Los números con formato letra_autoincremental tienen la forma: LETRA + digitos (ej: C880)
                // Buscamos todos los HC de esta fuente que comiencen con esa letra y tienen dígitos después
                $patron = $primera_letra . '%';
                $stmt = $conexion->prepare(
                    "SELECT numero_hc FROM historias_clinicas 
                     WHERE id_fuente = ? AND numero_hc LIKE ? 
                     ORDER BY CAST(SUBSTRING(numero_hc, 2) AS UNSIGNED) DESC 
                     LIMIT 1"
                );
                $stmt->bind_param("is", $id_fuente, $patron);
                $stmt->execute();
                $res = $stmt->get_result();
                $ultimo = $res->fetch_assoc();
                $stmt->close();

                if ($ultimo) {
                    // Extraer la parte numérica
                    $num_actual = intval(preg_replace('/^[A-Z]+/', '', $ultimo['numero_hc']));
                    $siguiente = $num_actual + 1;
                } else {
                    $siguiente = 1;
                }

                $sugerencia = $primera_letra . $siguiente;
            }
        }
        break;

    case 'autoincremental':
    default:
        // Buscar el mayor número entero de HC en esta fuente
        $stmt = $conexion->prepare(
            "SELECT numero_hc FROM historias_clinicas 
             WHERE id_fuente = ? AND numero_hc REGEXP '^[0-9]+$' 
             ORDER BY CAST(numero_hc AS UNSIGNED) DESC 
             LIMIT 1"
        );
        $stmt->bind_param("i", $id_fuente);
        $stmt->execute();
        $res = $stmt->get_result();
        $ultimo = $res->fetch_assoc();
        $stmt->close();

        if ($ultimo) {
            $sugerencia = strval(intval($ultimo['numero_hc']) + 1);
        } else {
            $sugerencia = '1';
        }
        break;
}

echo json_encode([
    'sugerencia' => $sugerencia,
    'formato' => $formato
]);
