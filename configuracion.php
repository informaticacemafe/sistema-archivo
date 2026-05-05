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

        $stmt = $conexion->prepare("SELECT id_destino FROM movimientos_destinos WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = 'Ya existe un destino con ese nombre';
            $tipo_mensaje = 'error';
        } else {
            $stmt = $conexion->prepare("INSERT INTO movimientos_destinos (nombre) VALUES (?)");
            $stmt->bind_param("s", $nombre);

            if ($stmt->execute()) {
                $id_nuevo = $conexion->insert_id;
                $resumen = "Se creó destino: {$nombre}";
                $detalle_nuevo = ['nombre' => $nombre];
                registrarLog('destino', $id_nuevo, 'CREAR', $resumen, null, $detalle_nuevo);
                registrarAuditoria('movimientos_destinos', $id_nuevo, 'nombre', '', $nombre, 'INSERT');
                $mensaje = 'Destino creado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al crear destino: ' . $conexion->error;
                $tipo_mensaje = 'error';
            }
        }
        $stmt->close();
    }

    if ($_POST['accion'] == 'editar') {
        $id_destino = intval($_POST['id_destino']);
        $nombre = trim($_POST['nombre']);

        $stmt = $conexion->prepare("SELECT id_destino FROM movimientos_destinos WHERE nombre = ? AND id_destino != ?");
        $stmt->bind_param("si", $nombre, $id_destino);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = 'Ya existe otro destino con ese nombre';
            $tipo_mensaje = 'error';
        } else {
            $stmt = $conexion->prepare("SELECT nombre FROM movimientos_destinos WHERE id_destino = ?");
            $stmt->bind_param("i", $id_destino);
            $stmt->execute();
            $nombre_anterior = $stmt->get_result()->fetch_assoc()['nombre'];
            $stmt->close();

            $stmt = $conexion->prepare("UPDATE movimientos_destinos SET nombre = ? WHERE id_destino = ?");
            $stmt->bind_param("si", $nombre, $id_destino);

            if ($stmt->execute()) {
                if ($nombre_anterior != $nombre) {
                    $resumen = "Se modificó destino: {$nombre_anterior} → {$nombre}";
                    $detalle_anterior = ['nombre' => $nombre_anterior];
                    $detalle_nuevo = ['nombre' => $nombre];
                    registrarLog('destino', $id_destino, 'EDITAR', $resumen, $detalle_anterior, $detalle_nuevo);
                    registrarAuditoria('movimientos_destinos', $id_destino, 'nombre', $nombre_anterior, $nombre);
                }
                $mensaje = 'Destino actualizado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al actualizar destino';
                $tipo_mensaje = 'error';
            }
        }
        $stmt->close();
    }

    if ($_POST['accion'] == 'cambiar_estado') {
        $id_destino = intval($_POST['id_destino']);
        $nuevo_estado = $_POST['activo'] == '1' ? 0 : 1;

        $stmt = $conexion->prepare("UPDATE movimientos_destinos SET activo = ? WHERE id_destino = ?");
        $stmt->bind_param("ii", $nuevo_estado, $id_destino);

        if ($stmt->execute()) {
            $estado_texto = $nuevo_estado ? 'activo' : 'inactivo';
            $resumen = "Se cambió estado de destino (ID {$id_destino}) a: {$estado_texto}";
            $detalle_anterior = ['activo' => $_POST['activo']];
            $detalle_nuevo = ['activo' => $nuevo_estado];
            registrarLog('destino', $id_destino, 'EDITAR', $resumen, $detalle_anterior, $detalle_nuevo);
            registrarAuditoria('movimientos_destinos', $id_destino, 'activo', $_POST['activo'], $nuevo_estado);
            $mensaje = 'Estado actualizado a ' . $estado_texto;
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al actualizar estado';
            $tipo_mensaje = 'error';
        }
        $stmt->close();
    }
}

$destinos = $conexion->query("SELECT * FROM movimientos_destinos ORDER BY activo DESC, nombre");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header no-print">
                <h1>Configuración del Sistema</h1>
                <p class="breadcrumb">Inicio / Administración / Configuración</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> no-print">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header flex-between">
                    <span>Destinos de Movimientos</span>
                    <button class="btn btn-primary btn-sm no-print" onclick="abrirModalNuevo()">+ Nuevo Destino</button>
                </div>
                <div class="card-body">
                    <p style="color: #666; margin-bottom: 15px;">
                        Estos destinos aparecerán como opciones en el dropdown de "Ubicación Destino" al registrar un movimiento.
                        Solo los destinos <strong>activos</strong> se muestran.
                    </p>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th class="no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($destinos->num_rows > 0): ?>
                                    <?php while ($d = $destinos->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $d['id_destino']; ?></td>
                                            <td><?php echo htmlspecialchars($d['nombre']); ?></td>
                                            <td>
                                                <?php if ($d['activo']): ?>
                                                    <span class="badge badge-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="no-print">
                                                <button class="btn btn-sm btn-secondary"
                                                    onclick="editarDestino(<?php echo $d['id_destino']; ?>, '<?php echo htmlspecialchars($d['nombre'], ENT_QUOTES); ?>')">
                                                    Editar
                                                </button>
                                                <form method="POST" style="display: inline;"
                                                    onsubmit="return confirm('¿Está seguro?')">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="id_destino" value="<?php echo $d['id_destino']; ?>">
                                                    <input type="hidden" name="activo" value="<?php echo $d['activo']; ?>">
                                                    <button type="submit"
                                                        class="btn btn-sm <?php echo $d['activo'] ? 'btn-warning' : 'btn-success'; ?>">
                                                        <?php echo $d['activo'] ? 'Desactivar' : 'Activar'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No hay destinos configurados</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Nuevo Destino -->
    <div id="modalNuevo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nuevo Destino</h2>
                <button class="modal-close" onclick="cerrarModal('modalNuevo')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear">

                <div class="form-group">
                    <label>Nombre del Destino *</label>
                    <input type="text" name="nombre" required placeholder="Ej: Archivo Central">
                    <small>Este nombre aparecerá en el dropdown de ubicación destino al registrar movimientos.</small>
                </div>

                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalNuevo')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Destino</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Destino -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Destino</h2>
                <button class="modal-close" onclick="cerrarModal('modalEditar')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_destino" id="edit_id_destino">

                <div class="form-group">
                    <label>Nombre del Destino *</label>
                    <input type="text" name="nombre" id="edit_nombre" required>
                </div>

                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalEditar')">Cancelar</button>
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

        function editarDestino(id, nombre) {
            document.getElementById('edit_id_destino').value = id;
            document.getElementById('edit_nombre').value = nombre;
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
