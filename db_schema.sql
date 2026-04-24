-- Base de datos para Sistema de Gestión de Historias Clínicas en Papel
-- MySQL 5.5 Compatible

CREATE DATABASE IF NOT EXISTS archivo CHARACTER SET utf8 COLLATE utf8_general_ci;
USE archivo;

-- Tabla de Usuarios
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    rol ENUM('administrador', 'archivo', 'servicio', 'auditor') NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabla de Fuentes (Origen de numeración de HC)
CREATE TABLE fuentes (
    id_fuente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#667eea',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabla de Pacientes
CREATE TABLE pacientes (
    id_paciente INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento ENUM('DNI', 'LC', 'LE', 'Pasaporte', 'Otro') NOT NULL,
    numero_documento VARCHAR(20) NOT NULL,
    nombre VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    apellido VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo ENUM('M', 'F', 'X') NOT NULL,
    telefono VARCHAR(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_documento (tipo_documento, numero_documento),
    INDEX idx_apellido_nombre (apellido, nombre),
    INDEX idx_documento (numero_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabla de Historias Clínicas
CREATE TABLE historias_clinicas (
    id_historia INT AUTO_INCREMENT PRIMARY KEY,
    id_paciente INT NOT NULL,
    id_fuente INT NOT NULL,
    numero_hc VARCHAR(50) NOT NULL,
    estado ENUM('en_archivo', 'en_servicio', 'extramuro', 'dada_de_baja', 'extraviada') DEFAULT 'en_archivo',
    ubicacion_actual VARCHAR(200) DEFAULT 'Archivo Central',
    fecha_ultimo_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_creacion DATETIME,
    observaciones TEXT,
    UNIQUE KEY unique_hc_fuente (numero_hc, id_fuente),
    FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente),
    FOREIGN KEY (id_fuente) REFERENCES fuentes(id_fuente),
    INDEX idx_numero_hc (numero_hc),
    INDEX idx_estado (estado),
    INDEX idx_paciente (id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabla de Movimientos
CREATE TABLE movimientos (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_historia INT NOT NULL,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento ENUM('ingreso_archivo', 'salida_a_servicio', 'devolucion_a_archivo', 
                         'salida_extramuro', 'ingreso_desde_extramuro', 'traslado_interno',
                         'dado_de_baja', 'reportado_extraviado', 'recuperado') NOT NULL,
    ubicacion_origen VARCHAR(200),
    ubicacion_destino VARCHAR(200) NOT NULL,
    usuario_id INT NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (id_historia) REFERENCES historias_clinicas(id_historia),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario),
    INDEX idx_historia (id_historia),
    INDEX idx_fecha (fecha_hora),
    INDEX idx_tipo (tipo_movimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabla de Auditoría
CREATE TABLE auditoria (
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
    tabla VARCHAR(50) NOT NULL,
    id_registro INT NOT NULL,
    campo VARCHAR(50) NOT NULL,
    valor_anterior TEXT,
    valor_nuevo TEXT,
    usuario_id INT NOT NULL,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accion ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario),
    INDEX idx_tabla_registro (tabla, id_registro),
    INDEX idx_fecha (fecha_hora),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabla de Sesiones (Log de accesos)
CREATE TABLE sesiones (
    id_sesion INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_logout TIMESTAMP NULL DEFAULT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha_login (fecha_login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insertar usuario administrador por defecto (password: admin123)
INSERT INTO usuarios (username, password, nombre, apellido, rol) 
VALUES ('admin', '$2y$10$8K1p/VLmVDFLqP5K5.FqLOxQZJW8fF5.KmGqVXXyJQGZVZYGZxJ0K', 'ADMINISTRADOR', 'SISTEMA', 'administrador');

-- Insertar algunas fuentes de ejemplo
INSERT INTO fuentes (nombre, codigo) VALUES 
('Clínica Médica', 'CM'),
('Cirugía General', 'CG'),
('Pediatría', 'PED'),
('Traumatología', 'TRA'),
('Cardiología', 'CAR'),
('Ginecología', 'GIN');
