-- Script de migración para eliminar el campo usuario_responsable
-- Este script debe ejecutarse UNA SOLA VEZ en la base de datos existente

USE archivo;

-- Paso 1: Eliminar la clave foránea
ALTER TABLE historias_clinicas 
DROP FOREIGN KEY historias_clinicas_ibfk_3;

-- Paso 2: Eliminar la columna usuario_responsable
ALTER TABLE historias_clinicas 
DROP COLUMN usuario_responsable;

-- Paso 3: Verificar los cambios
DESCRIBE historias_clinicas;

-- Mostrar la estructura de la tabla actualizada
SHOW CREATE TABLE historias_clinicas;
