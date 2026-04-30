-- Tabla para almacenar HC eliminadas (auditoría de bajas)
CREATE TABLE IF NOT EXISTS historias_clinicas_eliminadas (
    id_eliminacion INT AUTO_INCREMENT PRIMARY KEY,
    id_historia_original INT NOT NULL,
    id_paciente INT NOT NULL,
    id_fuente INT NOT NULL,
    numero_hc VARCHAR(50) NOT NULL,
    estado VARCHAR(20) NOT NULL,
    ubicacion_actual VARCHAR(200),
    fecha_creacion_hc DATETIME,
    observaciones TEXT,
    usuario_elimino INT NOT NULL,
    fecha_eliminacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha_eliminacion (fecha_eliminacion),
    INDEX idx_paciente (id_paciente),
    INDEX idx_fuente (id_fuente),
    INDEX idx_usuario_elimino (usuario_elimino)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabla para almacenar movimientos de HC eliminadas
CREATE TABLE IF NOT EXISTS movimientos_hc_eliminadas (
    id_movimiento_eliminado INT AUTO_INCREMENT PRIMARY KEY,
    id_historia_original INT NOT NULL,
    id_movimiento_original INT NOT NULL,
    fecha_hora DATETIME NOT NULL,
    tipo_movimiento VARCHAR(50) NOT NULL,
    ubicacion_origen VARCHAR(200),
    ubicacion_destino VARCHAR(200) NOT NULL,
    usuario_id INT NOT NULL,
    observaciones TEXT,
    fecha_eliminacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_historia_original (id_historia_original),
    INDEX idx_fecha_eliminacion (fecha_eliminacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
