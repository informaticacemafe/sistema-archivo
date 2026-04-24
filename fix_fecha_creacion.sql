-- Script de migración para corregir el campo fecha_creacion en historias_clinicas
-- Este script debe ejecutarse UNA SOLA VEZ en la base de datos existente
-- Compatible con MySQL 5.5

USE archivo;

-- Paso 1: Modificar la definición de la columna a DATETIME (MySQL 5.5 solo permite un TIMESTAMP con CURRENT_TIMESTAMP)
ALTER TABLE historias_clinicas 
MODIFY COLUMN fecha_creacion DATETIME;

-- Paso 2: Actualizar registros existentes que tienen fecha_creacion en '0000-00-00 00:00:00' o NULL
-- Usamos la fecha del primer movimiento registrado, o la fecha actual si no hay movimientos

UPDATE historias_clinicas hc
LEFT JOIN (
    SELECT id_historia, MIN(fecha_hora) as primera_fecha
    FROM movimientos
    GROUP BY id_historia
) m ON hc.id_historia = m.id_historia
SET hc.fecha_creacion = COALESCE(m.primera_fecha, NOW())
WHERE hc.fecha_creacion = '0000-00-00 00:00:00' 
   OR hc.fecha_creacion IS NULL
   OR hc.fecha_creacion < '1970-01-01 00:00:00';

-- Paso 3: Verificar los cambios
SELECT 
    COUNT(*) as total_registros,
    SUM(CASE WHEN fecha_creacion IS NULL OR fecha_creacion = '0000-00-00 00:00:00' THEN 1 ELSE 0 END) as con_fecha_invalida,
    SUM(CASE WHEN fecha_creacion >= '1970-01-01 00:00:00' THEN 1 ELSE 0 END) as con_fecha_valida
FROM historias_clinicas;

-- Mostrar algunas historias clínicas con sus fechas actualizadas
SELECT 
    id_historia,
    numero_hc,
    fecha_creacion,
    fecha_ultimo_movimiento
FROM historias_clinicas
ORDER BY id_historia
LIMIT 10;
