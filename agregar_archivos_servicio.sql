-- Script de migración para agregar archivos por servicio
-- Fecha: 2026-01-30

-- Agregar campo tiene_archivo a la tabla fuentes
ALTER TABLE fuentes 
ADD COLUMN tiene_archivo TINYINT(1) DEFAULT 1 
COMMENT 'Indica si el servicio tiene archivo propio (1=Sí, 0=No)' 
AFTER activo;

-- Por defecto, todos los servicios activos tienen archivo
UPDATE fuentes SET tiene_archivo = 1 WHERE activo = 1;

-- Opcional: Si algún servicio NO tiene archivo, marcarlo manualmente
-- Ejemplo:
-- UPDATE fuentes SET tiene_archivo = 0 WHERE codigo = 'XXX';
