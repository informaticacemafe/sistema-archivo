-- Tabla de Log de Actividades (Sistema de auditoría narrativa)
-- Reemplaza el registro campo-por-campo de auditoria por un log por evento
-- con datos de contexto completos (paciente, HC, movimiento, etc.)

CREATE TABLE IF NOT EXISTS log_actividades (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT NOT NULL,
    tipo_entidad VARCHAR(30) NOT NULL COMMENT 'paciente, hc, movimiento, fuente',
    id_entidad INT NOT NULL,
    accion VARCHAR(10) NOT NULL COMMENT 'CREAR, EDITAR, ELIMINAR',
    resumen TEXT NOT NULL COMMENT 'Texto legible descriptivo del evento',
    detalle_anterior TEXT COMMENT 'JSON con valores previos (EDITAR/ELIMINAR)',
    detalle_nuevo TEXT COMMENT 'JSON con valores nuevos (CREAR/EDITAR)',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario),
    INDEX idx_entidad (tipo_entidad, id_entidad),
    INDEX idx_fecha (fecha_hora),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
