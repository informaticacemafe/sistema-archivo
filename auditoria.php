<?php
session_start();
require_once 'conexion.php';
require_once 'auth.php';

if (!tienePermiso('administrador')) {
    header('Location: dashboard.php');
    exit();
}

// Filtros
$tabla = isset($_GET['tabla']) ? $_GET['tabla'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

// Construir query
$query = "
    SELECT a.*, u.username, u.nombre, u.apellido
    FROM auditoria a
    INNER JOIN usuarios u ON a.usuario_id = u.id_usuario
    WHERE DATE(a.fecha_hora) BETWEEN ? AND ?
";

$params = array($fecha_desde, $fecha_hasta);
$types = "ss";

if (!empty($tabla)) {
    $query .= " AND a.tabla = ?";
    $params[] = $tabla;
    $types .= "s";
}

if ($usuario > 0) {
    $query .= " AND a.usuario_id = ?";
    $params[] = $usuario;
    $types .= "i";
}

if (!empty($accion)) {
    $query .= " AND a.accion = ?";
    $params[] = $accion;
    $types .= "s";
}

$query .= " ORDER BY a.fecha_hora DESC LIMIT 500";

$stmt = $conexion->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$registros = $stmt->get_result();

// Obtener usuarios para filtro
$usuarios = $conexion->query("SELECT id_usuario, username, nombre, apellido FROM usuarios ORDER BY username");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría - Sistema HC</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .auditoria-insert { background-color: #d4edda; }
        .auditoria-update { background-color: #fff3cd; }
        .auditoria-delete { background-color: #f8d7da; }
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
                <h1>Log de Auditoría</h1>
                <p class="breadcrumb">Inicio / Administración / Auditoría</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Filtros de Auditoría
                </div>
                <div class="card-body no-print">
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
                                <label>Tabla</label>
                                <select name="tabla">
                                    <option value="">Todas</option>
                                    <option value="pacientes" <?php echo $tabla == 'pacientes' ? 'selected' : ''; ?>>Pacientes</option>
                                    <option value="historias_clinicas" <?php echo $tabla == 'historias_clinicas' ? 'selected' : ''; ?>>Historias Clínicas</option>
                                    <option value="usuarios" <?php echo $tabla == 'usuarios' ? 'selected' : ''; ?>>Usuarios</option>
                                    <option value="servicios" <?php echo $tabla == 'servicios' ? 'selected' : ''; ?>>Servicios</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Usuario</label>
                                <select name="usuario">
                                    <option value="0">Todos</option>
                                    <?php while($usr = $usuarios->fetch_assoc()): ?>
                                    <option value="<?php echo $usr['id_usuario']; ?>" <?php echo $usuario == $usr['id_usuario'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($usr['username']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Acción</label>
                                <select name="accion">
                                    <option value="">Todas</option>
                                    <option value="INSERT" <?php echo $accion == 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                                    <option value="UPDATE" <?php echo $accion == 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                                    <option value="DELETE" <?php echo $accion == 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <button type="button" class="btn btn-secondary" onclick="window.print()">Imprimir</button>
                            <button type="button" class="btn btn-secondary" onclick="exportarAuditoria()">Exportar a Excel</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Registros de Auditoría (Últimos 500)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla_auditoria">
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Tabla</th>
                                    <th>ID Registro</th>
                                    <th>Campo</th>
                                    <th>Valor Anterior</th>
                                    <th>Valor Nuevo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($registros->num_rows > 0): ?>
                                    <?php while($reg = $registros->fetch_assoc()): ?>
                                    <tr class="auditoria-<?php echo strtolower($reg['accion']); ?>">
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($reg['fecha_hora'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($reg['username']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($reg['nombre'] . ' ' . $reg['apellido']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $reg['accion'] == 'INSERT' ? 'success' : 
                                                    ($reg['accion'] == 'UPDATE' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo $reg['accion']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($reg['tabla']); ?></td>
                                        <td><?php echo $reg['id_registro']; ?></td>
                                        <td>
                                            <?php 
                                            // Traducir nombres de campos
                                            $campos_traducidos = array(
                                                'nombre' => 'Nombre',
                                                'apellido' => 'Apellido',
                                                'fecha_nacimiento' => 'Fecha de Nacimiento',
                                                'sexo' => 'Sexo',
                                                'telefono' => 'Teléfono',
                                                'tipo_documento' => 'Tipo de Documento',
                                                'numero_documento' => 'Número de Documento',
                                                'estado' => 'Estado',
                                                'ubicacion_actual' => 'Ubicación Actual',
                                                'numero_hc' => 'Número HC',
                                                'codigo' => 'Código',
                                                'color' => 'Color',
                                                'activo' => 'Activo',
                                                'username' => 'Usuario',
                                                'rol' => 'Rol',
                                                'password' => 'Contraseña',
                                                'CREACION' => 'Creación de registro'
                                            );
                                            
                                            $campo_mostrar = isset($campos_traducidos[$reg['campo']]) ? 
                                                           $campos_traducidos[$reg['campo']] : 
                                                           ucfirst(str_replace('_', ' ', $reg['campo']));
                                            
                                            echo htmlspecialchars($campo_mostrar);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($reg['accion'] == 'INSERT') {
                                                echo '<em style="color: #999;">-</em>';
                                            } else {
                                                $valor = htmlspecialchars(substr($reg['valor_anterior'], 0, 100));
                                                // Si es password, ocultar
                                                if ($reg['campo'] == 'password') {
                                                    echo '<em>••••••••</em>';
                                                } elseif ($reg['campo'] == 'activo') {
                                                    echo $reg['valor_anterior'] == '1' ? 'Activo' : 'Inactivo';
                                                } else {
                                                    echo empty($valor) ? '<em style="color: #999;">vacío</em>' : $valor;
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($reg['accion'] == 'DELETE') {
                                                echo '<em style="color: #999;">-</em>';
                                            } else {
                                                $valor = htmlspecialchars(substr($reg['valor_nuevo'], 0, 100));
                                                // Si es password, ocultar
                                                if ($reg['campo'] == 'password') {
                                                    echo '<em>••••••••</em>';
                                                } elseif ($reg['campo'] == 'activo') {
                                                    echo $reg['valor_nuevo'] == '1' ? 'Activo' : 'Inactivo';
                                                } else {
                                                    echo empty($valor) ? '<em style="color: #999;">vacío</em>' : $valor;
                                                }
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No se encontraron registros de auditoría</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-20"><strong>Total de registros mostrados: <?php echo $registros->num_rows; ?></strong></p>
                    <p><small>Se muestran máximo los últimos 500 registros según los filtros aplicados.</small></p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Información sobre Auditoría
                </div>
                <div class="card-body">
                    <h3>¿Qué se audita?</h3>
                    <ul>
                        <li><strong>INSERT:</strong> Creación de nuevos registros (pacientes, HC, usuarios, etc.)</li>
                        <li><strong>UPDATE:</strong> Modificaciones de datos existentes</li>
                        <li><strong>DELETE:</strong> Eliminación de registros (actualmente no se permiten eliminaciones)</li>
                    </ul>
                    
                    <h3>Características del Log</h3>
                    <ul>
                        <li>✓ Registro automático e inalterable</li>
                        <li>✓ Incluye usuario, fecha/hora exacta y valores modificados</li>
                        <li>✓ Permite rastrear cualquier cambio en el sistema</li>
                        <li>✓ No puede ser editado ni eliminado por usuarios</li>
                    </ul>
                    
                    <h3>Colores de Referencia</h3>
                    <p>
                        <span class="badge badge-success">INSERT</span> = Registro nuevo creado<br>
                        <span class="badge badge-warning">UPDATE</span> = Registro modificado<br>
                        <span class="badge badge-danger">DELETE</span> = Registro eliminado
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function exportarAuditoria() {
            const tabla = document.getElementById('tabla_auditoria');
            let html = '<html><head><meta charset="utf-8"></head><body>';
            html += '<h2>Log de Auditoría</h2>';
            html += '<p>Generado: <?php echo date("d/m/Y H:i"); ?></p>';
            html += '<p>Período: <?php echo date("d/m/Y", strtotime($fecha_desde)) . " - " . date("d/m/Y", strtotime($fecha_hasta)); ?></p>';
            html += tabla.outerHTML;
            html += '</body></html>';
            
            const blob = new Blob(['\ufeff', html], {
                type: 'application/vnd.ms-excel'
            });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'auditoria_<?php echo date("Ymd_His"); ?>.xls';
            link.click();
        }
    </script>
</body>
</html>
