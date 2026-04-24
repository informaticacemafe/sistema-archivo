<?php
echo "<h1>Prueba de Conexión - Sistema HC</h1>";
echo "<hr>";

// Datos de conexión
$servidor = "10.1.1.1";
$usuario_db = "abcds";
$password_db = "ABCDE";
$nombre_db = "archivo";

echo "<h2>1. Verificando extensión MySQLi...</h2>";
if (extension_loaded('mysqli')) {
    echo "✓ Extensión MySQLi está disponible<br>";
} else {
    echo "✗ ERROR: Extensión MySQLi NO está disponible<br>";
    echo "Solución: Instale php-mysqli<br>";
    exit;
}

echo "<h2>2. Intentando conectar a MySQL...</h2>";
echo "Servidor: $servidor<br>";
echo "Usuario: $usuario_db<br>";
echo "Base de datos: $nombre_db<br><br>";

$conexion = new mysqli($servidor, $usuario_db, $password_db, $nombre_db);

if ($conexion->connect_error) {
    echo "✗ ERROR DE CONEXIÓN: " . $conexion->connect_error . "<br>";
    echo "Código de error: " . $conexion->connect_errno . "<br><br>";
    
    echo "<strong>Posibles soluciones:</strong><br>";
    echo "- Verificar que MySQL esté corriendo<br>";
    echo "- Verificar las credenciales en conexion.php<br>";
    echo "- Verificar que el usuario tenga permisos<br>";
    echo "- Verificar el firewall/IP permitidas<br>";
    exit;
}

echo "✓ Conexión exitosa a MySQL<br>";
echo "Versión MySQL: " . $conexion->server_info . "<br>";

echo "<h2>3. Verificando charset UTF-8...</h2>";
if ($conexion->set_charset("utf8")) {
    echo "✓ Charset UTF-8 configurado correctamente<br>";
} else {
    echo "✗ Advertencia: No se pudo configurar UTF-8<br>";
}

echo "<h2>4. Verificando tablas en la base de datos...</h2>";
$result = $conexion->query("SHOW TABLES");
if ($result) {
    $tablas = array();
    while ($row = $result->fetch_array()) {
        $tablas[] = $row[0];
    }
    
    if (count($tablas) > 0) {
        echo "✓ Se encontraron " . count($tablas) . " tablas:<br>";
        echo "<ul>";
        foreach ($tablas as $tabla) {
            echo "<li>$tabla</li>";
        }
        echo "</ul>";
    } else {
        echo "✗ No hay tablas en la base de datos<br>";
        echo "Solución: Ejecute el script db_schema.sql<br>";
    }
} else {
    echo "✗ Error al consultar tablas: " . $conexion->error . "<br>";
}

echo "<h2>5. Verificando tabla usuarios...</h2>";
$result = $conexion->query("SELECT COUNT(*) as total FROM usuarios");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✓ Tabla usuarios existe<br>";
    echo "Total de usuarios: " . $row['total'] . "<br><br>";
    
    if ($row['total'] == 0) {
        echo "<strong>⚠ No hay usuarios en la base de datos</strong><br>";
        echo "Vamos a crear el usuario admin...<br><br>";
        
        // Crear usuario admin con contraseña simple
        $username = 'admin';
        $password_plain = 'admin123';
        $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
        $nombre = 'Administrador';
        $apellido = 'Sistema';
        $rol = 'administrador';
        
        $stmt = $conexion->prepare("INSERT INTO usuarios (username, password, nombre, apellido, rol, activo) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssss", $username, $password_hash, $nombre, $apellido, $rol);
        
        if ($stmt->execute()) {
            echo "✓ Usuario 'admin' creado exitosamente<br>";
            echo "Password: $password_plain<br>";
            echo "Hash generado: $password_hash<br>";
        } else {
            echo "✗ Error al crear usuario: " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "<h3>Usuarios existentes:</h3>";
        $result = $conexion->query("SELECT id_usuario, username, nombre, apellido, rol, activo FROM usuarios");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Nombre</th><th>Rol</th><th>Activo</th></tr>";
        while ($user = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $user['id_usuario'] . "</td>";
            echo "<td><strong>" . $user['username'] . "</strong></td>";
            echo "<td>" . $user['nombre'] . " " . $user['apellido'] . "</td>";
            echo "<td>" . $user['rol'] . "</td>";
            echo "<td>" . ($user['activo'] ? 'Sí' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "✗ Error: Tabla usuarios no existe o hay un problema<br>";
    echo "Error: " . $conexion->error . "<br>";
    echo "Solución: Ejecute el script db_schema.sql<br>";
}

echo "<h2>6. Prueba de password_hash...</h2>";
$test_password = "admin123";
$test_hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "Password de prueba: $test_password<br>";
echo "Hash generado: $test_hash<br>";
if (password_verify($test_password, $test_hash)) {
    echo "✓ password_verify() funciona correctamente<br>";
} else {
    echo "✗ ERROR: password_verify() no funciona<br>";
}

echo "<h2>7. Probar login manual...</h2>";
echo "<form method='POST' action=''>
    <label>Usuario: <input type='text' name='test_user' value='admin'></label><br>
    <label>Password: <input type='password' name='test_pass' value='admin123'></label><br>
    <button type='submit' name='test_login'>Probar Login</button>
</form>";

if (isset($_POST['test_login'])) {
    $test_user = $_POST['test_user'];
    $test_pass = $_POST['test_pass'];
    
    echo "<h3>Resultado del test de login:</h3>";
    echo "Usuario ingresado: $test_user<br>";
    echo "Password ingresado: $test_pass<br><br>";
    
    $stmt = $conexion->prepare("SELECT id_usuario, username, password, rol FROM usuarios WHERE username = ?");
    $stmt->bind_param("s", $test_user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "✓ Usuario encontrado en la BD<br>";
        echo "Hash en BD: " . $user['password'] . "<br><br>";
        
        if (password_verify($test_pass, $user['password'])) {
            echo "<strong style='color: green;'>✓✓✓ LOGIN EXITOSO ✓✓✓</strong><br>";
            echo "El usuario '$test_user' puede iniciar sesión correctamente<br>";
        } else {
            echo "<strong style='color: red;'>✗ PASSWORD INCORRECTO</strong><br>";
            echo "El hash no coincide con la contraseña ingresada<br>";
            echo "<br><strong>Solución: Actualizar el password del usuario</strong><br>";
            echo "<form method='POST'>
                <input type='hidden' name='reset_pass' value='1'>
                <input type='hidden' name='user_id' value='" . $user['id_usuario'] . "'>
                <button type='submit'>Resetear password a 'admin123'</button>
            </form>";
        }
    } else {
        echo "✗ Usuario NO encontrado en la base de datos<br>";
    }
    $stmt->close();
}

if (isset($_POST['reset_pass'])) {
    $user_id = $_POST['user_id'];
    $new_pass = 'admin123';
    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
    
    $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $new_hash, $user_id);
    
    if ($stmt->execute()) {
        echo "<br><strong style='color: green;'>✓ Password actualizado a: $new_pass</strong><br>";
        echo "Ahora puedes hacer login con esta contraseña<br>";
    } else {
        echo "<br><strong style='color: red;'>✗ Error al actualizar: " . $stmt->error . "</strong><br>";
    }
    $stmt->close();
}

$conexion->close();

echo "<hr>";
echo "<h2>✓ Diagnóstico completo</h2>";
echo "<p>Si todo está en verde arriba, la conexión funciona correctamente.</p>";
echo "<p><a href='index.php'>Ir al sistema de login</a></p>";
?>
