<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

$mensaje = '';
$tipo_mensaje = '';
$id_paciente = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_paciente == 0) {
    header('Location: pacientes.php');
    exit();
}

// Obtener datos del paciente
$stmt = $conexion->prepare("SELECT * FROM pacientes WHERE id_paciente = ?");
$stmt->bind_param("i", $id_paciente);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();

if (!$paciente) {
    header('Location: pacientes.php');
    exit();
}

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim(strtoupper($_POST['nombre']));
    $apellido = trim(strtoupper($_POST['apellido']));
    $fecha_nac = $_POST['fecha_nacimiento'];
    $sexo = $_POST['sexo'];
    $telefono = trim($_POST['telefono']);
    $numeropaciente = (isset($_POST['numeropaciente']) && $_POST['numeropaciente'] !== '') ? intval($_POST['numeropaciente']) : null;
    
    // Obtener valores anteriores para auditoría
    $stmt = $conexion->prepare("SELECT nombre, apellido, fecha_nacimiento, sexo, telefono, numeropaciente FROM pacientes WHERE id_paciente = ?");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $anterior = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verificar unicidad del numero SICAP si cambió
    if ($numeropaciente !== null && $numeropaciente != $anterior['numeropaciente']) {
        $stmt_unico = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE numeropaciente = ? AND id_paciente != ?");
        $stmt_unico->bind_param("ii", $numeropaciente, $id_paciente);
        $stmt_unico->execute();
        if ($stmt_unico->get_result()->num_rows > 0) {
            $mensaje = 'Ya existe un paciente con ese número SICAP';
            $tipo_mensaje = 'error';
            $stmt_unico->close();
            goto fin_editar;
        }
        $stmt_unico->close();
    }
    
    // Actualizar paciente (UPDATE dinámico para manejar numeropaciente NULL)
    $sets = ['nombre = ?', 'apellido = ?', 'fecha_nacimiento = ?', 'sexo = ?', 'telefono = ?'];
    $tipos = 'sssss';
    $params = [$nombre, $apellido, $fecha_nac, $sexo, $telefono];
    if ($numeropaciente !== null) {
        $sets[] = 'numeropaciente = ?';
        $tipos .= 'i';
        $params[] = $numeropaciente;
    } else {
        $sets[] = 'numeropaciente = NULL';
    }
    $params[] = $id_paciente;
    $tipos .= 'i';
    $sql = "UPDATE pacientes SET " . implode(', ', $sets) . " WHERE id_paciente = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($tipos, ...$params);
    
    if ($stmt->execute()) {
        // Registrar cambios en el log
        $detalle_anterior = [];
        $detalle_nuevo = [];
        $hay_cambios = false;
        if ($anterior['nombre'] != $nombre) { $detalle_anterior['nombre'] = $anterior['nombre']; $detalle_nuevo['nombre'] = $nombre; $hay_cambios = true; }
        if ($anterior['apellido'] != $apellido) { $detalle_anterior['apellido'] = $anterior['apellido']; $detalle_nuevo['apellido'] = $apellido; $hay_cambios = true; }
        if ($anterior['fecha_nacimiento'] != $fecha_nac) { $detalle_anterior['fecha_nacimiento'] = $anterior['fecha_nacimiento']; $detalle_nuevo['fecha_nacimiento'] = $fecha_nac; $hay_cambios = true; }
        if ($anterior['sexo'] != $sexo) { $detalle_anterior['sexo'] = $anterior['sexo']; $detalle_nuevo['sexo'] = $sexo; $hay_cambios = true; }
        if ($anterior['telefono'] != $telefono) { $detalle_anterior['telefono'] = $anterior['telefono']; $detalle_nuevo['telefono'] = $telefono; $hay_cambios = true; }
        if ($anterior['numeropaciente'] != $numeropaciente) { $detalle_anterior['numeropaciente'] = $anterior['numeropaciente']; $detalle_nuevo['numeropaciente'] = $numeropaciente; $hay_cambios = true; }
        
        if ($hay_cambios) {
            $resumen = "Se modificó paciente: {$apellido}, {$nombre}";
            registrarLog('paciente', $id_paciente, 'EDITAR', $resumen, $detalle_anterior, $detalle_nuevo);
        }
        
        $mensaje = 'Paciente actualizado exitosamente';
        $tipo_mensaje = 'success';
        
        // Actualizar datos del paciente para mostrar
        $paciente['nombre'] = $nombre;
        $paciente['apellido'] = $apellido;
        $paciente['fecha_nacimiento'] = $fecha_nac;
        $paciente['sexo'] = $sexo;
        $paciente['telefono'] = $telefono;
        $paciente['numeropaciente'] = $numeropaciente;
    } else {
        $mensaje = 'Error al actualizar paciente: ' . $conexion->error;
        $tipo_mensaje = 'error';
    }
    $stmt->close();
    fin_editar:
}

// Obtener historias clínicas del paciente
$hc_query = "
    SELECT h.*, f.nombre as fuente, f.codigo as codigo_fuente, f.color as color_fuente
    FROM historias_clinicas h
    INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
    WHERE h.id_paciente = ?
    ORDER BY f.nombre, h.numero_hc
";
$stmt_hc = $conexion->prepare($hc_query);
$stmt_hc->bind_param("i", $id_paciente);
$stmt_hc->execute();
$historias = $stmt_hc->get_result();
$stmt_hc->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Paciente - Sistema HC</title>
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
                <h1>Editar Paciente</h1>
                <p class="breadcrumb">
                    <a href="pacientes.php">Pacientes</a> / Editar
                </p>
            </div>
            
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> no-print">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header flex-between">
                    <span>Datos del Paciente</span>
                    <button class="btn btn-secondary btn-sm no-print" onclick="window.print()">Imprimir</button>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Tipo y Número de Documento</label>
                            <div style="padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
                                <strong><?php echo htmlspecialchars($paciente['tipo_documento'] . ' ' . $paciente['numero_documento']); ?></strong>
                                <br><small style="color: #666;">El documento no puede ser modificado</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nombre *</label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($paciente['nombre']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Apellido *</label>
                                <input type="text" name="apellido" value="<?php echo htmlspecialchars($paciente['apellido']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Fecha de Nacimiento *</label>
                                <input type="date" name="fecha_nacimiento" value="<?php echo $paciente['fecha_nacimiento']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Sexo *</label>
                                <select name="sexo" required>
                                    <option value="M" <?php echo $paciente['sexo'] == 'M' ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="F" <?php echo $paciente['sexo'] == 'F' ? 'selected' : ''; ?>>Femenino</option>
                                    <option value="X" <?php echo $paciente['sexo'] == 'X' ? 'selected' : ''; ?>>Otro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="tel" name="telefono" value="<?php echo htmlspecialchars($paciente['telefono']); ?>">
                        </div>
                        
                        <?php if (tienePermiso('administrador')): ?>
                        <div class="form-group">
                            <label>Numero de paciente en SICAP</label>
                            <input type="number" name="numeropaciente" placeholder="Opcional"
                                value="<?php echo htmlspecialchars($paciente['numeropaciente'] ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group text-right no-print">
                            <a href="pacientes.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header flex-between">
                    <span>Historias Clínicas del Paciente</span>
                    <a href="historias_clinicas.php?paciente=<?php echo $id_paciente; ?>" class="btn btn-primary btn-sm no-print">+ Nueva HC</a>
                </div>
                <div class="card-body">
                    <?php if ($historias->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Número HC</th>
                                    <th>Fuente</th>
                                    <th>Estado</th>
                                    <th>Ubicación Actual</th>
                                    <th>Último Movimiento</th>
                                    <th class="no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($hc = $historias->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($hc['numero_hc']); ?></strong></td>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo htmlspecialchars($hc['color_fuente']); ?>; color: white;">
                                            <?php echo htmlspecialchars($hc['codigo_fuente']); ?>
                                        </span>
                                        <?php echo htmlspecialchars($hc['fuente']); ?>
                                    </td>
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
                                        echo '<span class="badge badge-' . $badges[$hc['estado']] . '">' . $estados_texto[$hc['estado']] . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($hc['ubicacion_actual']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($hc['fecha_ultimo_movimiento'])); ?></td>
                                    <td class="no-print">
                                        <a href="hc_detalle.php?id=<?php echo $hc['id_historia']; ?>" class="btn btn-sm btn-primary">Ver</a>
                                        <?php if (!tienePermiso('auditor')): ?>
                                        <a href="movimiento_nuevo.php?hc=<?php echo $hc['id_historia']; ?>" class="btn btn-sm btn-warning">Mover</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        Este paciente no tiene historias clínicas registradas.
                        <a href="historias_clinicas.php?paciente=<?php echo $id_paciente; ?>" class="btn btn-sm btn-primary no-print">Crear Primera HC</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-20 no-print">
                <a href="pacientes.php" class="btn btn-secondary">Volver al Listado</a>
            </div>
        </div>
    </div>
</body>
</html>
