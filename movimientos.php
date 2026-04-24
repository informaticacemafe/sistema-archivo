<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

// Filtros
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$tipo_movimiento = isset($_GET['tipo_movimiento']) ? $_GET['tipo_movimiento'] : '';
$fuente = isset($_GET['fuente']) ? intval($_GET['fuente']) : 0;

// Construir query
$query = "
    SELECT m.*, 
           h.numero_hc,
           CONCAT(p.apellido, ', ', p.nombre) as paciente,
           f.nombre as fuente,
           f.codigo as codigo_fuente,
           f.color as color_fuente,
           u.username
    FROM movimientos m
    INNER JOIN historias_clinicas h ON m.id_historia = h.id_historia
    INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
    INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
    INNER JOIN usuarios u ON m.usuario_id = u.id_usuario
    WHERE DATE(m.fecha_hora) BETWEEN ? AND ?
";

$params = array($fecha_desde, $fecha_hasta);
$types = "ss";

if (!empty($tipo_movimiento)) {
    $query .= " AND m.tipo_movimiento = ?";
    $params[] = $tipo_movimiento;
    $types .= "s";
}

if ($fuente > 0) {
    $query .= " AND h.id_fuente = ?";
    $params[] = $fuente;
    $types .= "i";
}

$query .= " ORDER BY m.fecha_hora DESC LIMIT 500";

$stmt = $conexion->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$movimientos = $stmt->get_result();

// Obtener fuentes para filtro
$fuentes = $conexion->query("SELECT * FROM fuentes WHERE activo = 1 ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
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
                <h1>Registro de Movimientos</h1>
                <p class="breadcrumb">Inicio / Movimientos</p>
            </div>
            
            <div class="card no-print">
                <div class="card-header">
                    Filtros de Búsqueda
                </div>
                <div class="card-body">
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
                                <label>Tipo Movimiento</label>
                                <select name="tipo_movimiento">
                                    <option value="">Todos</option>
                                    <option value="ingreso_archivo" <?php echo $tipo_movimiento == 'ingreso_archivo' ? 'selected' : ''; ?>>Ingreso a Archivo</option>
                                    <option value="salida_a_servicio" <?php echo $tipo_movimiento == 'salida_a_servicio' ? 'selected' : ''; ?>>Salida a Servicio</option>
                                    <option value="devolucion_a_archivo" <?php echo $tipo_movimiento == 'devolucion_a_archivo' ? 'selected' : ''; ?>>Devolución</option>
                                    <option value="salida_extramuro" <?php echo $tipo_movimiento == 'salida_extramuro' ? 'selected' : ''; ?>>Salida Extramuro</option>
                                    <option value="ingreso_desde_extramuro" <?php echo $tipo_movimiento == 'ingreso_desde_extramuro' ? 'selected' : ''; ?>>Ingreso Extramuro</option>
                                    <option value="traslado_interno" <?php echo $tipo_movimiento == 'traslado_interno' ? 'selected' : ''; ?>>Traslado</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fuente</label>
                                <select name="fuente">
                                    <option value="0">Todas</option>
                                    <?php while($fue = $fuentes->fetch_assoc()): ?>
                                    <option value="<?php echo $fue['id_fuente']; ?>" <?php echo $fuente == $fue['id_fuente'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($fue['nombre']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <button type="button" class="btn btn-secondary" onclick="window.print()">Imprimir</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Listado de Movimientos
                    <span style="float: right; font-size: 12px; font-weight: normal;">
                        Total: <?php echo $movimientos->num_rows; ?> registros
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>HC</th>
                                    <th>Paciente</th>
                                    <th>Fuente</th>
                                    <th>Tipo Movimiento</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Usuario</th>
                                    <th class="no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($movimientos->num_rows > 0): ?>
                                    <?php while($mov = $movimientos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_hora'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($mov['numero_hc']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($mov['paciente']); ?></td>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo htmlspecialchars($mov['color_fuente'] ?? '#6c757d'); ?>; color: white;">
                                                <?php echo htmlspecialchars($mov['codigo_fuente']); ?>
                                            </span>
                                        </td>
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
                                        <td class="no-print">
                                            <a href="hc_detalle.php?id=<?php echo $mov['id_historia']; ?>" class="btn btn-sm btn-primary">Ver HC</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No se encontraron movimientos</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
