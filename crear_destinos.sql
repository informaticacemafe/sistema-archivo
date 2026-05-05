CREATE TABLE IF NOT EXISTS movimientos_destinos (
    id_destino INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL UNIQUE,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO movimientos_destinos (nombre) VALUES ('Archivo Central');
