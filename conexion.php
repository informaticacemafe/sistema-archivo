<?php
$servidor = "10.1.96.103";
$usuario_db = "gestion_";
$password_db = "GESTION_77";
$nombre_db = "archivo";

// Configurar el conjunto de caracteres para UTF-8 (acentos y eñes)
$opciones = array(
    MYSQLI_OPT_CONNECT_TIMEOUT => 10,
    MYSQLI_INIT_COMMAND => "SET NAMES utf8"
);

// Crear conexión con manejo de errores
$conexion = new mysqli($servidor, $usuario_db, $password_db, $nombre_db);

// Verificar conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Establecer charset UTF-8 para caracteres especiales
if (!$conexion->set_charset("utf8")) {
    die("Error al establecer el conjunto de caracteres: " . $conexion->error);
}

// Función para cerrar conexión
function cerrar_conexion() {
    global $conexion;
    if ($conexion) {
        $conexion->close();
    }
}

// Registrar función para cerrar conexión al finalizar el script
register_shutdown_function('cerrar_conexion');
?>

<?php
/*
ESTRUCTURA SQL PARA MYSQL 5.5 - Base de datos: dam

Ejecuta este SQL para crear la tabla optimizada:
*/

/*
-- Crear base de datos con charset UTF-8
CREATE DATABASE IF NOT EXISTS dam 
CHARACTER SET utf8 
COLLATE utf8_spanish_ci;

USE dam;

-- Crear tabla solicitudes
CREATE TABLE IF NOT EXISTS solicitudes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre_apellido VARCHAR(255) NOT NULL,
    fecha_solicitud DATE NOT NULL,
    dni VARCHAR(20) NOT NULL,
    edad TINYINT(3) UNSIGNED NOT NULL,
    domicilio TEXT NOT NULL,
    antecedentes_medicos TEXT,
    enfermedad_actual TEXT,
    impresion_diagnostica TEXT,
    plan_terapeutico TEXT,
    conformidad_requerido TEXT,
    cantidad_sesiones TEXT,
    observaciones_auditoria TEXT,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME NOT NULL,
    estado ENUM('pendiente', 'procesada', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
    usuario_creacion VARCHAR(100) DEFAULT NULL,
    
    PRIMARY KEY (id),
    INDEX idx_dni (dni),
    INDEX idx_fecha_solicitud (fecha_solicitud),
    INDEX idx_estado (estado),
    INDEX idx_fecha_creacion (fecha_creacion)
) 
ENGINE=InnoDB 
DEFAULT CHARSET=utf8 
COLLATE=utf8_spanish_ci
AUTO_INCREMENT=1;

-- Si ya tienes la tabla creada sin el campo antecedentes_medicos, ejecuta esto:
-- ALTER TABLE solicitudes ADD COLUMN antecedentes_medicos TEXT AFTER domicilio;
*/
?>