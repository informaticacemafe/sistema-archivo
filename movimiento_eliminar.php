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
$id_movimiento = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_movimiento == 0) {
    header('Location: historias_clinicas.php');
    exit();
}

// Obtener datos del movimiento
$stmt = $conexion->prepare("
    SELECT m.*, 
           h.id_historia, h.numero_hc,
           CONCAT(p.apellido, ', ', p.nombre) as paciente,
           f.nombre as fuente,
           u.username
    FROM movimientos m
    INNER JOIN historias_clinicas h ON m.id_historia = h.id_historia
    INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
    INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
    INNER JOIN usuarios u ON m.usuario_id = u.id_usuario
    WHERE m.id_movimiento = ?
");
$stmt->bind_param("i", $id_movimiento);
$stmt->execute();
$movimiento = $stmt->get_result()->fetch_assoc();

if (!$movimiento) {
    header('Location: historias_clinicas.php');
    exit();
}

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
            // Registrar en auditoría antes de eliminar
            $datos_movimiento = json_encode([
                'id_movimiento' => $movimiento['id_movimiento'],
                'tipo_movimiento' => $movimiento['tipo_movimiento'],
                'ubicacion_origen' => $movimiento['ubicacion_origen'],
                'ubicacion_destino' => $movimiento['ubicacion_destino'],
                'fecha_hora' => $movimiento['fecha_hora'],
                'observaciones' => $movimiento['observaciones'],
                'usuario' => $movimiento['username']
            ]);

            registrarAuditoria('movimientos', $id_movimiento, 'REGISTRO_COMPLETO', $datos_movimiento, '', 'DELETE');

            // Eliminar movimiento
            $stmt_delete = $conexion->prepare("DELETE FROM movimientos WHERE id_movimiento = ?");
            $stmt_delete->bind_param("i", $id_movimiento);
            $stmt_delete->execute();
            $stmt_delete->close();

            // Actualizar fecha de último movimiento de la HC
            $stmt_update = $conexion->prepare("
                UPDATE historias_clinicas 
                SET fecha_ultimo_movimiento = (
                    SELECT MAX(fecha_hora) 
                    FROM movimientos 
                    WHERE id_historia = ?
                )
                WHERE id_historia = ?
            ");
            $stmt_update->bind_param("ii", $movimiento['id_historia'], $movimiento['id_historia']);
            $stmt_update->execute();
            $stmt_update->close();

            $conexion->commit();

            // Redirigir con mensaje de éxito
            header('Location: hc_detalle.php?id=' . $movimiento['id_historia'] . '&mensaje=movimiento_eliminado');
            exit();

        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = 'Error al eliminar el movimiento: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// Tipos de movimiento para mostrar
$tipos = array(
    'ingreso_archivo' => 'Ingreso a Archivo',
    'salida_a_servicio' => 'Salida a Servicio',
    'devolucion_a_archivo' => 'Devolución',
    'salida_extramuro' => 'Salida Extramuro',
    'ingreso_desde_extramuro' => 'Ingreso Extramuro',
    'traslado_interno' => 'Traslado',
    'dado_de_baja' => 'Dada de Baja',
    'reportado_extraviado' => 'Extraviada',
    'recuperado' => 'Recuperada'
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Movimiento - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header">
                <h1>Eliminar Movimiento</h1>
                <p class="breadcrumb">
                    <a href="historias_clinicas.php">Historias Clínicas</a> /
                    <a href="hc_detalle.php?id=<?php echo $movimiento['id_historia']; ?>">Detalle HC</a> /
                    Eliminar Movimiento
                </p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header" style="background-color: #dc3545; color: white;">
                    ⚠️ Confirmación de Eliminación
                </div>
                <div class="card-body">
                    <div
                        style="background-color: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin-bottom: 20px;">
                        <strong>🚨 ADVERTENCIA:</strong> Esta operación eliminará permanentemente el movimiento
                        seleccionado.
                        Esta acción <strong>NO SE PUEDE DESHACER</strong>.
                    </div>

                    <h3>Información de la Historia Clínica</h3>
                    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <p><strong>HC:</strong>
                            <?php echo htmlspecialchars($movimiento['numero_hc']); ?>
                        </p>
                        <p><strong>Paciente:</strong>
                            <?php echo htmlspecialchars($movimiento['paciente']); ?>
                        </p>
                        <p><strong>Fuente:</strong>
                            <?php echo htmlspecialchars($movimiento['fuente']); ?>
                        </p>
                    </div>

                    <h3>Datos del Movimiento a Eliminar</h3>
                    <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <p><strong>Fecha/Hora:</strong>
                            <?php echo date('d/m/Y H:i', strtotime($movimiento['fecha_hora'])); ?>
                        </p>
                        <p><strong>Tipo:</strong>
                            <?php echo $tipos[$movimiento['tipo_movimiento']]; ?>
                        </p>
                        <p><strong>Origen:</strong>
                            <?php echo htmlspecialchars($movimiento['ubicacion_origen']); ?>
                        </p>
                        <p><strong>Destino:</strong>
                            <?php echo htmlspecialchars($movimiento['ubicacion_destino']); ?>
                        </p>
                        <p><strong>Usuario:</strong>
                            <?php echo htmlspecialchars($movimiento['username']); ?>
                        </p>
                        <?php if (!empty($movimiento['observaciones'])): ?>
                            <p><strong>Observaciones:</strong>
                                <?php echo htmlspecialchars($movimiento['observaciones']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="" onsubmit="return validarFormulario()">
                        <input type="hidden" name="accion" value="eliminar">

                        <div class="form-group">
                            <label style="color: #dc3545; font-weight: bold;">
                                Para confirmar la eliminación, escriba "ELIMINAR" (en mayúsculas) *
                            </label>
                            <input type="text" name="confirmacion" id="confirmacion" required
                                placeholder="Escriba ELIMINAR para confirmar" style="border: 2px solid #dc3545;">
                        </div>

                        <div class="form-group text-right">
                            <a href="hc_detalle.php?id=<?php echo $movimiento['id_historia']; ?>"
                                class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-danger" id="btnEliminar" disabled>
                                🗑️ Eliminar Movimiento
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

            return confirm('¿Está completamente seguro de eliminar este movimiento?\n\nEsta acción NO se puede deshacer.');
        }
    </script>
</body>

</html>