<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

// Obtener estadísticas
$stats = array();

// Total de pacientes
$result = $conexion->query("SELECT COUNT(*) as total FROM pacientes");
$stats['total_pacientes'] = $result->fetch_assoc()['total'];

// Total de historias clínicas
$result = $conexion->query("SELECT COUNT(*) as total FROM historias_clinicas");
$stats['total_hc'] = $result->fetch_assoc()['total'];

// Historias en archivo
$result = $conexion->query("SELECT COUNT(*) as total FROM historias_clinicas WHERE estado = 'en_archivo'");
$stats['hc_en_archivo'] = $result->fetch_assoc()['total'];

// Historias en servicio
$result = $conexion->query("SELECT COUNT(*) as total FROM historias_clinicas WHERE estado = 'en_servicio'");
$stats['hc_en_servicio'] = $result->fetch_assoc()['total'];

// Historias extramuro
$result = $conexion->query("SELECT COUNT(*) as total FROM historias_clinicas WHERE estado = 'extramuro'");
$stats['hc_extramuro'] = $result->fetch_assoc()['total'];

// Historias extraviadas
$result = $conexion->query("SELECT COUNT(*) as total FROM historias_clinicas WHERE estado = 'extraviada'");
$stats['hc_extraviadas'] = $result->fetch_assoc()['total'];

// Últimos movimientos
$query_movimientos = "
    SELECT m.*, h.numero_hc, s.nombre as servicio, 
           CONCAT(p.apellido, ', ', p.nombre) as paciente,
           u.username
    FROM movimientos m
    INNER JOIN historias_clinicas h ON m.id_historia = h.id_historia
    INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
    INNER JOIN servicios s ON h.id_servicio = s.id_servicio
    INNER JOIN usuarios u ON m.usuario_id = u.id_usuario
    ORDER BY m.fecha_hora DESC
    LIMIT 10
";
$ultimos_movimientos = $conexion->query($query_movimientos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Panel de Control</h1>
                <p class="breadcrumb">Inicio / Dashboard</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_pacientes']); ?></h3>
                    <p>Total Pacientes</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_hc']); ?></h3>
                    <p>Total Historias Clínicas</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo number_format($stats['hc_en_archivo']); ?></h3>
                    <p>HC en Archivo</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo number_format($stats['hc_en_servicio']); ?></h3>
                    <p>HC en Servicio</p>
                </div>
                
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <h3><?php echo number_format($stats['hc_extramuro']); ?></h3>
                    <p>HC Extramuro</p>
                </div>
                
                <div class="stat-card" style="border-left-color: #dc3545;">
                    <h3><?php echo number_format($stats['hc_extraviadas']); ?></h3>
                    <p>HC Extraviadas</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Últimos Movimientos
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>HC</th>
                                    <th>Paciente</th>
                                    <th>Servicio</th>
                                    <th>Tipo Movimiento</th>
                                    <th>Destino</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($ultimos_movimientos->num_rows > 0): ?>
                                    <?php while($mov = $ultimos_movimientos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_hora'])); ?></td>
                                        <td><?php echo htmlspecialchars($mov['numero_hc']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['paciente']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['servicio']); ?></td>
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
                                        <td><?php echo htmlspecialchars($mov['ubicacion_destino']); ?></td>
                                        <td><?php echo htmlspecialchars($mov['username']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No hay movimientos registrados</td>
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
