-- Script para cambiar concepto de "Servicio" a "Fuente/Origen"
-- Y actualizar formato de campos apellido, nombre y telefono
-- Compatible con MySQL 5.5

USE archivo;

-- PASO 1: Eliminar foreign keys existentes
ALTER TABLE historias_clinicas DROP FOREIGN KEY historias_clinicas_ibfk_2;

-- PASO 2: Renombrar tabla servicios a fuentes
RENAME TABLE servicios TO fuentes;

-- PASO 3: Renombrar columna id_servicio a id_fuente en tabla fuentes
ALTER TABLE fuentes CHANGE COLUMN id_servicio id_fuente INT AUTO_INCREMENT;

-- PASO 4: Renombrar columna id_servicio a id_fuente en tabla historias_clinicas
ALTER TABLE historias_clinicas CHANGE COLUMN id_servicio id_fuente INT NOT NULL;

-- PASO 5: Recrear foreign key con nuevo nombre
ALTER TABLE historias_clinicas 
    ADD CONSTRAINT historias_clinicas_ibfk_2 
    FOREIGN KEY (id_fuente) REFERENCES fuentes(id_fuente);

-- PASO 6: Actualizar formatos de campos en tabla pacientes
ALTER TABLE pacientes 
    MODIFY COLUMN apellido VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    MODIFY COLUMN nombre VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    MODIFY COLUMN telefono VARCHAR(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;

-- PASO 7: Convertir datos existentes a mayúsculas
UPDATE pacientes SET apellido = UPPER(apellido);
UPDATE pacientes SET nombre = UPPER(nombre);

-- PASO 8: Convertir usuario admin a mayúsculas
UPDATE usuarios SET nombre = UPPER(nombre), apellido = UPPER(apellido) WHERE username = 'admin';

-- Verificar cambios
SELECT 'Migración completada exitosamente' AS mensaje;
SELECT COUNT(*) as total_fuentes FROM fuentes;
SELECT COUNT(*) as total_pacientes FROM pacientes;
SELECT CONCAT(nombre, ' ', apellido) as ejemplo_paciente FROM pacientes LIMIT 1;

-- Verificar foreign keys
SELECT 
    CONSTRAINT_NAME, 
    COLUMN_NAME, 
    REFERENCED_TABLE_NAME, 
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'archivo' 
  AND TABLE_NAME = 'historias_clinicas'
  AND CONSTRAINT_NAME = 'historias_clinicas_ibfk_2';
