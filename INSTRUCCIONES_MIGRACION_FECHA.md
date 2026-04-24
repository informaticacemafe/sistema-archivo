# Instrucciones para Aplicar la Corrección de fecha_creacion

## Resumen de Cambios

Se ha corregido el problema donde el campo `fecha_creacion` quedaba en blanco al crear nuevas historias clínicas.

### Archivos Modificados

1. **[db_schema.sql](file:///c:/Users/Sectorial/Documents/Sistemas/Historias%20clinicas%20en%20papel/proyecto%20claude/db_schema.sql)** - Cambiado `fecha_creacion` de TIMESTAMP a DATETIME
2. **[historias_clinicas.php](file:///c:/Users/Sectorial/Documents/Sistemas/Historias%20clinicas%20en%20papel/proyecto%20claude/historias_clinicas.php)** - Agregado `fecha_creacion` con NOW() en el INSERT
3. **[fix_fecha_creacion.sql](file:///c:/Users/Sectorial/Documents/Sistemas/Historias%20clinicas%20en%20papel/proyecto%20claude/fix_fecha_creacion.sql)** - Script de migración para la base de datos existente

## Solución Implementada

> [!IMPORTANT]
> **Compatibilidad con MySQL 5.5**: MySQL 5.5 tiene una limitación donde solo puede haber **una columna TIMESTAMP con `CURRENT_TIMESTAMP`** por tabla. Como la columna `fecha_ultimo_movimiento` ya usa este valor, se cambió `fecha_creacion` a tipo `DATETIME` y se establece explícitamente en el código PHP con `NOW()`.

## Pasos para Aplicar la Corrección

### 1. Ejecutar el Script de Migración

> [!IMPORTANT]
> Este script debe ejecutarse **UNA SOLA VEZ** en la base de datos de producción.

**Opción A: Desde línea de comandos**
```bash
mysql -u usuario -p archivo < fix_fecha_creacion.sql
```

**Opción B: Desde phpMyAdmin o similar**
1. Abrir phpMyAdmin
2. Seleccionar la base de datos `archivo`
3. Ir a la pestaña "SQL"
4. Copiar y pegar el contenido de `fix_fecha_creacion.sql`
5. Ejecutar

### 2. Verificar los Cambios

El script mostrará automáticamente:
- Total de registros en la tabla
- Cantidad de registros con fecha inválida (debería ser 0 después de la migración)
- Cantidad de registros con fecha válida
- Muestra de 10 registros con sus fechas actualizadas

### 3. Probar la Funcionalidad

1. Crear una nueva historia clínica desde el sistema web
2. Verificar que en la vista de detalle aparezca la fecha de creación correcta
3. Verificar en la base de datos:
   ```sql
   SELECT id_historia, numero_hc, fecha_creacion 
   FROM historias_clinicas 
   ORDER BY id_historia DESC 
   LIMIT 5;
   ```

## Qué Hace el Script de Migración

1. **Modifica la columna**: Cambia el tipo de `TIMESTAMP` a `DATETIME`
2. **Actualiza registros existentes**: Para las historias clínicas que tienen fecha cero o NULL:
   - Usa la fecha del primer movimiento registrado (si existe)
   - Usa la fecha actual con `NOW()` si no hay movimientos registrados
3. **Verifica los cambios**: Muestra estadísticas y ejemplos de los registros actualizados

## Comportamiento Después de la Corrección

- **Nuevas historias clínicas**: El campo `fecha_creacion` se llenará automáticamente con `NOW()` desde el código PHP
- **Historias existentes**: Tendrán fechas válidas basadas en su primer movimiento
- **Compatible con MySQL 5.5**: La solución respeta la limitación de una sola columna TIMESTAMP con CURRENT_TIMESTAMP

## Notas Técnicas

- El archivo `db_schema.sql` ya está corregido para futuras instalaciones
- El código PHP en `historias_clinicas.php` ahora establece explícitamente la fecha de creación
- La corrección es totalmente compatible con MySQL 5.5 y versiones superiores
- El tipo `DATETIME` funciona igual que `TIMESTAMP` para este propósito, pero sin las limitaciones de MySQL 5.5
