<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

$resultados = null;
$tipo_busqueda = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_busqueda = $_POST['tipo_busqueda'];
    $termino = trim($_POST['termino']);
    
    if (!empty($termino)) {
        switch($tipo_busqueda) {
            case 'paciente':
                $stmt = $conexion->prepare("
                    SELECT p.*, 
                           COUNT(h.id_historia) as total_hc
                    FROM pacientes p
                    LEFT JOIN historias_clinicas h ON p.id_paciente = h.id_paciente
                    WHERE p.numero_documento LIKE ? 
                       OR CONCAT(p.apellido, ' ', p.nombre) LIKE ?
                       OR CONCAT(p.nombre, ' ', p.apellido) LIKE ?
                    GROUP BY p.id_paciente
                    ORDER BY p.apellido, p.nombre
                    LIMIT 50
                ");
                $buscar = "%{$termino}%";
                $stmt->bind_param("sss", $buscar, $buscar, $buscar);
                $stmt->execute();
                $resultados = $stmt->get_result();
                break;
                
            case 'hc':
                $stmt = $conexion->prepare("
                    SELECT h.*, 
                           CONCAT(p.apellido, ', ', p.nombre) as paciente,
                           p.numero_documento,
                           s.nombre as servicio
                    FROM historias_clinicas h
                    INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
                    INNER JOIN servicios s ON h.id_servicio = s.id_servicio
                    WHERE h.numero_hc LIKE ?
                    ORDER BY h.numero_hc
                    LIMIT 50
                ");
                $buscar = "%{$termino}%";
                $stmt->bind_param("s", $buscar);
                $stmt->execute();
                $resultados = $stmt->get_result();
                break;
                
            case 'ubicacion':
                $stmt = $conexion->prepare("
                    SELECT h.*, 
                           CONCAT(p.apellido, ', ', p.nombre) as paciente,
                           s.nombre as servicio
                    FROM historias_clinicas h
                    INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
                    INNER JOIN servicios s ON h.id_servicio = s.id_servicio
                    WHERE h.ubicacion_actual LIKE ?
                    ORDER BY h.ubicacion_actual, h.numero_hc
                    LIMIT 100
                ");
                $buscar = "%{$termino}%";
                $stmt->bind_param("s", $buscar);
                $stmt->execute();
                $resultados = $stmt->get_result();
                break;
        }
    }
}

$servicios = $conexion->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda Avanzada - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Búsqueda Avanzada</h1>
                <p class="breadcrumb">Inicio / Búsqueda Avanzada</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Opciones de Búsqueda
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tipo de Búsqueda</label>
                                <select name="tipo_busqueda" required>
                                    <option value="">Seleccione...</option>
                                    <option value="paciente" <?php echo $tipo_busqueda == 'paciente' ? 'selected' : ''; ?>>Buscar Paciente</option>
                                    <option value="hc" <?php echo $tipo_busqueda == 'hc' ? 'selected' : ''; ?>>Buscar Historia Clínica</option>
                                    <option value="ubicacion" <?php echo $tipo_busqueda == 'ubicacion' ? 'selected' : ''; ?>>Buscar por Ubicación</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="flex: 2;">
                                <label>Término de Búsqueda</label>
                                <input type="text" name="termino" required placeholder="Ingrese el término a buscar..." value="<?php echo isset($_POST['termino']) ? htmlspecialchars($_POST['termino']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary">Buscar</button>
                            </div>
                        </div>
                    </form>
                    
                    <div style="margin-top: 15px; padding: 15px; background-color: #e7f3ff; border-radius: 5px;">
                        <strong>Ayuda de búsqueda:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <li><strong>Paciente:</strong> Busca por documento, nombre o apellido</li>
                            <li><strong>Historia Clínica:</strong> Busca por número de HC</li>
                            <li><strong>Ubicación:</strong> Busca HC por su ubicación actual (ej: "Servicio de Cirugía")</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <?php if ($resultados !== null): ?>
            <div class="card">
                <div class="card-header">
                    Resultados de la Búsqueda
                </div>
                <div class="card-body">
                    <?php if ($resultados->num_rows > 0): ?>
                        <p><strong>Se encontraron <?php echo $resultados->num_rows; ?> resultado(s)</strong></p>
                        
                        <?php if ($tipo_busqueda == 'paciente'): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Apellido y Nombre</th>
                                        <th>Fecha Nac.</th>
                                        <th>Sexo</th>
                                        <th>Teléfono</th>
                                        <th>HC Asociadas</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $resultados->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['tipo_documento'] . ' ' . $row['numero_documento']); ?></td>
                                        <td><?php echo htmlspecialchars($row['apellido'] . ', ' . $row['nombre']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['fecha_nacimiento'])); ?></td>
                                        <td><?php echo $row['sexo']; ?></td>
                                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                        <td><?php echo $row['total_hc']; ?></td>
                                        <td>
                                            <a href="historias_clinicas.php?paciente=<?php echo $row['id_paciente']; ?>" class="btn btn-sm btn-primary">Ver HC</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($tipo_busqueda == 'hc' || $tipo_busqueda == 'ubicacion'): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>HC</th>
                                        <th>Paciente</th>
                                        <th>Documento</th>
                                        <th>Servicio</th>
                                        <th>Estado</th>
                                        <th>Ubicación Actual</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $resultados->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['numero_hc']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['paciente']); ?></td>
                                        <td><?php echo htmlspecialchars($row['numero_documento']); ?></td>
                                        <td><?php echo htmlspecialchars($row['servicio']); ?></td>
                                        <td>
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
                                                'dada_de_baja' => 'Baja',
                                                'extraviada' => 'Extraviada'
                                            );
                                            echo '<span class="badge badge-' . $badges[$row['estado']] . '">' . $estados_texto[$row['estado']] . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['ubicacion_actual']); ?></td>
                                        <td>
                                            <a href="hc_detalle.php?id=<?php echo $row['id_historia']; ?>" class="btn btn-sm btn-primary">Ver</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="alert alert-info">
                            No se encontraron resultados para la búsqueda realizada.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
