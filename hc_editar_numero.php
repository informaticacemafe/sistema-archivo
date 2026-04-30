<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

// RESTRICCIÓN: Solo administradores
if (!tienePermiso('administrador')) {
    header('Location: dashboard.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$id_hc = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_hc == 0) {
    header('Location: historias_clinicas.php');
    exit();
}

// Obtener datos de la HC
$stmt = $conexion->prepare("
    SELECT h.*, 
           CONCAT(p.apellido, ', ', p.nombre) as paciente,
           p.numero_documento,
           f.nombre as fuente, f.id_fuente
    FROM historias_clinicas h
    INNER JOIN pacientes p ON h.id_paciente = p.id_paciente
    INNER JOIN fuentes f ON h.id_fuente = f.id_fuente
    WHERE h.id_historia = ?
");
$stmt->bind_param("i", $id_hc);
$stmt->execute();
$hc = $stmt->get_result()->fetch_assoc();

if (!$hc) {
    header('Location: historias_clinicas.php');
    exit();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
    $nuevo_numero_hc = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($_POST['numero_hc'])));
    $numero_anterior = $hc['numero_hc'];

    if (empty($nuevo_numero_hc)) {
        $mensaje = 'El número de HC no puede estar vacío';
        $tipo_mensaje = 'error';
    } else {
        // Verificar que no exista otro HC con ese número en la misma fuente
        $stmt_check = $conexion->prepare("SELECT id_historia FROM historias_clinicas WHERE numero_hc = ? AND id_fuente = ? AND id_historia != ?");
        $stmt_check->bind_param("sii", $nuevo_numero_hc, $hc['id_fuente'], $id_hc);
        $stmt_check->execute();
        $existe = $stmt_check->get_result();

        if ($existe->num_rows > 0) {
            $mensaje = 'Ya existe otra HC con ese número en la misma fuente';
            $tipo_mensaje = 'error';
        } else {
            // Actualizar número de HC
            $stmt_update = $conexion->prepare("UPDATE historias_clinicas SET numero_hc = ? WHERE id_historia = ?");
            $stmt_update->bind_param("si", $nuevo_numero_hc, $id_hc);

            if ($stmt_update->execute()) {
                // Registrar en log
                $resumen = "Se modificó HC {$numero_anterior} → {$nuevo_numero_hc} ({$hc['fuente']}) - Paciente: {$hc['paciente']}";
                $detalle_anterior = ['numero_hc' => $numero_anterior];
                $detalle_nuevo = ['numero_hc' => $nuevo_numero_hc];
                registrarLog('hc', $id_hc, 'EDITAR', $resumen, $detalle_anterior, $detalle_nuevo);
                registrarAuditoria('historias_clinicas', $id_hc, 'numero_hc', $numero_anterior, $nuevo_numero_hc);

                // Redirigir a detalle de HC con mensaje de éxito
                header('Location: hc_detalle.php?id=' . $id_hc . '&mensaje=numero_actualizado');
                exit();
            } else {
                $mensaje = 'Error al actualizar el número de HC';
                $tipo_mensaje = 'error';
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Número HC - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-header">
                <h1>Editar Número de Historia Clínica</h1>
                <p class="breadcrumb">
                    <a href="historias_clinicas.php">Historias Clínicas</a> /
                    <a href="hc_detalle.php?id=<?php echo $id_hc; ?>">Detalle HC</a> /
                    Editar Número
                </p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    Información de la Historia Clínica
                </div>
                <div class="card-body">
                    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <p><strong>Paciente:</strong>
                            <?php echo htmlspecialchars($hc['paciente']); ?>
                        </p>
                        <p><strong>Documento:</strong>
                            <?php echo htmlspecialchars($hc['numero_documento']); ?>
                        </p>
                        <p><strong>Fuente:</strong>
                            <?php echo htmlspecialchars($hc['fuente']); ?>
                        </p>
                        <p><strong>Número HC Actual:</strong> <span style="font-size: 18px; color: #667eea;"><strong>
                                    <?php echo htmlspecialchars($hc['numero_hc']); ?>
                                </strong></span></p>
                    </div>

                    <div
                        style="background-color: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                        <strong>⚠️ Advertencia:</strong> Esta operación modificará el número de la historia clínica.
                        Asegúrese de que el nuevo número sea correcto antes de confirmar.
                    </div>

                    <form method="POST" action="" onsubmit="return confirmarCambio()">
                        <input type="hidden" name="accion" value="actualizar">

                        <div class="form-group">
                            <label>Nuevo Número de HC *</label>
                            <input type="text" name="numero_hc"
                                value="<?php echo htmlspecialchars($hc['numero_hc']); ?>" required autofocus
                                style="font-size: 16px; font-weight: bold;"
                                onkeydown="return validarTeclaHC(event)"
                                oninput="sanitizarHC(this)"
                                onpaste="event.preventDefault(); this.value = (event.clipboardData || window.clipboardData).getData('text').replace(/[^A-Za-z0-9]/g, '').toUpperCase()">
                            <small>Ingrese el nuevo número de historia clínica</small>
                        </div>

                        <div class="form-group text-right">
                            <a href="hc_detalle.php?id=<?php echo $id_hc; ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-warning">💾 Actualizar Número HC</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmarCambio() {
            const nuevoNumero = document.querySelector('input[name="numero_hc"]').value;
            const numeroActual = '<?php echo addslashes($hc['numero_hc']); ?>';

            if (nuevoNumero === numeroActual) {
                alert('El número ingresado es igual al actual. No hay cambios para guardar.');
                return false;
            }

            return confirm('¿Está seguro de cambiar el número de HC de "' + numeroActual + '" a "' + nuevoNumero + '"?\n\nEsta acción quedará registrada en la auditoría.');
        }

        function validarTeclaHC(event) {
            const key = event.key;
            if (key === 'Backspace' || key === 'Delete' ||
                key.startsWith('Arrow') || key === 'Home' || key === 'End' ||
                key === 'Tab' || event.ctrlKey || event.metaKey) {
                return true;
            }
            return /^[A-Za-z0-9]$/.test(key);
        }

        function sanitizarHC(input) {
            input.value = input.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        }
    </script>
</body>

</html>