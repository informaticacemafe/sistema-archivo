<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

$mensaje = '';
$tipo_mensaje = '';

// Manejar mensajes de redirección
if (isset($_GET['mensaje'])) {
    switch ($_GET['mensaje']) {
        case 'hc_eliminada':
            $movimientos_eliminados = isset($_GET['movimientos']) ? intval($_GET['movimientos']) : 0;
            $mensaje = "Historia Clínica eliminada exitosamente (incluyendo {$movimientos_eliminados} movimiento" . ($movimientos_eliminados != 1 ? 's' : '') . ")";
            $tipo_mensaje = 'success';
            break;
    }
}

// Procesar nueva HC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'crear') {
    $id_paciente = intval($_POST['id_paciente']);
    $id_fuente = intval($_POST['id_fuente']);
    $numero_hc = trim($_POST['numero_hc']);
    $observaciones = trim($_POST['observaciones']);

    // Validar que el paciente existe
    $stmt_validar = $conexion->prepare("SELECT id_paciente FROM pacientes WHERE id_paciente = ?");
    $stmt_validar->bind_param("i", $id_paciente);
    $stmt_validar->execute();
    $paciente_existe = $stmt_validar->get_result();

    if ($paciente_existe->num_rows == 0) {
        $mensaje = 'Error: El paciente seleccionado no existe. ID: ' . $id_paciente;
        $tipo_mensaje = 'error';
    } else {
        // Verificar si ya existe esa HC en la fuente
        $stmt = $conexion->prepare("SELECT id_historia FROM historias_clinicas WHERE numero_hc = ? AND id_fuente = ?");
        $stmt->bind_param("si", $numero_hc, $id_fuente);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = 'Ya existe una HC con ese número en la fuente seleccionada';
            $tipo_mensaje = 'error';
        } else {
            $usuario_id = $_SESSION['usuario_id'];

            // Obtener ubicación inicial seleccionada
            $ubicacion_inicial = trim($_POST['ubicacion_inicial']);

            // Si seleccionó archivo del servicio, construir nombre con la fuente
            if ($ubicacion_inicial === 'archivo_servicio') {
                // Obtener nombre de la fuente
                $stmt_fuente = $conexion->prepare("SELECT nombre FROM fuentes WHERE id_fuente = ?");
                $stmt_fuente->bind_param("i", $id_fuente);
                $stmt_fuente->execute();
                $fuente_nombre = $stmt_fuente->get_result()->fetch_assoc()['nombre'];
                $stmt_fuente->close();

                $ubicacion_inicial = "Archivo " . $fuente_nombre;
            }

            $stmt = $conexion->prepare("INSERT INTO historias_clinicas (id_paciente, id_fuente, numero_hc, estado, ubicacion_actual, observaciones, fecha_creacion) VALUES (?, ?, ?, 'en_archivo', ?, ?, NOW())");
            $stmt->bind_param("iisss", $id_paciente, $id_fuente, $numero_hc, $ubicacion_inicial, $observaciones);

            if ($stmt->execute()) {
                $id_nueva_hc = $conexion->insert_id;

                // Registrar movimiento inicial con ubicación seleccionada
                $stmt_mov = $conexion->prepare("INSERT INTO movimientos (id_historia, tipo_movimiento, ubicacion_origen, ubicacion_destino, usuario_id, observaciones) VALUES (?, 'ingreso_archivo', '', ?, ?, 'Creación de HC')");
                $stmt_mov->bind_param("isi", $id_nueva_hc, $ubicacion_inicial, $usuario_id);
                $stmt_mov->execute();

                registrarAuditoria('historias_clinicas', $id_nueva_hc, 'CREACION', '', 'HC creada', 'INSERT');

                $mensaje = 'Historia Clínica registrada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al registrar HC: ' . $conexion->error;
                $tipo_mensaje = 'error';
            }
        }
        $stmt->close();
    }
    $stmt_validar->close();
}

// Filtros
$filtro_paciente = isset($_GET['paciente']) ? intval($_GET['paciente']) : 0;
$filtro_fuente = isset($_GET['fuente']) ? intval($_GET['fuente']) : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Si hay paciente filtrado, obtener sus datos
$paciente_info = null;
if ($filtro_paciente > 0) {
    $stmt_pac = $conexion->prepare("SELECT id_paciente, CONCAT(apellido, ', ', nombre) as nombre_completo, tipo_documento, numero_documento FROM pacientes WHERE id_paciente = ?");
    $stmt_pac->bind_param("i", $filtro_paciente);
    $stmt_pac->execute();
    $paciente_info = $stmt_pac->get_result()->fetch_assoc();
    $stmt_pac->close();
}

// Construir query
$query = "
    SELECT h.*, 
           CONCAT(p.apellido, ', ', p.nombre) as paciente,
           p.tipo_documento, p.numero_documento,
           f.nombre as fuente, f.codigo as codigo_fuente, f.color as color_fuente
    FROM historias_clinicas h
    INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
    INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
    WHERE 1=1
";

$params = array();
$types = "";

if ($filtro_paciente > 0) {
    $query .= " AND h.id_paciente = ?";
    $params[] = $filtro_paciente;
    $types .= "i";
}

if ($filtro_fuente > 0) {
    $query .= " AND h.id_fuente = ?";
    $params[] = $filtro_fuente;
    $types .= "i";
}

if (!empty($filtro_estado)) {
    $query .= " AND h.estado = ?";
    $params[] = $filtro_estado;
    $types .= "s";
}

if (!empty($buscar)) {
    $query .= " AND (h.numero_hc LIKE ? OR CONCAT(p.apellido, ' ', p.nombre) LIKE ? OR CONCAT(p.nombre, ' ', p.apellido) LIKE ? OR p.numero_documento LIKE ?)";
    $buscar_param = "%{$buscar}%";
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $params[] = $buscar_param;
    $types .= "ssss";
}

$query .= " ORDER BY h.fecha_ultimo_movimiento DESC LIMIT 100";

if (!empty($params)) {
    $stmt = $conexion->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $historias = $stmt->get_result();
} else {
    $historias = $conexion->query($query);
}

// Obtener fuentes para el formulario (respetando fuentes del usuario si corresponde)
$fuentes = $conexion->query("SELECT id_fuente, nombre, codigo, color, tiene_archivo, formato_numeracion FROM fuentes WHERE activo = 1 ORDER BY nombre");

// Obtener fuentes asociadas al usuario logueado
$usuario_fuentes = [];
$stmt_uf = $conexion->prepare("SELECT uf.id_fuente, f.nombre, f.tiene_archivo FROM usuarios_fuentes uf INNER JOIN fuentes f ON uf.id_fuente = f.id_fuente WHERE uf.id_usuario = ? AND f.activo = 1");
$stmt_uf->bind_param("i", $_SESSION['usuario_id']);
$stmt_uf->execute();
$res_uf = $stmt_uf->get_result();
while ($uf = $res_uf->fetch_assoc()) {
    $usuario_fuentes[] = $uf;
}
$stmt_uf->close();
// Si el usuario tiene exactamente una fuente asociada, se pre-seleccionará automáticamente
$auto_fuente = count($usuario_fuentes) === 1 ? $usuario_fuentes[0] : null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historias Clínicas - Sistema HC</title>
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
        }
    </style>
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header no-print">
                <h1>Historias Clínicas<?php if ($paciente_info): ?> -
                        <?php echo htmlspecialchars($paciente_info['nombre_completo']); ?><?php endif; ?>
                </h1>
                <p class="breadcrumb">
                    <?php if ($paciente_info): ?>
                        <a href="pacientes.php">Pacientes</a> / <a href="historias_clinicas.php">Historias Clínicas</a> /
                        <?php echo htmlspecialchars($paciente_info['tipo_documento'] . ' ' . $paciente_info['numero_documento']); ?>
                    <?php else: ?>
                        Inicio / Historias Clínicas
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> no-print">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if ($paciente_info): ?>
                <div class="card no-print">
                    <div class="card-body" style="background-color: #e7f3ff; padding: 15px;">
                        <strong>📋 Filtrando por paciente:</strong>
                        <?php echo htmlspecialchars($paciente_info['nombre_completo']); ?>
                        (<?php echo htmlspecialchars($paciente_info['tipo_documento'] . ' ' . $paciente_info['numero_documento']); ?>)
                        <a href="historias_clinicas.php" class="btn btn-sm btn-secondary" style="float: right;">Ver
                            Todos</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header flex-between">
                    <span>Búsqueda de Historias Clínicas</span>
                    <div class="no-print">
                        <?php if (!tienePermiso('auditor')): ?>
                            <button class="btn btn-primary btn-sm" onclick="abrirModalNueva()">+ Nueva HC</button>
                        <?php endif; ?>
                        <button class="btn btn-secondary btn-sm" onclick="window.print()">Imprimir</button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="no-print">
                        <?php if ($filtro_paciente > 0): ?>
                            <input type="hidden" name="paciente" value="<?php echo $filtro_paciente; ?>">
                        <?php endif; ?>
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <input type="text" name="buscar" placeholder="Buscar por HC, paciente o documento..."
                                    value="<?php echo htmlspecialchars($buscar); ?>">
                            </div>
                            <div class="form-group">
                                <select name="fuente">
                                    <option value="">Todas las fuentes</option>
                                    <?php
                                    $fuentes_filtro = $conexion->query("SELECT * FROM fuentes WHERE activo = 1 ORDER BY nombre");
                                    while ($fue = $fuentes_filtro->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $fue['id_fuente']; ?>" <?php echo $filtro_fuente == $fue['id_fuente'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fue['nombre']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <select name="estado">
                                    <option value="">Todos los estados</option>
                                    <option value="en_archivo" <?php echo $filtro_estado == 'en_archivo' ? 'selected' : ''; ?>>En Archivo</option>
                                    <option value="en_servicio" <?php echo $filtro_estado == 'en_servicio' ? 'selected' : ''; ?>>En Servicio</option>
                                    <option value="extramuro" <?php echo $filtro_estado == 'extramuro' ? 'selected' : ''; ?>>Extramuro</option>
                                    <option value="dada_de_baja" <?php echo $filtro_estado == 'dada_de_baja' ? 'selected' : ''; ?>>Dada de Baja</option>
                                    <option value="extraviada" <?php echo $filtro_estado == 'extraviada' ? 'selected' : ''; ?>>Extraviada</option>
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
                                    <th>HC</th>
                                    <th>Paciente</th>
                                    <th>Documento</th>
                                    <th>Servicio</th>
                                    <th>Estado</th>
                                    <th>Ubicación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($historias->num_rows > 0): ?>
                                    <?php while ($hc = $historias->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($hc['numero_hc']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($hc['paciente']); ?></td>
                                            <td><?php echo htmlspecialchars($hc['tipo_documento'] . ' ' . $hc['numero_documento']); ?>
                                            </td>
                                            <td>
                                                <span class="badge"
                                                    style="background-color: <?php echo htmlspecialchars($hc['color_fuente'] ?? '#667eea'); ?>; color: white;">
                                                    <?php echo htmlspecialchars($hc['codigo_fuente']); ?>
                                                </span>
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
                                            <td class="no-print">
                                                <a href="hc_detalle.php?id=<?php echo $hc['id_historia']; ?>"
                                                    class="btn btn-sm btn-primary">Ver</a>
                                                <?php if (!tienePermiso('auditor')): ?>
                                                    <button class="btn btn-sm btn-warning"
                                                        onclick="registrarMovimiento(<?php echo $hc['id_historia']; ?>)">Mover</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No se encontraron historias clínicas</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nueva HC -->
    <div id="modalNueva" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nueva Historia Clínica</h2>
                <button class="modal-close" onclick="cerrarModal('modalNueva')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear">
                <?php if ($filtro_paciente > 0 && $paciente_info): ?>
                    <input type="hidden" name="id_paciente" value="<?php echo $filtro_paciente; ?>">

                    <div class="form-group">
                        <label>Paciente</label>
                        <div style="padding: 10px; background-color: #e7f3ff; border-radius: 5px;">
                            <strong><?php echo htmlspecialchars($paciente_info['nombre_completo']); ?></strong><br>
                            <small><?php echo htmlspecialchars($paciente_info['tipo_documento'] . ' ' . $paciente_info['numero_documento']); ?></small>
                        </div>
                    </div>
                <?php else: ?>

                    <div class="form-group">
                        <label>Buscar Paciente *</label>
                        <input type="text" id="buscar_paciente" placeholder="Ingrese documento o nombre..."
                            autocomplete="off">
                        <div id="resultados_paciente"
                            style="border: 1px solid #ddd; max-height: 200px; overflow-y: auto; display: none; background: white; position: relative; z-index: 100;">
                        </div>
                        <input type="hidden" name="id_paciente" id="id_paciente" required>
                        <input type="hidden" id="paciente_dni" value="">
                        <div id="paciente_seleccionado"
                            style="margin-top: 10px; padding: 10px; background-color: #e7f3ff; border-radius: 5px; display: none;">
                        </div>
                        <small style="color: #dc3545;" id="error_paciente"></small>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Fuente *</label>
                        <select name="id_fuente" id="id_fuente" required onchange="alCambiarFuente()">
                            <option value="">Seleccione...</option>
                            <?php while ($fue = $fuentes->fetch_assoc()): ?>
                                <option value="<?php echo $fue['id_fuente']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($fue['nombre']); ?>"
                                    data-tiene-archivo="<?php echo $fue['tiene_archivo']; ?>"
                                    data-formato="<?php echo htmlspecialchars($fue['formato_numeracion'] ?? 'autoincremental'); ?>">
                                    <?php echo htmlspecialchars($fue['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Número de HC *
                            <small id="lbl_sugerencia"
                                style="color: #28a745; font-weight: normal; display: none;"></small>
                        </label>
                        <input type="text" name="numero_hc" id="numero_hc" required
                            placeholder="Se sugerirá automáticamente">
                    </div>
                </div>

                <div class="form-group">
                    <label>Ubicación Inicial *</label>
                    <select name="ubicacion_inicial" id="ubicacion_inicial" required>
                        <option value="">Seleccione primero una fuente...</option>
                        <option value="Archivo Central">Archivo Central</option>
                        <option value="archivo_servicio" id="opcion_archivo_servicio" disabled>
                            Archivo del Servicio (seleccione fuente primero)
                        </option>
                    </select>
                    <small>Seleccione dónde se archivará inicialmente la HC</small>
                </div>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" rows="3"></textarea>
                </div>

                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary"
                        onclick="cerrarModal('modalNueva')">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btn_guardar_hc">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fuentes asociadas al usuario logueado (para autoselección)
        const fuentesUsuario = <?php echo json_encode(array_column($usuario_fuentes, 'id_fuente')); ?>;
        <?php if ($auto_fuente): ?>
            const autoFuenteId = '<?php echo $auto_fuente['id_fuente']; ?>';
        <?php else: ?>
            const autoFuenteId = null;
        <?php endif; ?>

        function abrirModalNueva() {
            <?php if (!$filtro_paciente): ?>
                document.getElementById('buscar_paciente').value = '';
                document.getElementById('id_paciente').value = '';
                document.getElementById('paciente_dni').value = '';
                document.getElementById('paciente_seleccionado').style.display = 'none';
                document.getElementById('paciente_seleccionado').innerHTML = '';
                document.getElementById('resultados_paciente').style.display = 'none';
                document.getElementById('error_paciente').textContent = '';
            <?php endif; ?>
            document.getElementById('numero_hc').value = '';
            document.getElementById('lbl_sugerencia').style.display = 'none';

            document.getElementById('modalNueva').classList.add('active');

            // Autoseleccionar fuente del usuario si tienen exactamente una
            if (autoFuenteId) {
                const sel = document.getElementById('id_fuente');
                sel.value = autoFuenteId;
                alCambiarFuente();
            }
        }

        function cerrarModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function registrarMovimiento(id) {
            window.location.href = 'movimiento_nuevo.php?hc=' + id;
        }

        // ── Búsqueda de pacientes con teclado ──
        let indicePaciente = -1;
        let resultadosPaciente = [];

        document.getElementById('buscar_paciente').addEventListener('keydown', function (e) {
            const div = document.getElementById('resultados_paciente');
            const items = div.querySelectorAll('.resultado-item');
            if (items.length === 0) {
                if (e.key === 'Enter') e.preventDefault();
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                indicePaciente = Math.min(indicePaciente + 1, items.length - 1);
                actualizarResaltado(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                indicePaciente = Math.max(indicePaciente - 1, 0);
                actualizarResaltado(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (indicePaciente >= 0 && indicePaciente < items.length) {
                    items[indicePaciente].click();
                } else if (items.length === 1) {
                    items[0].click();
                }
            }
        });

        document.getElementById('buscar_paciente').addEventListener('input', function () {
            buscarPaciente();
        });

        function actualizarResaltado(items) {
            items.forEach((item, i) => {
                item.style.backgroundColor = (i === indicePaciente) ? '#e8e8e8' : '';
            });
            if (indicePaciente >= 0) {
                items[indicePaciente].scrollIntoView({ block: 'nearest' });
            }
        }

        let timeoutBusqueda;
        function buscarPaciente() {
            clearTimeout(timeoutBusqueda);
            indicePaciente = -1;
            const busqueda = document.getElementById('buscar_paciente').value;
            if (busqueda.length < 2) {
                document.getElementById('resultados_paciente').style.display = 'none';
                return;
            }
            timeoutBusqueda = setTimeout(() => {
                fetch('buscar_paciente_ajax.php?q=' + encodeURIComponent(busqueda))
                    .then(r => r.json())
                    .then(data => {
                        const div = document.getElementById('resultados_paciente');
                        if (data.length > 0) {
                            let html = '';
                            data.forEach(p => {
                                html += `<div class="resultado-item" style="padding:10px;cursor:pointer;border-bottom:1px solid #eee;"
                                    data-id="${p.id_paciente}"
                                    data-nombre="${escapeAttr(p.nombre_completo)}"
                                    data-doc="${escapeAttr(p.documento)}"
                                    data-dni="${escapeAttr(p.numero_documento ?? '')}"
                                    onclick="seleccionarPaciente(${p.id_paciente},'${escapeAttr(p.nombre_completo)}','${escapeAttr(p.documento)}','${escapeAttr(p.numero_documento ?? '')}')">
                                    <strong>${escapeHtml(p.nombre_completo)}</strong><br>
                                    <small>${escapeHtml(p.documento)}</small>
                                </div>`;
                            });
                            div.innerHTML = html;
                            div.style.display = 'block';
                        } else {
                            div.innerHTML = '<div style="padding:10px;">No se encontraron pacientes</div>';
                            div.style.display = 'block';
                        }
                    })
                    .catch(() => {
                        const div = document.getElementById('resultados_paciente');
                        div.innerHTML = '<div style="padding:10px;color:red;">Error al buscar</div>';
                        div.style.display = 'block';
                    });
            }, 300);
        }

        function escapeHtml(text) {
            const d = document.createElement('div');
            d.textContent = text || '';
            return d.innerHTML;
        }

        function escapeAttr(text) {
            return (text || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        function seleccionarPaciente(id, nombre, documento, dni) {
            document.getElementById('id_paciente').value = id;
            document.getElementById('paciente_dni').value = dni || '';
            document.getElementById('paciente_seleccionado').innerHTML =
                `<strong>✓ Paciente seleccionado:</strong><br>${escapeHtml(nombre)}<br><small>${escapeHtml(documento)}</small>`;
            document.getElementById('paciente_seleccionado').style.display = 'block';
            document.getElementById('resultados_paciente').style.display = 'none';
            document.getElementById('buscar_paciente').value = '';
            document.getElementById('error_paciente').textContent = '';
            indicePaciente = -1;
            // Sugerir número de HC con el paciente ya seleccionado
            sugerirNumeroHC();
        }

        // ── Cambio de fuente ──
        function alCambiarFuente() {
            actualizarOpcionesUbicacion();
            sugerirNumeroHC();
        }

        // ── Sugerencia de número de HC ──
        function sugerirNumeroHC() {
            const idFuente = document.getElementById('id_fuente').value;
            const idPaciente = document.getElementById('id_paciente').value;
            const campoHC = document.getElementById('numero_hc');
            const lbl = document.getElementById('lbl_sugerencia');

            if (!idFuente) return;

            fetch(`sugerir_numero_hc_ajax.php?id_fuente=${idFuente}&id_paciente=${idPaciente}`)
                .then(r => r.json())
                .then(data => {
                    if (data.sugerencia) {
                        campoHC.value = data.sugerencia;
                        lbl.textContent = '(sugerido automáticamente)';
                        lbl.style.display = 'inline';
                    } else {
                        lbl.style.display = 'none';
                    }
                })
                .catch(() => { });
        }

        // ── Actualizar opciones de ubicación según fuente seleccionada ──
        function actualizarOpcionesUbicacion() {
            const selectFuente = document.getElementById('id_fuente');
            const selectUbicacion = document.getElementById('ubicacion_inicial');
            const opcionArchivoServicio = document.getElementById('opcion_archivo_servicio');

            if (selectFuente.value) {
                const selectedOption = selectFuente.options[selectFuente.selectedIndex];
                const fuenteNombre = selectedOption.getAttribute('data-nombre');
                const tieneArchivo = selectedOption.getAttribute('data-tiene-archivo') == '1';

                opcionArchivoServicio.textContent = 'Archivo ' + fuenteNombre;
                opcionArchivoServicio.value = 'archivo_servicio';

                if (tieneArchivo) {
                    opcionArchivoServicio.disabled = false;
                    opcionArchivoServicio.style.display = 'block';
                    // Autoseleccionar el archivo del servicio si tiene archivo propio
                    selectUbicacion.value = 'archivo_servicio';
                } else {
                    opcionArchivoServicio.disabled = true;
                    opcionArchivoServicio.style.display = 'none';
                    if (selectUbicacion.value == 'archivo_servicio') {
                        selectUbicacion.value = 'Archivo Central';
                    }
                }
                selectUbicacion.disabled = false;
            } else {
                opcionArchivoServicio.textContent = 'Archivo del Servicio (seleccione fuente primero)';
                opcionArchivoServicio.value = 'archivo_servicio';
                opcionArchivoServicio.disabled = true;
                opcionArchivoServicio.style.display = 'block';
                selectUbicacion.value = '';
                selectUbicacion.disabled = true;
            }
        }

        // ── Validar formulario antes de enviar ──
        const formModal = document.querySelector('#modalNueva form[action=""]');
        if (formModal) {
            formModal.addEventListener('submit', function (e) {
                <?php if (!$filtro_paciente): ?>
                    const idPaciente = document.getElementById('id_paciente').value;
                    if (!idPaciente || idPaciente === '') {
                        e.preventDefault();
                        document.getElementById('error_paciente').textContent = '⚠ Debe seleccionar un paciente de la lista';
                        alert('Por favor, seleccione un paciente de la lista de búsqueda');
                        return false;
                    }
                <?php endif; ?>
            });
        }

        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>

</html>
