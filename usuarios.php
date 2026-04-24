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
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $rol = $_POST['rol'];

        // Verificar si existe
        $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $mensaje = 'El nombre de usuario ya existe';
            $tipo_mensaje = 'error';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (username, password, nombre, apellido, rol) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $password_hash, $nombre, $apellido, $rol);

            if ($stmt->execute()) {
                $id_nuevo = $conexion->insert_id;
                // Guardar fuentes asociadas (si las hay)
                if (!empty($_POST['fuentes_asociadas']) && is_array($_POST['fuentes_asociadas'])) {
                    $stmt_f = $conexion->prepare("INSERT IGNORE INTO usuarios_fuentes (id_usuario, id_fuente) VALUES (?, ?)");
                    foreach ($_POST['fuentes_asociadas'] as $id_fuente) {
                        $id_fuente = intval($id_fuente);
                        $stmt_f->bind_param("ii", $id_nuevo, $id_fuente);
                        $stmt_f->execute();
                    }
                    $stmt_f->close();
                }
                $mensaje = 'Usuario creado exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al crear usuario';
                $tipo_mensaje = 'error';
            }
        }
        $stmt->close();
    }

    if ($_POST['accion'] == 'editar_fuentes') {
        $id_usuario = intval($_POST['id_usuario']);
        // Eliminar asociaciones previas
        $stmt = $conexion->prepare("DELETE FROM usuarios_fuentes WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $stmt->close();
        // Insertar nuevas
        if (!empty($_POST['fuentes_asociadas']) && is_array($_POST['fuentes_asociadas'])) {
            $stmt_f = $conexion->prepare("INSERT IGNORE INTO usuarios_fuentes (id_usuario, id_fuente) VALUES (?, ?)");
            foreach ($_POST['fuentes_asociadas'] as $id_fuente) {
                $id_fuente = intval($id_fuente);
                $stmt_f->bind_param("ii", $id_usuario, $id_fuente);
                $stmt_f->execute();
            }
            $stmt_f->close();
        }
        $mensaje = 'Fuentes asociadas actualizadas correctamente';
        $tipo_mensaje = 'success';
    }

    if ($_POST['accion'] == 'cambiar_estado') {
        $id_usuario = $_POST['id_usuario'];
        $nuevo_estado = $_POST['activo'] == '1' ? 0 : 1;

        $stmt = $conexion->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?");
        $stmt->bind_param("ii", $nuevo_estado, $id_usuario);

        if ($stmt->execute()) {
            $mensaje = 'Estado actualizado exitosamente';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al actualizar estado';
            $tipo_mensaje = 'error';
        }
        $stmt->close();
    }

    if ($_POST['accion'] == 'cambiar_password') {
        $id_usuario = $_POST['id_usuario'];
        $nueva_password = $_POST['nueva_password'];

        $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
        $stmt->bind_param("si", $password_hash, $id_usuario);

        if ($stmt->execute()) {
            $mensaje = 'Contraseña actualizada exitosamente';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'Error al actualizar contraseña';
            $tipo_mensaje = 'error';
        }
        $stmt->close();
    }
}

// Obtener usuarios con sus fuentes asociadas
$usuarios = $conexion->query("SELECT * FROM usuarios ORDER BY apellido, nombre");

// Obtener todas las fuentes activas
$fuentes_raw = $conexion->query("SELECT id_fuente, nombre, codigo, color FROM fuentes WHERE activo = 1 ORDER BY nombre");
$todas_fuentes = [];
if ($fuentes_raw) {
    while ($f = $fuentes_raw->fetch_assoc()) {
        $todas_fuentes[] = $f;
    }
}

// Obtener mapa usuario->fuentes
$uf_raw = $conexion->query("SELECT uf.id_usuario, uf.id_fuente, f.nombre as fuente_nombre, f.codigo, f.color FROM usuarios_fuentes uf INNER JOIN fuentes f ON uf.id_fuente = f.id_fuente ORDER BY f.nombre");
$fuentes_por_usuario = [];
if ($uf_raw) {
    while ($uf = $uf_raw->fetch_assoc()) {
        $fuentes_por_usuario[$uf['id_usuario']][] = $uf;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header">
                <h1>Gestión de Usuarios</h1>
                <p class="breadcrumb">Inicio / Administración / Usuarios</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header flex-between">
                    <span>Usuarios del Sistema</span>
                    <button class="btn btn-primary btn-sm" onclick="abrirModalNuevo()">+ Nuevo Usuario</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nombre Completo</th>
                                    <th>Rol</th>
                                    <th>Fuentes Asociadas</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $lista_usuarios = [];
                                while ($usr = $usuarios->fetch_assoc()) {
                                    $lista_usuarios[] = $usr;
                                }
                                foreach ($lista_usuarios as $usr):
                                    $id_usr = $usr['id_usuario'];
                                    $fuentes_usr = isset($fuentes_por_usuario[$id_usr]) ? $fuentes_por_usuario[$id_usr] : [];
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($usr['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($usr['nombre'] . ' ' . $usr['apellido']); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo ucfirst($usr['rol']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (empty($fuentes_usr)): ?>
                                                <span class="badge badge-secondary">Sin fuente específica</span>
                                            <?php else: ?>
                                                <?php foreach ($fuentes_usr as $fu): ?>
                                                    <span class="badge"
                                                        style="background-color: <?php echo htmlspecialchars($fu['color']); ?>; color: white; margin: 1px;">
                                                        <?php echo htmlspecialchars($fu['codigo']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($usr['activo']): ?>
                                                <span class="badge badge-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($usr['fecha_creacion'])); ?></td>
                                        <td>
                                            <?php
                                            $fuentes_ids_usr = [];
                                            foreach ($fuentes_usr as $f_item) {
                                                $fuentes_ids_usr[] = intval($f_item['id_fuente']);
                                            }
                                            $fuentes_ids_json = json_encode($fuentes_ids_usr);
                                            ?>
                                            <button class="btn btn-sm btn-info"
                                                onclick="abrirModalFuentes(<?php echo $usr['id_usuario']; ?>, '<?php echo htmlspecialchars($usr['username'], ENT_QUOTES); ?>', <?php echo htmlspecialchars($fuentes_ids_json, ENT_QUOTES); ?>)">
                                                Fuentes
                                            </button>
                                            <button class="btn btn-sm btn-warning"
                                                onclick="cambiarPassword(<?php echo $usr['id_usuario']; ?>, '<?php echo htmlspecialchars($usr['username']); ?>')">
                                                Clave
                                            </button>
                                            <?php if ($usr['id_usuario'] != $_SESSION['usuario_id']): ?>
                                                <form method="POST" style="display: inline;"
                                                    onsubmit="return confirm('¿Está seguro?')">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="id_usuario"
                                                        value="<?php echo $usr['id_usuario']; ?>">
                                                    <input type="hidden" name="activo" value="<?php echo $usr['activo']; ?>">
                                                    <button type="submit"
                                                        class="btn btn-sm <?php echo $usr['activo'] ? 'btn-danger' : 'btn-success'; ?>">
                                                        <?php echo $usr['activo'] ? 'Desactivar' : 'Activar'; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Usuario -->
    <div id="modalNuevo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nuevo Usuario</h2>
                <button class="modal-close" onclick="cerrarModal('modalNuevo')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear">

                <div class="form-group">
                    <label>Nombre de Usuario *</label>
                    <input type="text" name="username" required>
                </div>

                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" required minlength="6">
                    <small>Mínimo 6 caracteres</small>
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

                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol" required>
                        <option value="">Seleccione...</option>
                        <option value="administrador">Administrador</option>
                        <option value="archivo">Archivo</option>
                        <option value="servicio">Servicio</option>
                        <option value="auditor">Auditor (Solo Lectura)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fuentes Asociadas <small>(opcional – dejar vacío para acceso a todas las
                            fuentes)</small></label>
                    <div
                        style="border: 1px solid #ddd; border-radius: 5px; padding: 10px; max-height: 180px; overflow-y: auto;">
                        <?php foreach ($todas_fuentes as $tf): ?>
                            <label
                                style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; cursor: pointer;">
                                <input type="checkbox" name="fuentes_asociadas[]" value="<?php echo $tf['id_fuente']; ?>"
                                    style="width: auto;">
                                <span class="badge"
                                    style="background-color: <?php echo htmlspecialchars($tf['color']); ?>; color: white;"><?php echo htmlspecialchars($tf['codigo']); ?></span>
                                <?php echo htmlspecialchars($tf['nombre']); ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($todas_fuentes)): ?>
                            <small style="color: #666;">No hay fuentes activas en el sistema.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary"
                        onclick="cerrarModal('modalNuevo')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Fuentes del Usuario -->
    <div id="modalFuentes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Fuentes de: <span id="fuentes_usuario_nombre"></span></h2>
                <button class="modal-close" onclick="cerrarModal('modalFuentes')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar_fuentes">
                <input type="hidden" name="id_usuario" id="fuentes_usuario_id">

                <p style="color: #555; margin-bottom: 12px;">
                    Seleccione las fuentes a las que este usuario tiene acceso.
                    <strong>Si no selecciona ninguna, el usuario podrá operar con cualquier fuente.</strong>
                </p>

                <div style="border: 1px solid #ddd; border-radius: 5px; padding: 10px; max-height: 250px; overflow-y: auto;"
                    id="lista_fuentes_modal">
                    <?php foreach ($todas_fuentes as $tf): ?>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;"
                            class="fuente-check-item">
                            <input type="checkbox" name="fuentes_asociadas[]" value="<?php echo $tf['id_fuente']; ?>"
                                class="fuente-checkbox" data-id="<?php echo $tf['id_fuente']; ?>" style="width: auto;">
                            <span class="badge"
                                style="background-color: <?php echo htmlspecialchars($tf['color']); ?>; color: white;"><?php echo htmlspecialchars($tf['codigo']); ?></span>
                            <?php echo htmlspecialchars($tf['nombre']); ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($todas_fuentes)): ?>
                        <small style="color: #666;">No hay fuentes activas en el sistema.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group text-right" style="margin-top: 15px;">
                    <button type="button" class="btn btn-secondary"
                        onclick="cerrarModal('modalFuentes')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar Fuentes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Cambiar Password -->
    <div id="modalPassword" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cambiar Contraseña</h2>
                <button class="modal-close" onclick="cerrarModal('modalPassword')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="cambiar_password">
                <input type="hidden" name="id_usuario" id="password_usuario_id">

                <p>Usuario: <strong id="password_usuario_nombre"></strong></p>

                <div class="form-group">
                    <label>Nueva Contraseña *</label>
                    <input type="password" name="nueva_password" required minlength="6">
                    <small>Mínimo 6 caracteres</small>
                </div>

                <div class="form-group text-right">
                    <button type="button" class="btn btn-secondary"
                        onclick="cerrarModal('modalPassword')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Cambiar Contraseña</button>
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

        function abrirModalFuentes(idUsuario, username, fuentesIds) {
            document.getElementById('fuentes_usuario_id').value = idUsuario;
            document.getElementById('fuentes_usuario_nombre').textContent = username;

            // Desmarcar todos
            document.querySelectorAll('.fuente-checkbox').forEach(cb => cb.checked = false);

            // Marcar los que corresponden
            const ids = fuentesIds;
            ids.forEach(id => {
                const cb = document.querySelector('.fuente-checkbox[data-id="' + id + '"]');
                if (cb) cb.checked = true;
            });

            document.getElementById('modalFuentes').classList.add('active');
        }

        function cambiarPassword(id, username) {
            document.getElementById('password_usuario_id').value = id;
            document.getElementById('password_usuario_nombre').textContent = username;
            document.getElementById('modalPassword').classList.add('active');
        }

        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>

</html>