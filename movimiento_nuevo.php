<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

if (!tienePermiso(array('administrador', 'archivo', 'servicio'))) {
    header('Location: dashboard.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$id_hc = isset($_GET['hc']) ? intval($_GET['hc']) : 0;

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

// Procesar movimiento
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_movimiento = $_POST['tipo_movimiento'];
    $ubicacion_destino = trim($_POST['ubicacion_destino']);
    $observaciones = trim($_POST['observaciones']);
    $usuario_id = $_SESSION['usuario_id'];

    // Determinar nuevo estado según tipo de movimiento
    $nuevo_estado = $hc['estado'];
    switch ($tipo_movimiento) {
        case 'ingreso_archivo':
        case 'devolucion_a_archivo':
            $nuevo_estado = 'en_archivo';
            break;
        case 'salida_a_servicio':
        case 'traslado_interno':
            $nuevo_estado = 'en_servicio';
            break;
        case 'salida_extramuro':
            $nuevo_estado = 'extramuro';
            break;
        case 'ingreso_desde_extramuro':
            $nuevo_estado = 'en_archivo';
            break;
        case 'dado_de_baja':
            $nuevo_estado = 'dada_de_baja';
            break;
        case 'reportado_extraviado':
            $nuevo_estado = 'extraviada';
            break;
        case 'recuperado':
            $nuevo_estado = 'en_archivo';
            break;
    }

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Insertar movimiento
        $stmt = $conexion->prepare("INSERT INTO movimientos (id_historia, tipo_movimiento, ubicacion_origen, ubicacion_destino, usuario_id, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssds", $id_hc, $tipo_movimiento, $hc['ubicacion_actual'], $ubicacion_destino, $usuario_id, $observaciones);
        $stmt->execute();

        // Actualizar HC
        $stmt = $conexion->prepare("UPDATE historias_clinicas SET estado = ?, ubicacion_actual = ?, fecha_ultimo_movimiento = NOW() WHERE id_historia = ?");
        $stmt->bind_param("ssi", $nuevo_estado, $ubicacion_destino, $id_hc);
        $stmt->execute();

        // Obtener ID del movimiento insertado
        $id_movimiento = $conexion->insert_id;

        $conexion->commit();

        // Registrar en log
        $tipos_texto = array(
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
        $tipo_texto = $tipos_texto[$tipo_movimiento] ?? $tipo_movimiento;
        $resumen = "Se registró movimiento en HC {$hc['numero_hc']} ({$hc['paciente']}): {$tipo_texto} - {$hc['ubicacion_actual']} → {$ubicacion_destino}";
        $detalle_anterior = [
            'estado' => $hc['estado'],
            'ubicacion_actual' => $hc['ubicacion_actual']
        ];
        $detalle_nuevo = [
            'tipo_movimiento' => $tipo_movimiento,
            'estado' => $nuevo_estado,
            'ubicacion_destino' => $ubicacion_destino,
            'observaciones' => $observaciones
        ];
        registrarLog('movimiento', $id_movimiento, 'CREAR', $resumen, $detalle_anterior, $detalle_nuevo);

        // Redirigir a detalle de HC
        header('Location: hc_detalle.php?id=' . $id_hc . '&mensaje=movimiento_registrado');
        exit();

    } catch (Exception $e) {
        $conexion->rollback();
        $mensaje = 'Error al registrar movimiento: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Obtener historial de movimientos (ACTUALIZADO)
$movimientos = $conexion->query("
    SELECT m.*, u.username
    FROM movimientos m
    INNER JOIN usuarios u ON m.usuario_id = u.id_usuario
    WHERE m.id_historia = {$id_hc}
    ORDER BY m.fecha_hora DESC
");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Movimiento - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header">
                <h1>Registrar Movimiento</h1>
                <p class="breadcrumb">
                    <a href="historias_clinicas.php">Historias Clínicas</a> / Movimiento
                </p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    Información de la Historia Clínica
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div>
                            <strong>HC:</strong> <?php echo htmlspecialchars($hc['numero_hc']); ?>
                        </div>
                        <div>
                            <strong>Fuente:</strong> <?php echo htmlspecialchars($hc['fuente']); ?>
                        </div>
                        <div>
                            <strong>Paciente:</strong> <?php echo htmlspecialchars($hc['paciente']); ?>
                        </div>
                        <div>
                            <strong>Documento:</strong> <?php echo htmlspecialchars($hc['numero_documento']); ?>
                        </div>
                        <div>
                            <strong>Estado Actual:</strong>
                            <?php
                            $estados = array(
                                'en_archivo' => 'En Archivo',
                                'en_servicio' => 'En Servicio',
                                'extramuro' => 'Extramuro',
                                'dada_de_baja' => 'Dada de Baja',
                                'extraviada' => 'Extraviada'
                            );
                            echo $estados[$hc['estado']];
                            ?>
                        </div>
                        <div>
                            <strong>Ubicación Actual:</strong> <?php echo htmlspecialchars($hc['ubicacion_actual']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Nuevo Movimiento
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Tipo de Movimiento *</label>
                            <select name="tipo_movimiento" id="tipo_movimiento" required onchange="actualizarDestino()">
                                <option value="">Seleccione...</option>
                                <option value="salida_a_servicio">Salida a Servicio</option>
                                <option value="devolucion_a_archivo">Devolución a Archivo</option>
                                <option value="salida_extramuro">Salida Extramuro</option>
                                <option value="ingreso_desde_extramuro">Ingreso desde Extramuro</option>
                                <option value="traslado_interno">Traslado Interno</option>
                                <option value="dado_de_baja">Dar de Baja</option>
                                <option value="reportado_extraviado">Reportar Extraviada</option>
                                <option value="recuperado">Recuperada</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Ubicación Destino *</label>
                            <select name="ubicacion_destino" id="ubicacion_destino_select"
                                onchange="manejarCambioUbicacion()" required>
                                <option value="">Seleccione ubicación...</option>
                                <option value="Archivo Central">Archivo Central</option>
                                <?php
                                // Listar archivos de servicios activos
                                $fuentes_archivo = $conexion->query("SELECT nombre FROM fuentes WHERE activo = 1 ORDER BY nombre");
                                while ($fue = $fuentes_archivo->fetch_assoc()):
                                    ?>
                                    <option value="Archivo <?php echo htmlspecialchars($fue['nombre']); ?>">
                                        Archivo <?php echo htmlspecialchars($fue['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__otra__">📝 Otra ubicación (escribir manualmente)</option>
                            </select>

                            <input type="text" name="ubicacion_destino_manual" id="ubicacion_destino_manual"
                                style="display: none; margin-top: 10px;" placeholder="Ingrese ubicación manualmente">

                            <small>Origen actual: <?php echo htmlspecialchars($hc['ubicacion_actual']); ?></small>
                        </div>

                        <div class="form-group">
                            <label>Observaciones</label>
                            <textarea name="observaciones" rows="3"></textarea>
                        </div>

                        <div class="form-group text-right">
                            <a href="historias_clinicas.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Registrar Movimiento</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Historial de Movimientos
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Tipo</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Usuario</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($movimientos->num_rows > 0): ?>
                                    <?php while ($mov = $movimientos->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_hora'])); ?></td>
                                            <td>
                                                <?php
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
                                                echo $tipos[$mov['tipo_movimiento']];
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($mov['ubicacion_origen']); ?></td>
                                            <td><?php echo htmlspecialchars($mov['ubicacion_destino']); ?></td>
                                            <td><?php echo htmlspecialchars($mov['username']); ?></td>
                                            <td><?php echo htmlspecialchars($mov['observaciones']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No hay movimientos registrados</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function manejarCambioUbicacion() {
            const select = document.getElementById('ubicacion_destino_select');
            const inputManual = document.getElementById('ubicacion_destino_manual');
            
            if (select.value === '__otra__') {
                inputManual.style.display = 'block';
                inputManual.required = true;
                select.name = '';  // Desactivar select para que no se envíe
                inputManual.name = 'ubicacion_destino';  // Activar input manual
                inputManual.focus();
            } else {
                inputManual.style.display = 'none';
                inputManual.required = false;
                select.name = 'ubicacion_destino';  // Activar select
                inputManual.name = '';  // Desactivar input manual
            }
        }

        function actualizarDestino() {
            const tipo = document.getElementById('tipo_movimiento').value;
            const select = document.getElementById('ubicacion_destino_select');
            const inputManual = document.getElementById('ubicacion_destino_manual');
            
            // Resetear a modo select por defecto
            inputManual.style.display = 'none';
            inputManual.required = false;
            select.name = 'ubicacion_destino';
            inputManual.name = '';
            
            switch (tipo) {
                case 'devolucion_a_archivo':
                case 'ingreso_desde_extramuro':
                case 'recuperado':
                    select.value = 'Archivo Central';
                    break;
                case 'dado_de_baja':
                    select.value = '__otra__';
                    manejarCambioUbicacion(); // Activa modo manual
                    inputManual.value = 'Baja Definitiva';
                    break;
                case 'reportado_extraviado':
                    select.value = '__otra__';
                    manejarCambioUbicacion(); // Activa modo manual
                    inputManual.value = 'Extraviada';
                    break;
                default:
                    select.value = '';
                    inputManual.value = '';
            }
        }
    </script>
</body>

</html>