<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

// RESTRICCIÓN: Solo administradores
if (!tienePermiso('administrador')) {
    header('Location: dashboard.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$id_hc = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_hc == 0) {
    header('Location: historias_clinicas.php');
    exit();
}

// Obtener datos de la HC
$stmt = $conexion->prepare("
    SELECT h.*, 
           CONCAT(p.apellido, ', ', p.nombre) as paciente,
           p.numero_documento,
           f.nombre as fuente
    FROM historias_clinicas h
    INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
    INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
    WHERE h.id_historia = ?
");
$stmt->bind_param("i", $id_hc);
$stmt->execute();
$hc = $stmt->get_result()->fetch_assoc();

if (!$hc) {
    header('Location: historias_clinicas.php');
    exit();
}

// Contar movimientos asociados
$stmt_count = $conexion->prepare("SELECT COUNT(*) as total FROM movimientos WHERE id_historia = ?");
$stmt_count->bind_param("i", $id_hc);
$stmt_count->execute();
$count_result = $stmt_count->get_result()->fetch_assoc();
$total_movimientos = $count_result['total'];
$stmt_count->close();

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $confirmacion = trim($_POST['confirmacion']);

    if ($confirmacion !== 'ELIMINAR') {
        $mensaje = 'Debe escribir "ELIMINAR" para confirmar la operación';
        $tipo_mensaje = 'error';
    } else {
        // Iniciar transacción
        $conexion->begin_transaction();

        try {
            $resumen = "Se eliminó HC {$hc['numero_hc']} ({$hc['fuente']}) - Paciente: {$hc['paciente']} - con {$total_movimientos} movimiento" . ($total_movimientos != 1 ? 's' : '');
            $detalle_hc = [
                'id_historia' => $hc['id_historia'],
                'numero_hc' => $hc['numero_hc'],
                'paciente' => $hc['paciente'],
                'documento' => $hc['numero_documento'],
                'fuente' => $hc['fuente'],
                'estado' => $hc['estado'],
                'ubicacion_actual' => $hc['ubicacion_actual'],
                'total_movimientos' => $total_movimientos
            ];
            registrarLog('hc', $id_hc, 'ELIMINAR', $resumen, $detalle_hc, null);

            // Eliminar movimientos asociados primero
            $stmt_delete_mov = $conexion->prepare("DELETE FROM movimientos WHERE id_historia = ?");
            $stmt_delete_mov->bind_param("i", $id_hc);
            $stmt_delete_mov->execute();
            $movimientos_eliminados = $stmt_delete_mov->affected_rows;
            $stmt_delete_mov->close();

            // Eliminar historia clínica
            $stmt_delete_hc = $conexion->prepare("DELETE FROM historias_clinicas WHERE id_historia = ?");
            $stmt_delete_hc->bind_param("i", $id_hc);
            $stmt_delete_hc->execute();
            $stmt_delete_hc->close();

            $conexion->commit();

            // Redirigir con mensaje de éxito
            header('Location: historias_clinicas.php?mensaje=hc_eliminada&movimientos=' . $movimientos_eliminados);
            exit();

        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = 'Error al eliminar la historia clínica: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar HC - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header">
                <h1>Eliminar Historia Clínica</h1>
                <p class="breadcrumb">
                    <a href="historias_clinicas.php">Historias Clínicas</a> /
                    <a href="hc_detalle.php?id=<?php echo $id_hc; ?>">Detalle HC</a> /
                    Eliminar HC
                </p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header" style="background-color: #dc3545; color: white;">
                    🚨 CONFIRMACIÓN DE ELIMINACIÓN PERMANENTE
                </div>
                <div class="card-body">
                    <div
                        style="background-color: #f8d7da; padding: 20px; border-radius: 5px; border-left: 5px solid #dc3545; margin-bottom: 25px;">
                        <h3 style="color: #721c24; margin-top: 0;">⛔ ADVERTENCIA CRÍTICA</h3>
                        <p style="font-size: 16px; margin-bottom: 10px;">
                            <strong>Esta operación eliminará PERMANENTEMENTE:</strong>
                        </p>
                        <ul style="font-size: 15px; color: #721c24;">
                            <li>La historia clínica completa</li>
                            <li>Todos los movimientos asociados (
                                <?php echo $total_movimientos; ?> movimiento
                                <?php echo $total_movimientos != 1 ? 's' : ''; ?>)
                            </li>
                            <li>Todo el historial de la HC</li>
                        </ul>
                        <p style="font-size: 16px; font-weight: bold; color: #721c24; margin-bottom: 0;">
                            ⚠️ ESTA ACCIÓN NO SE PUEDE DESHACER
                        </p>
                    </div>

                    <h3>Datos de la Historia Clínica a Eliminar</h3>
                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <p><strong>Número HC:</strong> <span style="font-size: 18px; color: #dc3545;">
                                        <?php echo htmlspecialchars($hc['numero_hc']); ?>
                                    </span></p>
                                <p><strong>Paciente:</strong>
                                    <?php echo htmlspecialchars($hc['paciente']); ?>
                                </p>
                                <p><strong>Documento:</strong>
                                    <?php echo htmlspecialchars($hc['numero_documento']); ?>
                                </p>
                            </div>
                            <div>
                                <p><strong>Fuente:</strong>
                                    <?php echo htmlspecialchars($hc['fuente']); ?>
                                </p>
                                <p><strong>Estado:</strong>
                                    <?php echo htmlspecialchars($hc['estado']); ?>
                                </p>
                                <p><strong>Ubicación:</strong>
                                    <?php echo htmlspecialchars($hc['ubicacion_actual']); ?>
                                </p>
                            </div>
                        </div>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #dee2e6;">
                            <p style="font-size: 16px;">
                                <strong>Total de movimientos que se eliminarán:</strong>
                                <span style="color: #dc3545; font-size: 20px; font-weight: bold;">
                                    <?php echo $total_movimientos; ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="" onsubmit="return validarFormulario()">
                        <input type="hidden" name="accion" value="eliminar">

                        <div class="form-group">
                            <label style="color: #dc3545; font-weight: bold; font-size: 16px;">
                                Para confirmar la eliminación PERMANENTE, escriba "ELIMINAR" (en mayúsculas) *
                            </label>
                            <input type="text" name="confirmacion" id="confirmacion" required
                                placeholder="Escriba ELIMINAR para confirmar"
                                style="border: 3px solid #dc3545; font-size: 16px; padding: 12px;">
                            <small style="color: #dc3545; font-weight: bold;">
                                Esta acción eliminará la HC y sus
                                <?php echo $total_movimientos; ?> movimiento
                                <?php echo $total_movimientos != 1 ? 's' : ''; ?> asociado
                                <?php echo $total_movimientos != 1 ? 's' : ''; ?>
                            </small>
                        </div>

                        <div class="form-group text-right">
                            <a href="hc_detalle.php?id=<?php echo $id_hc; ?>" class="btn btn-secondary"
                                style="font-size: 16px; padding: 10px 20px;">
                                ← Cancelar
                            </a>
                            <button type="submit" class="btn btn-danger" id="btnEliminar" disabled
                                style="font-size: 16px; padding: 10px 20px;">
                                🗑️ ELIMINAR PERMANENTEMENTE
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const inputConfirmacion = document.getElementById('confirmacion');
        const btnEliminar = document.getElementById('btnEliminar');

        inputConfirmacion.addEventListener('input', function () {
            if (this.value === 'ELIMINAR') {
                btnEliminar.disabled = false;
                btnEliminar.style.opacity = '1';
            } else {
                btnEliminar.disabled = true;
                btnEliminar.style.opacity = '0.5';
            }
        });

        function validarFormulario() {
            const confirmacion = document.getElementById('confirmacion').value;

            if (confirmacion !== 'ELIMINAR') {
                alert('Debe escribir exactamente "ELIMINAR" (en mayúsculas) para confirmar.');
                return false;
            }

            const totalMovimientos = <?php echo $total_movimientos; ?>;
            const mensaje = '⚠️ ÚLTIMA ADVERTENCIA ⚠️\n\n' +
                'Está a punto de ELIMINAR PERMANENTEMENTE:\n\n' +
                '• Historia Clínica: <?php echo addslashes($hc['numero_hc']); ?>\n' +
                    '• Paciente: <?php echo addslashes($hc['paciente']); ?>\n' +
                        '• Total de movimientos: ' + totalMovimientos + '\n\n' +
                        'ESTA ACCIÓN NO SE PUEDE DESHACER.\n\n' +
                        '¿Está COMPLETAMENTE SEGURO?';

            return confirm(mensaje);
        }
    </script>
</body>

</html>