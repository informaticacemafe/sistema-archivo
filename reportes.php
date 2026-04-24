<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

$tipo_reporte = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$id_servicio = isset($_GET['servicio']) ? intval($_GET['servicio']) : 0;

$resultado = null;
$titulo_reporte = '';

if (!empty($tipo_reporte)) {
    switch ($tipo_reporte) {
        case 'fuera_archivo':
            $titulo_reporte = 'Historias Clínicas Fuera del Archivo';
            $query = "
                SELECT h.numero_hc, 
                       CONCAT(p.apellido, ', ', p.nombre) as paciente,
                       f.nombre as fuente,
                       h.estado,
                       h.ubicacion_actual,
                       h.fecha_ultimo_movimiento
                FROM historias_clinicas h
                INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
                INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
                WHERE h.estado != 'en_archivo' AND h.estado != 'dada_de_baja'
            ";
            if ($id_servicio > 0) {
                $query .= " AND h.id_fuente = {$id_servicio}";
            }
            $query .= " ORDER BY h.fecha_ultimo_movimiento DESC";
            $resultado = $conexion->query($query);
            break;

        case 'extramuro':
            $titulo_reporte = 'Historias Clínicas Extramuro';
            $query = "
                SELECT h.numero_hc, 
                       CONCAT(p.apellido, ', ', p.nombre) as paciente,
                       f.nombre as fuente,
                       h.ubicacion_actual,
                       h.fecha_ultimo_movimiento,
                       DATEDIFF(NOW(), h.fecha_ultimo_movimiento) as dias_fuera
                FROM historias_clinicas h
                INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
                INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
                WHERE h.estado = 'extramuro'
            ";
            if ($id_servicio > 0) {
                $query .= " AND h.id_fuente = {$id_servicio}";
            }
            $query .= " ORDER BY h.fecha_ultimo_movimiento";
            $resultado = $conexion->query($query);
            break;

        case 'extraviadas':
            $titulo_reporte = 'Historias Clínicas Extraviadas';
            $query = "
                SELECT h.numero_hc, 
                       CONCAT(p.apellido, ', ', p.nombre) as paciente,
                       f.nombre as fuente,
                       h.ubicacion_actual,
                       h.fecha_ultimo_movimiento
                FROM historias_clinicas h
                INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
                INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
                WHERE h.estado = 'extraviada'
            ";
            if ($id_servicio > 0) {
                $query .= " AND h.id_fuente = {$id_servicio}";
            }
            $query .= " ORDER BY h.fecha_ultimo_movimiento DESC";
            $resultado = $conexion->query($query);
            break;

        case 'movimientos_periodo':
            $titulo_reporte = 'Movimientos por Período';
            $query = "
                SELECT m.fecha_hora,
                       h.numero_hc,
                       CONCAT(p.apellido, ', ', p.nombre) as paciente,
                       f.nombre as fuente,
                       m.tipo_movimiento,
                       m.ubicacion_origen,
                       m.ubicacion_destino,
                       u.username
                FROM movimientos m
                INNER JOIN historias_clinicas h ON m.id_historia = h.id_historia
                INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
                INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
                INNER JOIN usuarios u ON m.usuario_id = u.id_usuario
                WHERE DATE(m.fecha_hora) BETWEEN '{$fecha_desde}' AND '{$fecha_hasta}'
            ";
            if ($id_servicio > 0) {
                $query .= " AND h.id_fuente = {$id_servicio}";
            }
            $query .= " ORDER BY m.fecha_hora DESC";
            $resultado = $conexion->query($query);
            break;

        case 'movimientos_usuario':
            $titulo_reporte = 'Movimientos por Usuario';
            $query = "
                SELECT u.username,
                       u.nombre,
                       u.apellido,
                       u.rol,
                       COUNT(*) as total_movimientos,
                       MAX(m.fecha_hora) as ultimo_movimiento
                FROM movimientos m
                INNER JOIN usuarios u ON m.usuario_id = u.id_usuario
                WHERE DATE(m.fecha_hora) BETWEEN '{$fecha_desde}' AND '{$fecha_hasta}'
                GROUP BY u.id_usuario
                ORDER BY total_movimientos DESC
            ";
            $resultado = $conexion->query($query);
            break;

        case 'estadisticas_servicio':
            $titulo_reporte = 'Estadísticas por Fuente';
            $query = "
                SELECT f.nombre as fuente,
                       COUNT(h.id_historia) as total_hc,
                       SUM(CASE WHEN h.estado = 'en_archivo' THEN 1 ELSE 0 END) as en_archivo,
                       SUM(CASE WHEN h.estado = 'en_servicio' THEN 1 ELSE 0 END) as en_servicio,
                       SUM(CASE WHEN h.estado = 'extramuro' THEN 1 ELSE 0 END) as extramuro,
                       SUM(CASE WHEN h.estado = 'extraviada' THEN 1 ELSE 0 END) as extraviadas
                FROM fuentes f
                LEFT JOIN historias_clinicas h ON f.id_fuente = h.id_fuente
                WHERE f.activo = 1
                GROUP BY f.id_fuente
                ORDER BY f.nombre
            ";
            $resultado = $conexion->query($query);
            break;
    }
}

// Obtener fuentes para filtro
$fuentes = $conexion->query("SELECT * FROM fuentes WHERE activo = 1 ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header">
                <h1>Reportes</h1>
                <p class="breadcrumb">Inicio / Reportes</p>
            </div>

            <div class="card">
                <div class="card-header">
                    Seleccionar Reporte
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Tipo de Reporte</label>
                                <select name="tipo" required>
                                    <option value="">Seleccione...</option>
                                    <option value="fuera_archivo" <?php echo $tipo_reporte == 'fuera_archivo' ? 'selected' : ''; ?>>HC Fuera del Archivo</option>
                                    <option value="extramuro" <?php echo $tipo_reporte == 'extramuro' ? 'selected' : ''; ?>>HC Extramuro</option>
                                    <option value="extraviadas" <?php echo $tipo_reporte == 'extraviadas' ? 'selected' : ''; ?>>HC Extraviadas</option>
                                    <option value="movimientos_periodo" <?php echo $tipo_reporte == 'movimientos_periodo' ? 'selected' : ''; ?>>Movimientos por Período</option>
                                    <option value="movimientos_usuario" <?php echo $tipo_reporte == 'movimientos_usuario' ? 'selected' : ''; ?>>Movimientos por Usuario</option>
                                    <option value="estadisticas_servicio" <?php echo $tipo_reporte == 'estadisticas_servicio' ? 'selected' : ''; ?>>Estadísticas por
                                        Fuente</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Fuente</label>
                                <select name="servicio">
                                    <option value="0">Todas</option>
                                    <?php while ($fue = $fuentes->fetch_assoc()): ?>
                                        <option value="<?php echo $fue['id_fuente']; ?>" <?php echo $id_servicio == $fue['id_fuente'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fue['nombre']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row" id="filtros_fecha">
                            <div class="form-group">
                                <label>Desde</label>
                                <input type="date" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                            </div>
                            <div class="form-group">
                                <label>Hasta</label>
                                <input type="date" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Generar Reporte</button>
                            <?php if ($resultado): ?>
                                <button type="button" class="btn btn-success" onclick="exportarExcel()">Exportar a
                                    Excel</button>
                                <button type="button" class="btn btn-secondary" onclick="window.print()">Imprimir</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($resultado): ?>
                <div class="card">
                    <div class="card-header">
                        <?php echo $titulo_reporte; ?>
                        <span style="float: right; font-size: 12px; font-weight: normal;">
                            Generado: <?php echo date('d/m/Y H:i'); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tabla_reporte">
                                <thead>
                                    <tr>
                                        <?php
                                        // Encabezados según tipo de reporte
                                        switch ($tipo_reporte) {
                                            case 'fuera_archivo':
                                                echo '<th>HC</th><th>Paciente</th><th>Fuente</th><th>Estado</th><th>Ubicación</th><th>Último Movimiento</th>';
                                                break;
                                            case 'extramuro':
                                                echo '<th>HC</th><th>Paciente</th><th>Fuente</th><th>Ubicación</th><th>Desde</th><th>Días Fuera</th>';
                                                break;
                                            case 'extraviadas':
                                                echo '<th>HC</th><th>Paciente</th><th>Fuente</th><th>Última Ubicación</th><th>Fecha Extravío</th>';
                                                break;
                                            case 'movimientos_periodo':
                                                echo '<th>Fecha/Hora</th><th>HC</th><th>Paciente</th><th>Fuente</th><th>Tipo</th><th>Origen</th><th>Destino</th><th>Usuario</th>';
                                                break;
                                            case 'movimientos_usuario':
                                                echo '<th>Usuario</th><th>Nombre</th><th>Rol</th><th>Total Movimientos</th><th>Último Movimiento</th>';
                                                break;
                                            case 'estadisticas_servicio':
                                                echo '<th>Fuente</th><th>Total HC</th><th>En Archivo</th><th>En Servicio</th><th>Extramuro</th><th>Extraviadas</th>';
                                                break;
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_registros = 0;
                                    while ($row = $resultado->fetch_assoc()):
                                        $total_registros++;
                                        ?>
                                        <tr>
                                            <?php
                                            switch ($tipo_reporte) {
                                                case 'fuera_archivo':
                                                    echo '<td>' . htmlspecialchars($row['numero_hc']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['paciente']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['fuente']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['estado']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['ubicacion_actual']) . '</td>';
                                                    echo '<td>' . date('d/m/Y H:i', strtotime($row['fecha_ultimo_movimiento'])) . '</td>';
                                                    break;
                                                case 'extramuro':
                                                    echo '<td>' . htmlspecialchars($row['numero_hc']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['paciente']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['fuente']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['ubicacion_actual']) . '</td>';
                                                    echo '<td>' . date('d/m/Y', strtotime($row['fecha_ultimo_movimiento'])) . '</td>';
                                                    echo '<td>' . $row['dias_fuera'] . ' días</td>';
                                                    break;
                                                case 'extraviadas':
                                                    echo '<td>' . htmlspecialchars($row['numero_hc']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['paciente']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['fuente']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['ubicacion_actual']) . '</td>';
                                                    echo '<td>' . date('d/m/Y', strtotime($row['fecha_ultimo_movimiento'])) . '</td>';
                                                    break;
                                                case 'movimientos_periodo':
                                                    echo '<td>' . date('d/m/Y H:i', strtotime($row['fecha_hora'])) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['numero_hc']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['paciente']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['fuente']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['tipo_movimiento']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['ubicacion_origen']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['ubicacion_destino']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                                                    break;
                                                case 'movimientos_usuario':
                                                    echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['nombre'] . ' ' . $row['apellido']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['rol']) . '</td>';
                                                    echo '<td>' . $row['total_movimientos'] . '</td>';
                                                    echo '<td>' . date('d/m/Y H:i', strtotime($row['ultimo_movimiento'])) . '</td>';
                                                    break;
                                                case 'estadisticas_servicio':
                                                    echo '<td>' . htmlspecialchars($row['fuente']) . '</td>';
                                                    echo '<td>' . $row['total_hc'] . '</td>';
                                                    echo '<td>' . $row['en_archivo'] . '</td>';
                                                    echo '<td>' . $row['en_servicio'] . '</td>';
                                                    echo '<td>' . $row['extramuro'] . '</td>';
                                                    echo '<td>' . $row['extraviadas'] . '</td>';
                                                    break;
                                            }
                                            ?>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-20"><strong>Total de registros: <?php echo $total_registros; ?></strong></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function exportarExcel() {
            const tabla = document.getElementById('tabla_reporte');
            let html = '<html><head><meta charset="utf-8"></head><body>';
            html += '<h2><?php echo $titulo_reporte; ?></h2>';
            html += '<p>Generado: <?php echo date("d/m/Y H:i"); ?></p>';
            html += tabla.outerHTML;
            html += '</body></html>';

            const blob = new Blob(['\ufeff', html], {
                type: 'application/vnd.ms-excel'
            });

            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'reporte_<?php echo $tipo_reporte; ?>_<?php echo date("Ymd_His"); ?>.xls';
            link.click();
        }
    </script>
</body>

</html>