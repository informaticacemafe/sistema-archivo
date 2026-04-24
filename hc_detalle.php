<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

$mensaje = '';
$tipo_mensaje = '';
$id_hc = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Manejar mensajes de redirección
if (isset($_GET['mensaje'])) {
    switch ($_GET['mensaje']) {
        case 'movimiento_eliminado':
            $mensaje = 'Movimiento eliminado exitosamente';
            $tipo_mensaje = 'success';
            break;
        case 'numero_actualizado':
            $mensaje = 'Número de HC actualizado exitosamente';
            $tipo_mensaje = 'success';
            break;
    }
}

if ($id_hc == 0) {
    header('Location: historias_clinicas.php');
    exit();
}

// Procesar actualización de observaciones
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar_observaciones') {
    $nuevas_observaciones = trim($_POST['observaciones']);

    // Obtener observaciones anteriores
    $stmt_ant = $conexion->prepare("SELECT observaciones FROM historias_clinicas WHERE id_historia = ?");
    $stmt_ant->bind_param("i", $id_hc);
    $stmt_ant->execute();
    $obs_anterior = $stmt_ant->get_result()->fetch_assoc()['observaciones'];
    $stmt_ant->close();

    // Actualizar observaciones
    $stmt = $conexion->prepare("UPDATE historias_clinicas SET observaciones = ? WHERE id_historia = ?");
    $stmt->bind_param("si", $nuevas_observaciones, $id_hc);

    if ($stmt->execute()) {
        registrarAuditoria('historias_clinicas', $id_hc, 'observaciones', $obs_anterior, $nuevas_observaciones);
        $mensaje = 'Observaciones actualizadas exitosamente';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al actualizar observaciones';
        $tipo_mensaje = 'error';
    }
    $stmt->close();
}

// Obtener datos de la HC
$stmt = $conexion->prepare("
    SELECT h.*, 
           p.tipo_documento, p.numero_documento,
           CONCAT(p.apellido, ', ', p.nombre) as paciente,
           p.fecha_nacimiento, p.sexo, p.telefono,
           f.nombre as fuente, f.codigo as codigo_fuente
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

// Calcular edad
$edad = date_diff(date_create($hc['fecha_nacimiento']), date_create('now'))->y;

// Obtener movimientos
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
    <title>Detalle HC - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            .sidebar {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .print-only {
                display: block !important;
            }
        }

        .print-only {
            display: none;
        }

        @media screen {
            .print-only.auditor-view {
                display: block !important;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header no-print">
                <h1>Historia Clínica: <?php echo htmlspecialchars($hc['numero_hc']); ?></h1>
                <p class="breadcrumb">
                    <a href="historias_clinicas.php">Historias Clínicas</a> / Detalle
                </p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> no-print">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header flex-between">
                    <span>Información de la Historia Clínica</span>
                    <div>
                        <?php if (!tienePermiso('auditor')): ?>
                            <a href="movimiento_nuevo.php?hc=<?php echo $id_hc; ?>" class="btn btn-warning btn-sm">Registrar
                                Movimiento</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('administrador')): ?>
                            <a href="hc_editar_numero.php?id=<?php echo $id_hc; ?>" class="btn btn-warning btn-sm">✏️ Editar
                                Número HC</a>
                            <a href="hc_eliminar.php?id=<?php echo $id_hc; ?>" class="btn btn-danger btn-sm">🗑️ Eliminar
                                HC</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div>
                            <h3>Datos de la HC</h3>
                            <p><strong>Número HC:</strong> <?php echo htmlspecialchars($hc['numero_hc']); ?></p>
                            <p><strong>Fuente:</strong>
                                <?php echo htmlspecialchars($hc['fuente']) . ' (' . htmlspecialchars($hc['codigo_fuente']) . ')'; ?>
                            </p>
                            <p><strong>Estado:</strong>
                                <?php
                                $badges = array(
                                    'en_archivo' => 'success',
                                    'en_servicio' => 'info',
                                    'extramuro' => 'warning',
                                    'dada_de_baja' => 'secondary',
                                    'extraviada' => 'danger'
                                );
                                $estados_texto = array(
                                    'en_archivo' => 'En Archivo',
                                    'en_servicio' => 'En Servicio',
                                    'extramuro' => 'Extramuro',
                                    'dada_de_baja' => 'Dada de Baja',
                                    'extraviada' => 'Extraviada'
                                );
                                echo '<span class="badge badge-' . $badges[$hc['estado']] . '">' . $estados_texto[$hc['estado']] . '</span>';
                                ?>
                            </p>
                            <p><strong>Ubicación Actual:</strong>
                                <?php echo htmlspecialchars($hc['ubicacion_actual']); ?></p>
                            <p><strong>Último Movimiento:</strong>
                                <?php echo date('d/m/Y H:i', strtotime($hc['fecha_ultimo_movimiento'])); ?></p>
                            <p><strong>Fecha Creación:</strong>
                                <?php echo date('d/m/Y', strtotime($hc['fecha_creacion'])); ?></p>
                        </div>

                        <div>
                            <h3>Datos del Paciente</h3>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($hc['paciente']); ?></p>
                            <p><strong>Documento:</strong>
                                <?php echo htmlspecialchars($hc['tipo_documento'] . ' ' . $hc['numero_documento']); ?>
                            </p>
                            <p><strong>Fecha Nacimiento:</strong>
                                <?php echo date('d/m/Y', strtotime($hc['fecha_nacimiento'])) . ' (' . $edad . ' años)'; ?>
                            </p>
                            <p><strong>Sexo:</strong>
                                <?php echo $hc['sexo'] == 'M' ? 'Masculino' : ($hc['sexo'] == 'F' ? 'Femenino' : 'Otro'); ?>
                            </p>
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($hc['telefono'] ?? 'N/A'); ?></p>
                            <p><a href="pacientes.php?buscar=<?php echo $hc['numero_documento']; ?>"
                                    class="btn btn-sm btn-secondary">Ver Paciente</a></p>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <h3 style="margin-bottom: 10px;">Observaciones</h3>
                        <?php if (!tienePermiso('auditor')): ?>
                            <!-- Formulario editable -->
                            <form method="POST" action="" id="formObservaciones" class="no-print">
                                <input type="hidden" name="accion" value="actualizar_observaciones">
                                <div class="form-group">
                                    <textarea name="observaciones" rows="4"
                                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;"><?php echo htmlspecialchars($hc['observaciones']); ?></textarea>
                                </div>
                                <div class="form-group text-right">
                                    <button type="submit" class="btn btn-success btn-sm">💾 Guardar Observaciones</button>
                                </div>
                            </form>
                            <!-- Vista para impresión -->
                            <div style="padding: 15px; background-color: #f8f9fa; border-radius: 5px; min-height: 60px;"
                                class="print-only">
                                <?php echo !empty($hc['observaciones']) ? nl2br(htmlspecialchars($hc['observaciones'])) : '<em style="color: #999;">Sin observaciones</em>'; ?>
                            </div>
                        <?php else: ?>
                            <!-- Solo lectura para auditores -->
                            <div style="padding: 15px; background-color: #f8f9fa; border-radius: 5px; min-height: 60px;">
                                <?php echo !empty($hc['observaciones']) ? nl2br(htmlspecialchars($hc['observaciones'])) : '<em style="color: #999;">Sin observaciones</em>'; ?>
                            </div>
                        <?php endif; ?>
                    </div>
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
                                    <th>Tipo Movimiento</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Usuario</th>
                                    <th>Observaciones</th>
                                    <?php if (tienePermiso('administrador')): ?>
                                        <th class="no-print">Acciones</th>
                                    <?php endif; ?>
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
                                            <?php if (tienePermiso('administrador')): ?>
                                                <td class="no-print">
                                                    <?php 
                                                    // No permitir eliminar el movimiento inicial de creación de HC
                                                    $es_movimiento_inicial = ($mov['tipo_movimiento'] == 'ingreso_archivo' && 
                                                                             $mov['observaciones'] == 'Creación de HC');
                                                    
                                                    if ($es_movimiento_inicial): 
                                                    ?>
                                                        <span style="color: #999; font-size: 12px;" title="El movimiento inicial no puede eliminarse">
                                                            🔒 Protegido
                                                        </span>
                                                    <?php else: ?>
                                                        <a href="movimiento_eliminar.php?id=<?php echo $mov['id_movimiento']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('¿Está seguro de eliminar este movimiento?')">
                                                            🗑️ Eliminar
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
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

            <div class="text-center mt-20">
                <a href="historias_clinicas.php" class="btn btn-secondary">Volver al Listado</a>
                <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
            </div>
        </div>
    </div>
</body>

</html>