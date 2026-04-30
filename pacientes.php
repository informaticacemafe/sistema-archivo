<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario de nuevo paciente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] == 'crear') {
        $tipo_doc = $_POST['tipo_documento'];
        $num_doc = trim($_POST['numero_documento']);
        $nombre = trim(strtoupper($_POST['nombre']));
        $apellido = trim(strtoupper($_POST['apellido']));
        $fecha_nac = $_POST['fecha_nacimiento'];
        $sexo = $_POST['sexo'];
        $telefono = trim($_POST['telefono']);
        $numeropaciente = (isset($_POST['numeropaciente']) && $_POST['numeropaciente'] !== '') ? intval($_POST['numeropaciente']) : null;

        // Verificar si ya existe
        $stmt = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE tipo_documento = ? AND numero_documento = ?");
        $stmt->bind_param("ss", $tipo_doc, $num_doc);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = 'Ya existe un paciente con ese documento';
            $tipo_mensaje = 'error';
        } elseif ($numeropaciente !== null) {
            // Verificar unicidad del numero SICAP
            $stmt_unico = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE numeropaciente = ?");
            $stmt_unico->bind_param("i", $numeropaciente);
            $stmt_unico->execute();
            if ($stmt_unico->get_result()->num_rows > 0) {
                $mensaje = 'Ya existe un paciente con ese número SICAP';
                $tipo_mensaje = 'error';
            } else {
                $stmt_unico->close();
                goto ejecutar_insert;
            }
            $stmt_unico->close();
        } else {
            ejecutar_insert:
            $columnas = ['tipo_documento', 'numero_documento', 'nombre', 'apellido', 'fecha_nacimiento', 'sexo', 'telefono'];
            $tipos = 'sssssss';
            $params = [$tipo_doc, $num_doc, $nombre, $apellido, $fecha_nac, $sexo, $telefono];
            if ($numeropaciente !== null) {
                $columnas[] = 'numeropaciente';
                $tipos .= 'i';
                $params[] = $numeropaciente;
            }
            $sql = "INSERT INTO pacientes (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', array_fill(0, count($params), '?')) . ")";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param($tipos, ...$params);

            if ($stmt->execute()) {
                $id_nuevo = $conexion->insert_id;
                $resumen = "Se creó paciente: {$apellido}, {$nombre} ({$tipo_doc} {$num_doc})";
                $detalle_nuevo = [
                    'tipo_documento' => $tipo_doc,
                    'numero_documento' => $num_doc,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'fecha_nacimiento' => $fecha_nac,
                    'sexo' => $sexo,
                    'telefono' => $telefono,
                    'numeropaciente' => $numeropaciente
                ];
                registrarLog('paciente', $id_nuevo, 'CREAR', $resumen, null, $detalle_nuevo);
                $mensaje = 'Paciente registrado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al registrar paciente: ' . $conexion->error;
                $tipo_mensaje = 'error';
            }
        }
        $stmt->close();
    }
    
    if ($_POST['accion'] == 'editar') {
        $id_paciente = $_POST['id_paciente'];
        $nombre = trim(strtoupper($_POST['nombre']));
        $apellido = trim(strtoupper($_POST['apellido']));
        $fecha_nac = $_POST['fecha_nacimiento'];
        $sexo = $_POST['sexo'];
        $telefono = trim($_POST['telefono']);
        $numeropaciente = (isset($_POST['numeropaciente']) && $_POST['numeropaciente'] !== '') ? intval($_POST['numeropaciente']) : null;

        // Obtener valores anteriores
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

        // Construir UPDATE dinámico para manejar numeropaciente NULL
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
        } else {
            $mensaje = 'Error al actualizar paciente';
            $tipo_mensaje = 'error';
        }
        $stmt->close();
        fin_editar:
    }
}

// Paginación
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 50;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// Búsqueda de pacientes
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Query base sin GROUP_CONCAT inicialmente
$query_base = "FROM pacientes p WHERE 1=1";
$params_base = array();
$types_base = "";

if (!empty($busqueda)) {
    $query_base .= " AND (p.numero_documento LIKE ? OR CONCAT(p.apellido, ' ', p.nombre) LIKE ? OR CONCAT(p.nombre, ' ', p.apellido) LIKE ?)";
    $buscar_param = "%{$busqueda}%";
    $params_base[] = $buscar_param;
    $params_base[] = $buscar_param;
    $params_base[] = $buscar_param;
    $types_base = "sss";
}

// Contar total de pacientes para paginación
$query_count = "SELECT COUNT(DISTINCT p.id_paciente) as total " . $query_base;
if (!empty($params_base)) {
    $stmt_count = $conexion->prepare($query_count);
    $stmt_count->bind_param($types_base, ...$params_base);
    $stmt_count->execute();
    $total_pacientes = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();
} else {
    $result_count = $conexion->query($query_count);
    $total_pacientes = $result_count->fetch_assoc()['total'];
}

$total_paginas = ceil($total_pacientes / $por_pagina);

// Query principal con HC
$query = "
    SELECT p.*, 
           GROUP_CONCAT(DISTINCT CONCAT(f.codigo, ':', f.color, ':', h.numero_hc) SEPARATOR '|') as hc_info
    FROM pacientes p
    LEFT JOIN historias_clinicas h ON p.id_paciente = h.id_paciente
    LEFT JOIN fuentes f ON h.id_fuente = f.id_fuente
    WHERE 1=1
";

$params = array();
$types = "";

if (!empty($busqueda)) {
    $query .= " AND (p.numero_documento LIKE ? OR CONCAT(p.apellido, ' ', p.nombre) LIKE ? OR CONCAT(p.nombre, ' ', p.apellido) LIKE ?)";
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $types = "sss";
}

$query .= " GROUP BY p.id_paciente ORDER BY p.apellido, p.nombre LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt = $conexion->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$pacientes = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pacientes - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }
        .hc-badge {
            display: inline-block;
            padding: 4px 8px;
            margin: 2px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            text-decoration: none;
        }
        .hc-badge:hover {
            opacity: 0.8;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination button, .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            border-radius: 3px;
        }
        .pagination button:hover, .pagination a:hover {
            background: #f8f9fa;
        }
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .input-error {
            border: 2px solid #dc3545 !important;
            background-color: #fff8f8 !important;
        }
        button:disabled, .btn:disabled {
            opacity: 0.45 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            filter: grayscale(0.6);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-header no-print">
                <h1>Gestión de Pacientes</h1>
                <p class="breadcrumb">Inicio / Pacientes</p>
            </div>
            
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> no-print">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header flex-between">
                    <span>Búsqueda de Pacientes</span>
                    <div class="no-print">
                        <button class="btn btn-primary btn-sm" onclick="abrirModalNuevo()">+ Nuevo Paciente</button>
                        <button class="btn btn-secondary btn-sm" onclick="window.print()">Imprimir</button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="mb-20 no-print">
                        <div class="form-row">
                            <div class="form-group" style="flex: 3;">
                                <input type="text" name="buscar" placeholder="Buscar por documento, nombre o apellido..." value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            <div class="form-group">
                                <select name="por_pagina">
                                    <option value="10" <?php echo $por_pagina == 10 ? 'selected' : ''; ?>>10 por página</option>
                                    <option value="50" <?php echo $por_pagina == 50 ? 'selected' : ''; ?>>50 por página</option>
                                    <option value="100" <?php echo $por_pagina == 100 ? 'selected' : ''; ?>>100 por página</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Buscar</button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Apellido y Nombre</th>
                                    <th>Fecha Nac.</th>
                                    <th>Sexo</th>
                                    <th>Teléfono</th>
                                    <th>Historias Clínicas</th>
                                    <th class="no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pacientes->num_rows > 0): ?>
                                    <?php while($pac = $pacientes->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pac['tipo_documento'] . ' ' . $pac['numero_documento']); ?></td>
                                        <td><?php echo htmlspecialchars($pac['apellido'] . ', ' . $pac['nombre']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($pac['fecha_nacimiento'])); ?></td>
                                        <td><?php echo htmlspecialchars($pac['sexo']); ?></td>
                                        <td><?php echo htmlspecialchars($pac['telefono']); ?></td>
                                        <td>
                                            <?php 
                                            if (!empty($pac['hc_info'])) {
                                                $hc_list = explode('|', $pac['hc_info']);
                                                foreach ($hc_list as $hc) {
                                                    $parts = explode(':', $hc);
                                                    if (count($parts) == 3) {
                                                        $codigo = $parts[0];
                                                        $color = $parts[1];
                                                        $numero = $parts[2];
                                                        echo '<a href="hc_detalle.php?numero=' . urlencode($numero) . '" class="hc-badge" style="background-color: ' . htmlspecialchars($color) . '" title="' . htmlspecialchars($codigo . ': ' . $numero) . '">';
                                                        echo htmlspecialchars($codigo . ' ' . $numero);
                                                        echo '</a>';
                                                    }
                                                }
                                            } else {
                                                echo '<span style="color: #999;">Sin HC</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="no-print">
                                            <button class="btn btn-sm btn-secondary" onclick="editarPaciente(<?php echo $pac['id_paciente']; ?>)">Editar</button>
                                            <a href="historias_clinicas.php?paciente=<?php echo $pac['id_paciente']; ?>" class="btn btn-sm btn-primary">Números de HC</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No se encontraron pacientes</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                    <div class="pagination no-print">
                        <a href="?pagina=1&buscar=<?php echo urlencode($busqueda); ?>&por_pagina=<?php echo $por_pagina; ?>" <?php echo $pagina_actual == 1 ? 'style="pointer-events:none;opacity:0.5;"' : ''; ?>>
                            &laquo;&laquo; Primera
                        </a>
                        
                        <a href="?pagina=<?php echo max(1, $pagina_actual - 1); ?>&buscar=<?php echo urlencode($busqueda); ?>&por_pagina=<?php echo $por_pagina; ?>" <?php echo $pagina_actual == 1 ? 'style="pointer-events:none;opacity:0.5;"' : ''; ?>>
                            &laquo; Anterior
                        </a>
                        
                        <?php
                        $rango = 2;
                        $inicio = max(1, $pagina_actual - $rango);
                        $fin = min($total_paginas, $pagina_actual + $rango);
                        
                        for ($i = $inicio; $i <= $fin; $i++):
                        ?>
                            <a href="?pagina=<?php echo $i; ?>&buscar=<?php echo urlencode($busqueda); ?>&por_pagina=<?php echo $por_pagina; ?>" class="<?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <a href="?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?>&buscar=<?php echo urlencode($busqueda); ?>&por_pagina=<?php echo $por_pagina; ?>" <?php echo $pagina_actual == $total_paginas ? 'style="pointer-events:none;opacity:0.5;"' : ''; ?>>
                            Siguiente &raquo;
                        </a>
                        
                        <a href="?pagina=<?php echo $total_paginas; ?>&buscar=<?php echo urlencode($busqueda); ?>&por_pagina=<?php echo $por_pagina; ?>" <?php echo $pagina_actual == $total_paginas ? 'style="pointer-events:none;opacity:0.5;"' : ''; ?>>
                            Última &raquo;&raquo;
                        </a>
                        
                        <span style="padding: 8px 12px;">
                            Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?> (<?php echo $total_pacientes; ?> pacientes)
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Nuevo Paciente -->
    <div id="modalNuevo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nuevo Paciente</h2>
                <button class="modal-close" onclick="cerrarModal('modalNuevo')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo Documento *</label>
                        <select name="tipo_documento" required>
                            <option value="DNI">DNI</option>
                            <option value="LC">LC</option>
                            <option value="LE">LE</option>
                            <option value="Pasaporte">Pasaporte</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Número Documento *</label>
                        <input type="text" name="numero_documento" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Apellido *</label>
                        <input type="text" name="apellido" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha Nacimiento *</label>
                        <input type="date" name="fecha_nacimiento" required>
                    </div>
                    <div class="form-group">
                        <label>Sexo *</label>
                        <select name="sexo" required>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="X">Otro</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono">
                </div>
                
                <?php if (tienePermiso('administrador')): ?>
                <div class="form-group">
                    <label>Numero de paciente en SICAP</label>
                    <input type="number" name="numeropaciente" id="numeropaciente" placeholder="Opcional"
                        oninput="validarNumeropacienteDuplicado(this)">
                    <small id="error_numeropaciente_existe" style="color: #dc3545; display: none; margin-top: 5px;">Ya existe un paciente con ese número SICAP</small>
                </div>
                <?php endif; ?>
                
                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNuevo')">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btn_guardar_paciente">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function abrirModalNuevo() {
            document.getElementById('modalNuevo').classList.add('active');
            const inputSicap = document.getElementById('numeropaciente');
            if (inputSicap) {
                inputSicap.value = '';
                inputSicap.classList.remove('input-error');
                document.getElementById('error_numeropaciente_existe').style.display = 'none';
            }
            const btnGuardar = document.getElementById('btn_guardar_paciente');
            if (btnGuardar) { btnGuardar.disabled = false; }
        }
        
        function cerrarModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function editarPaciente(id) {
            window.location.href = 'paciente_editar.php?id=' + id;
        }

        // Validación de número SICAP duplicado en tiempo real (AJAX)
        let timeoutSicap;
        function validarNumeropacienteDuplicado(input) {
            const valor = input.value.trim();
            const errorLabel = document.getElementById('error_numeropaciente_existe');
            const btnGuardar = document.getElementById('btn_guardar_paciente');

            if (!valor) {
                input.classList.remove('input-error');
                errorLabel.style.display = 'none';
                if (btnGuardar) { btnGuardar.disabled = false; }
                return;
            }

            clearTimeout(timeoutSicap);
            timeoutSicap = setTimeout(() => {
                fetch(`verificar_numeropaciente_ajax.php?numeropaciente=${encodeURIComponent(valor)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.existe) {
                            input.classList.add('input-error');
                            errorLabel.style.display = 'block';
                            if (btnGuardar) { btnGuardar.disabled = true; }
                        } else {
                            input.classList.remove('input-error');
                            errorLabel.style.display = 'none';
                            if (btnGuardar) { btnGuardar.disabled = false; }
                        }
                    })
                    .catch(() => {
                        input.classList.remove('input-error');
                        errorLabel.style.display = 'none';
                        if (btnGuardar) { btnGuardar.disabled = false; }
                    });
            }, 400);
        }

        // Bloquear submit si el número SICAP ya existe
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('#modalNuevo form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    const errorSicap = document.getElementById('error_numeropaciente_existe');
                    if (errorSicap && errorSicap.style.display !== 'none') {
                        e.preventDefault();
                        alert('Ya existe un paciente con ese número SICAP. Corrija el número antes de guardar.');
                        return false;
                    }
                });
            }
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
