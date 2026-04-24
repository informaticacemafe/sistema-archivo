-- Migración: Asociación Usuario-Fuentes y Formato de Numeración de HC
-- Ejecutar en la base de datos 'archivo'

USE archivo;

-- PASO 1: Agregar formato_numeracion a la tabla fuentes
-- opciones: 'autoincremental', 'letra_autoincremental', 'dni'
ALTER TABLE fuentes 
ADD COLUMN formato_numeracion ENUM('autoincremental', 'letra_autoincremental', 'dni') 
NOT NULL DEFAULT 'autoincremental' 
AFTER tiene_archivo;

-- PASO 2: Crear tabla de relación Usuario-Fuentes (muchos a muchos, opcional)
CREATE TABLE IF NOT EXISTS usuarios_fuentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_fuente INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_fuente) REFERENCES fuentes(id_fuente) ON DELETE CASCADE,
    UNIQUE KEY uq_usuario_fuente (id_usuario, id_fuente),
    INDEX idx_usuario (id_usuario),
    INDEX idx_fuente (id_fuente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Verificar cambios
SELECT 'Migración completada exitosamente' AS mensaje;
SELECT nombre, codigo, formato_numeracion FROM fuentes;
SELECT COUNT(*) as total_en_usuarios_fuentes FROM usuarios_fuentes;
