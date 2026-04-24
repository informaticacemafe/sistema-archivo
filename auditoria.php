<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

if (!tienePermiso('administrador')) {
    header('Location: dashboard.php');
    exit();
}

// Filtros
$tipo_entidad = isset($_GET['tipo_entidad']) ? $_GET['tipo_entidad'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

// Construir query para log_actividades
$query = "
    SELECT l.*, u.username, u.nombre, u.apellido
    FROM log_actividades l
    INNER JOIN usuarios u ON l.usuario_id = u.id_usuario
    WHERE DATE(l.fecha_hora) BETWEEN ? AND ?
";

$params = array($fecha_desde, $fecha_hasta);
$types = "ss";

if (!empty($tipo_entidad)) {
    $query .= " AND l.tipo_entidad = ?";
    $params[] = $tipo_entidad;
    $types .= "s";
}

if ($usuario > 0) {
    $query .= " AND l.usuario_id = ?";
    $params[] = $usuario;
    $types .= "i";
}

if (!empty($accion)) {
    $query .= " AND l.accion = ?";
    $params[] = $accion;
    $types .= "s";
}

$query .= " ORDER BY l.fecha_hora DESC LIMIT 500";

$stmt = $conexion->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$registros = $stmt->get_result();

// Obtener usuarios para filtro
$usuarios = $conexion->query("SELECT id_usuario, username, nombre, apellido FROM usuarios ORDER BY username");

// Traducciones
$entidades_traducidas = array(
    'paciente' => 'Paciente',
    'hc' => 'Historia Clínica',
    'movimiento' => 'Movimiento',
    'fuente' => 'Fuente'
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Actividades - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .log-crear { background-color: #d4edda; }
        .log-editar { background-color: #fff3cd; }
        .log-eliminar { background-color: #f8d7da; }
        .detalle-json { display: none; margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px; font-family: monospace; font-size: 12px; white-space: pre-wrap; border: 1px solid #ddd; }
        .detalle-json.visible { display: block; }
        .detalle-toggle { cursor: pointer; color: #667eea; text-decoration: underline; font-size: 12px; }
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-header no-print">
                <h1>Log de Actividades</h1>
                <p class="breadcrumb">Inicio / Administración / Log de Actividades</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Filtros
                </div>
                <div class="card-body no-print">
                    <form method="GET" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Desde</label>
                                <input type="date" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                            </div>
                            <div class="form-group">
                                <label>Hasta</label>
                                <input type="date" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                            </div>
                            <div class="form-group">
                                <label>Entidad</label>
                                <select name="tipo_entidad">
                                    <option value="">Todas</option>
                                    <option value="paciente" <?php echo $tipo_entidad == 'paciente' ? 'selected' : ''; ?>>Paciente</option>
                                    <option value="hc" <?php echo $tipo_entidad == 'hc' ? 'selected' : ''; ?>>Historia Clínica</option>
                                    <option value="movimiento" <?php echo $tipo_entidad == 'movimiento' ? 'selected' : ''; ?>>Movimiento</option>
                                    <option value="fuente" <?php echo $tipo_entidad == 'fuente' ? 'selected' : ''; ?>>Fuente</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Usuario</label>
                                <select name="usuario">
                                    <option value="0">Todos</option>
                                    <?php while($usr = $usuarios->fetch_assoc()): ?>
                                    <option value="<?php echo $usr['id_usuario']; ?>" <?php echo $usuario == $usr['id_usuario'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($usr['username']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Acción</label>
                                <select name="accion">
                                    <option value="">Todas</option>
                                    <option value="CREAR" <?php echo $accion == 'CREAR' ? 'selected' : ''; ?>>CREAR</option>
                                    <option value="EDITAR" <?php echo $accion == 'EDITAR' ? 'selected' : ''; ?>>EDITAR</option>
                                    <option value="ELIMINAR" <?php echo $accion == 'ELIMINAR' ? 'selected' : ''; ?>>ELIMINAR</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <button type="button" class="btn btn-secondary" onclick="window.print()">Imprimir</button>
                            <button type="button" class="btn btn-secondary" onclick="exportarLog()">Exportar a Excel</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Registros de Actividad (Últimos 500)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla_log">
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario</th>
                                    <th>Entidad</th>
                                    <th>ID</th>
                                    <th>Acción</th>
                                    <th>Resumen</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($registros->num_rows > 0): ?>
                                    <?php while($reg = $registros->fetch_assoc()): ?>
                                    <tr class="log-<?php echo strtolower($reg['accion']); ?>">
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($reg['fecha_hora'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($reg['username']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($reg['nombre'] . ' ' . $reg['apellido']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo $entidades_traducidas[$reg['tipo_entidad']] ?? $reg['tipo_entidad']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $reg['id_entidad']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $reg['accion'] == 'CREAR' ? 'success' : 
                                                    ($reg['accion'] == 'EDITAR' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo $reg['accion']; ?>
                                            </span>
                                        </td>
                                        <td style="max-width: 400px;">
                                            <?php echo htmlspecialchars($reg['resumen']); ?>
                                        </td>
                                        <td class="no-print">
                                            <?php if ($reg['detalle_anterior'] || $reg['detalle_nuevo']): ?>
                                                <span class="detalle-toggle" onclick="toggleDetalle(<?php echo $reg['id_log']; ?>)">
                                                    Ver detalle
                                                </span>
                                                <div id="detalle_<?php echo $reg['id_log']; ?>" class="detalle-json">
                                                    <?php if ($reg['detalle_anterior']): ?>
                                                        <strong style="color: #dc3545;">VALOR ANTERIOR:</strong>
                                                        <?php 
                                                        $ant = json_decode($reg['detalle_anterior'], true);
                                                        if ($ant && is_array($ant)) {
                                                            echo "\n";
                                                            foreach ($ant as $campo => $valor) {
                                                                echo htmlspecialchars("{$campo}: " . ($valor ?? 'vacío')) . "\n";
                                                            }
                                                        } else {
                                                            echo htmlspecialchars($reg['detalle_anterior']);
                                                        }
                                                        ?>
                                                    <?php endif; ?>
                                                    <?php if ($reg['detalle_nuevo']): ?>
                                                        <?php if ($reg['detalle_anterior']): echo "\n\n"; endif; ?>
                                                        <strong style="color: #28a745;">VALOR NUEVO:</strong>
                                                        <?php 
                                                        $nuevo = json_decode($reg['detalle_nuevo'], true);
                                                        if ($nuevo && is_array($nuevo)) {
                                                            echo "\n";
                                                            foreach ($nuevo as $campo => $valor) {
                                                                echo htmlspecialchars("{$campo}: " . ($valor ?? 'vacío')) . "\n";
                                                            }
                                                        } else {
                                                            echo htmlspecialchars($reg['detalle_nuevo']);
                                                        }
                                                        ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <em style="color: #999;">-</em>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No se encontraron registros de actividad</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-20"><strong>Total de registros mostrados: <?php echo $registros->num_rows; ?></strong></p>
                    <p><small>Se muestran máximo los últimos 500 registros según los filtros aplicados.</small></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header" onclick="toggleAuditoriaTecnica()" style="cursor: pointer;">
                    📋 Auditoría Técnica (campo por campo) <span id="toggle_icon">[Mostrar]</span>
                </div>
                <div class="card-body" id="auditoria_tecnica" style="display: none;">
                    <p><small>Esta sección muestra el registro detallado campo por campo de la tabla <code>auditoria</code> para análisis técnicos.</small></p>
                    <?php
                    $tecnicos = $conexion->query("
                        SELECT a.*, u.username, u.nombre, u.apellido
                        FROM auditoria a
                        INNER JOIN usuarios u ON a.usuario_id = u.id_usuario
                        WHERE DATE(a.fecha_hora) BETWEEN '{$fecha_desde}' AND '{$fecha_hasta}'
                        ORDER BY a.fecha_hora DESC
                        LIMIT 200
                    ");
                    ?>
                    <?php if ($tecnicos && $tecnicos->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario</th>
                                    <th>Tabla</th>
                                    <th>ID</th>
                                    <th>Campo</th>
                                    <th>Valor Anterior</th>
                                    <th>Valor Nuevo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($t = $tecnicos->fetch_assoc()): ?>
                                <tr class="auditoria-<?php echo strtolower($t['accion']); ?>">
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($t['fecha_hora'])); ?></td>
                                    <td><?php echo htmlspecialchars($t['username']); ?></td>
                                    <td><?php echo htmlspecialchars($t['tabla']); ?></td>
                                    <td><?php echo $t['id_registro']; ?></td>
                                    <td><?php echo htmlspecialchars($t['campo']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($t['valor_anterior'] ?? '', 0, 100)) ?: '<em style="color:#999;">vacío</em>'; ?></td>
                                    <td><?php echo htmlspecialchars(substr($t['valor_nuevo'] ?? '', 0, 100)) ?: '<em style="color:#999;">vacío</em>'; ?></td>
                                    <td><span class="badge badge-<?php echo $t['accion'] == 'INSERT' ? 'success' : ($t['accion'] == 'UPDATE' ? 'warning' : 'danger'); ?>"><?php echo $t['accion']; ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center">No hay registros en la auditoría técnica para el período seleccionado.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleDetalle(id) {
            const div = document.getElementById('detalle_' + id);
            div.classList.toggle('visible');
        }

        function toggleAuditoriaTecnica() {
            const div = document.getElementById('auditoria_tecnica');
            const icon = document.getElementById('toggle_icon');
            if (div.style.display === 'none') {
                div.style.display = 'block';
                icon.textContent = '[Ocultar]';
            } else {
                div.style.display = 'none';
                icon.textContent = '[Mostrar]';
            }
        }

        function exportarLog() {
            const tabla = document.getElementById('tabla_log');
            let html = '<html><head><meta charset="utf-8"></head><body>';
            html += '<h2>Log de Actividades</h2>';
            html += '<p>Generado: <?php echo date("d/m/Y H:i"); ?></p>';
            html += '<p>Período: <?php echo date("d/m/Y", strtotime($fecha_desde)) . " - " . date("d/m/Y", strtotime($fecha_hasta)); ?></p>';
            html += tabla.outerHTML;
            html += '</body></html>';
            
            const blob = new Blob(['\ufeff', html], {
                type: 'application/vnd.ms-excel'
            });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'log_actividades_<?php echo date("Ymd_His"); ?>.xls';
            link.click();
        }
    </script>
</body>
</html>
