<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

if (!tienePermiso('administrador')) {
    header('Location: dashboard.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] == 'crear') {
        $nombre = trim($_POST['nombre']);
        $codigo = trim(strtoupper($_POST['codigo']));
        $color = trim($_POST['color']);

        // Verificar si existe
        $stmt = $conexion->prepare("SELECT id_fuente FROM fuentes WHERE codigo = ?");
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = 'Ya existe una fuente con ese código';
            $tipo_mensaje = 'error';
        } else {
            $tiene_archivo = isset($_POST['tiene_archivo']) ? 1 : 0;
            $formato_numeracion = in_array($_POST['formato_numeracion'] ?? '', ['autoincremental', 'letra_autoincremental', 'dni']) ? $_POST['formato_numeracion'] : 'autoincremental';

            $stmt = $conexion->prepare("INSERT INTO fuentes (nombre, codigo, color, tiene_archivo, formato_numeracion) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $nombre, $codigo, $color, $tiene_archivo, $formato_numeracion);

            if ($stmt->execute()) {
                $id_nuevo = $conexion->insert_id;
                $resumen = "Se creó fuente: {$codigo} - {$nombre}";
                $detalle_nuevo = ['codigo' => $codigo, 'nombre' => $nombre, 'color' => $color, 'tiene_archivo' => $tiene_archivo, 'formato_numeracion' => $formato_numeracion];
                registrarLog('fuente', $id_nuevo, 'CREAR', $resumen, null, $detalle_nuevo);
                $mensaje = 'Fuente creada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al crear fuente: ' . $conexion->error;
                $tipo_mensaje = 'error';
            }
        }
        $stmt->close();
    }

    if ($_POST['accion'] == 'editar') {
        $id_fuente = $_POST['id_fuente'];
        $nombre = trim($_POST['nombre']);
        $codigo = trim(strtoupper($_POST['codigo']));
        $color = trim($_POST['color']);
        $tiene_archivo = isset($_POST['tiene_archivo']) ? 1 : 0;
        $formato_numeracion = in_array($_POST['formato_numeracion'] ?? '', ['autoincremental', 'letra_autoincremental', 'dni']) ? $_POST['formato_numeracion'] : 'autoincremental';

        // Verificar si el código ya existe en otra fuente
        $stmt = $conexion->prepare("SELECT id_fuente FROM fuentes WHERE codigo = ? AND id_fuente != ?");
        $stmt->bind_param("si", $codigo, $id_fuente);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = 'Ya existe otra fuente con ese código';
            $tipo_mensaje = 'error';
        } else {
            // Obtener valores anteriores
            $stmt = $conexion->prepare("SELECT nombre, codigo, color, tiene_archivo, formato_numeracion FROM fuentes WHERE id_fuente = ?");
            $stmt->bind_param("i", $id_fuente);
            $stmt->execute();
            $result_anterior = $stmt->get_result();
            if ($result_anterior->num_rows > 0) {
                $anterior = $result_anterior->fetch_assoc();
            } else {
                $anterior = ['nombre' => '', 'codigo' => '', 'color' => '', 'tiene_archivo' => 1, 'formato_numeracion' => 'autoincremental'];
            }
            $stmt->close();

            $stmt = $conexion->prepare("UPDATE fuentes SET nombre = ?, codigo = ?, color = ?, tiene_archivo = ?, formato_numeracion = ? WHERE id_fuente = ?");
            $stmt->bind_param("sssisi", $nombre, $codigo, $color, $tiene_archivo, $formato_numeracion, $id_fuente);

            if ($stmt->execute()) {
                $detalle_anterior = [];
                $detalle_nuevo = [];
                $hay_cambios = false;
                if ($anterior['nombre'] != $nombre) { $detalle_anterior['nombre'] = $anterior['nombre']; $detalle_nuevo['nombre'] = $nombre; $hay_cambios = true; }
                if ($anterior['codigo'] != $codigo) { $detalle_anterior['codigo'] = $anterior['codigo']; $detalle_nuevo['codigo'] = $codigo; $hay_cambios = true; }
                if ($anterior['color'] != $color) { $detalle_anterior['color'] = $anterior['color']; $detalle_nuevo['color'] = $color; $hay_cambios = true; }
                if (isset($anterior['tiene_archivo']) && $anterior['tiene_archivo'] != $tiene_archivo) { $detalle_anterior['tiene_archivo'] = $anterior['tiene_archivo']; $detalle_nuevo['tiene_archivo'] = $tiene_archivo; $hay_cambios = true; }
                if (isset($anterior['formato_numeracion']) && $anterior['formato_numeracion'] != $formato_numeracion) { $detalle_anterior['formato_numeracion'] = $anterior['formato_numeracion']; $detalle_nuevo['formato_numeracion'] = $formato_numeracion; $hay_cambios = true; }
                
                if ($hay_cambios) {
                    $resumen = "Se modificó fuente: {$codigo} - {$nombre}";
                    registrarLog('fuente', $id_fuente, 'EDITAR', $resumen, $detalle_anterior, $detalle_nuevo);
                }

                $mensaje = 'Fuente actualizada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar fuente';
                $tipo_mensaje = 'error';
            }
        }
        $stmt->close();
    }

    if ($_POST['accion'] == 'cambiar_estado') {
        $id_fuente = $_POST['id_fuente'];
        $nuevo_estado = $_POST['activo'] == '1' ? 0 : 1;

        $stmt = $conexion->prepare("UPDATE fuentes SET activo = ? WHERE id_fuente = ?");
        $stmt->bind_param("ii", $nuevo_estado, $id_fuente);

        if ($stmt->execute()) {
            $estado_texto = $nuevo_estado ? 'activo' : 'inactivo';
            $resumen = "Se cambió estado de fuente (ID {$id_fuente}) a: {$estado_texto}";
            $detalle_anterior = ['activo' => $_POST['activo']];
            $detalle_nuevo = ['activo' => $nuevo_estado];
            registrarLog('fuente', $id_fuente, 'EDITAR', $resumen, $detalle_anterior, $detalle_nuevo);
            $mensaje = 'Estado actualizado a ' . $estado_texto;
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al actualizar estado';
            $tipo_mensaje = 'error';
        }
        $stmt->close();
    }
}

// Obtener fuentes con estadísticas
$query = "
    SELECT f.*,
           COUNT(DISTINCT h.id_historia) as total_hc,
           SUM(CASE WHEN h.estado = 'en_archivo' THEN 1 ELSE 0 END) as hc_en_archivo,
           SUM(CASE WHEN h.estado = 'en_servicio' THEN 1 ELSE 0 END) as hc_en_servicio,
           SUM(CASE WHEN h.estado = 'extramuro' THEN 1 ELSE 0 END) as hc_extramuro
    FROM fuentes f
    LEFT JOIN historias_clinicas h ON f.id_fuente = h.id_fuente
    GROUP BY f.id_fuente
    ORDER BY f.activo DESC, f.nombre
";
$fuentes = $conexion->query($query);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Fuentes - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header no-print">
                <h1>Gestión de Fuentes de Numeración</h1>
                <p class="breadcrumb">Inicio / Administración / Fuentes</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> no-print">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header flex-between">
                    <span>Fuentes de la Institución</span>
                    <button class="btn btn-primary btn-sm no-print" onclick="abrirModalNuevo()">+ Nueva Fuente</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Archivo Físico</th>
                                    <th>Formato Nro. HC</th>
                                    <th>Total HC</th>
                                    <th>En Archivo</th>
                                    <th>En Servicio</th>
                                    <th>Extramuro</th>
                                    <th>Estado</th>
                                    <th class="no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($fue = $fuentes->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="badge"
                                                style="background-color: <?php echo htmlspecialchars($fue['color']); ?>; color: white;">
                                                <?php echo htmlspecialchars($fue['codigo']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($fue['nombre']); ?></td>
                                        <td class="text-center">
                                            <?php if ($fue['tiene_archivo']): ?>
                                                <span class="badge badge-success" title="Tiene archivo físico propio">Sí</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary"
                                                    title="No tiene archivo físico propio">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $fmt_labels = [
                                                'autoincremental' => 'Num. Autoincremental',
                                                'letra_autoincremental' => 'Letra + Num.',
                                                'dni' => 'DNI del Paciente',
                                            ];
                                            $fmt = $fue['formato_numeracion'] ?? 'autoincremental';
                                            echo '<span class="badge badge-info">' . htmlspecialchars($fmt_labels[$fmt] ?? $fmt) . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo number_format($fue['total_hc']); ?></td>
                                        <td><?php echo number_format($fue['hc_en_archivo']); ?></td>
                                        <td><?php echo number_format($fue['hc_en_servicio']); ?></td>
                                        <td><?php echo number_format($fue['hc_extramuro']); ?></td>
                                        <td>
                                            <?php if ($fue['activo']): ?>
                                                <span class="badge badge-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="no-print">
                                            <button class="btn btn-sm btn-secondary"
                                                onclick="editarFuente(<?php echo $fue['id_fuente']; ?>, '<?php echo htmlspecialchars($fue['nombre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fue['codigo'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fue['color'], ENT_QUOTES); ?>', <?php echo $fue['tiene_archivo']; ?>, '<?php echo htmlspecialchars($fue['formato_numeracion'] ?? 'autoincremental', ENT_QUOTES); ?>')">
                                                Editar
                                            </button>
                                            <form method="POST" style="display: inline;"
                                                onsubmit="return confirm('¿Está seguro?')">
                                                <input type="hidden" name="accion" value="cambiar_estado">
                                                <input type="hidden" name="id_fuente"
                                                    value="<?php echo $fue['id_fuente']; ?>">
                                                <input type="hidden" name="activo" value="<?php echo $fue['activo']; ?>">
                                                <button type="submit"
                                                    class="btn btn-sm <?php echo $fue['activo'] ? 'btn-warning' : 'btn-success'; ?>">
                                                    <?php echo $fue['activo'] ? 'Desactivar' : 'Activar'; ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Información sobre Fuentes de Numeración
                </div>
                <div class="card-body">
                    <h3>¿Qué son las Fuentes de Numeración?</h3>
                    <p>Las fuentes representan los diferentes orígenes o sistemas de numeración de historias clínicas.
                        Cada fuente puede tener su propia secuencia de números.</p>

                    <h3>Consideraciones importantes:</h3>
                    <ul>
                        <li><strong>Código único:</strong> Cada fuente debe tener un código identificador único (ej: CM,
                            PED, CG)</li>
                        <li><strong>Numeración independiente:</strong> Cada fuente puede tener su propia numeración de
                            historias clínicas</li>
                        <li><strong>Desactivación:</strong> Las fuentes se pueden desactivar pero no eliminar para
                            mantener la integridad histórica</li>
                        <li><strong>Fuentes inactivas:</strong> No aparecerán en formularios pero sus HC seguirán siendo
                            accesibles</li>
                        <li><strong>Color identificatorio:</strong> Cada fuente tiene un color que facilita la
                            identificación visual en el sistema</li>
                    </ul>

                    <h3>Ejemplos de uso:</h3>
                    <ul>
                        <li><strong>Por servicio:</strong> Clínica Médica, Cirugía, Pediatría, etc.</li>
                        <li><strong>Por tipo:</strong> Ambulatorio, Internación, Emergencias</li>
                        <li><strong>Por sede:</strong> Sede Central, Filial Norte, Filial Sur</li>
                        <li><strong>Histórico:</strong> Sistema Antiguo, Sistema Nuevo</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Fuente -->
    <div id="modalNuevo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nueva Fuente de Numeración</h2>
                <button class="modal-close" onclick="cerrarModal('modalNuevo')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear">

                <div class="form-group">
                    <label>Nombre de la Fuente *</label>
                    <input type="text" name="nombre" required placeholder="Ej: Clínica Médica">
                </div>

                <div class="form-group">
                    <label>Código *</label>
                    <input type="text" name="codigo" required maxlength="20" placeholder="Ej: CM"
                        style="text-transform: uppercase;">
                    <small>Código corto único para identificar la fuente (2-5 caracteres)</small>
                </div>

                <div class="form-group">
                    <label>Color de la Fuente *</label>
                    <input type="color" name="color" value="#667eea" required style="height: 40px;">
                    <small>Este color se usará para identificar la fuente en todo el sistema</small>
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center;">
                        <input type="checkbox" name="tiene_archivo" value="1" checked
                            style="width:auto; margin-right:10px;">
                        Tiene Archivo Físico Propio
                    </label>
                    <small>Marque esta casilla si este servicio/fuente tiene un espacio físico para archivar historias
                        clínicas.</small>
                </div>

                <div class="form-group">
                    <label>Formato de Numeración de HC *</label>
                    <select name="formato_numeracion" required>
                        <option value="autoincremental">Número Autoincremental (1, 2, 3...)</option>
                        <option value="letra_autoincremental">Letra + Autoincremental (A1, A2... B1, B2...)</option>
                        <option value="dni">DNI del Paciente</option>
                    </select>
                    <small>Define cómo se generarán las sugerencias de número de HC para esta fuente.</small>
                </div>

                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary"
                        onclick="cerrarModal('modalNuevo')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Fuente</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Fuente -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Fuente</h2>
                <button class="modal-close" onclick="cerrarModal('modalEditar')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_fuente" id="edit_id_fuente">

                <div class="form-group">
                    <label>Nombre de la Fuente *</label>
                    <input type="text" name="nombre" id="edit_nombre" required>
                </div>

                <div class="form-group">
                    <label>Código *</label>
                    <input type="text" name="codigo" id="edit_codigo" required maxlength="20"
                        style="text-transform: uppercase;">
                    <small>Código corto único para identificar la fuente</small>
                </div>

                <div class="form-group">
                    <label>Color de la Fuente *</label>
                    <input type="color" name="color" id="edit_color" required style="height: 40px;">
                    <small>Este color se usará para identificar la fuente en todo el sistema</small>
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center;">
                        <input type="checkbox" name="tiene_archivo" id="edit_tiene_archivo" value="1"
                            style="width:auto; margin-right:10px;">
                        Tiene Archivo Físico Propio
                    </label>
                    <small>Marque esta casilla si este servicio/fuente tiene un espacio físico para archivar historias
                        clínicas.</small>
                </div>

                <div class="form-group">
                    <label>Formato de Numeración de HC *</label>
                    <select name="formato_numeracion" id="edit_formato_numeracion" required>
                        <option value="autoincremental">Número Autoincremental (1, 2, 3...)</option>
                        <option value="letra_autoincremental">Letra + Autoincremental (A1, A2... B1, B2...)</option>
                        <option value="dni">DNI del Paciente</option>
                    </select>
                    <small>Define cómo se generarán las sugerencias de número de HC para esta fuente.</small>
                </div>

                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary"
                        onclick="cerrarModal('modalEditar')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalNuevo() {
            document.getElementById('modalNuevo').classList.add('active');
        }

        function cerrarModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function editarFuente(id, nombre, codigo, color, tieneArchivo, formatoNumeracion) {
            document.getElementById('edit_id_fuente').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_codigo').value = codigo;
            document.getElementById('edit_color').value = color || '#667eea';
            document.getElementById('edit_tiene_archivo').checked = (tieneArchivo == 1);
            document.getElementById('edit_formato_numeracion').value = formatoNumeracion || 'autoincremental';
            document.getElementById('modalEditar').classList.add('active');
        }

        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>

</html>