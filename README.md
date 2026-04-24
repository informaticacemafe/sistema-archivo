# Sistema de Gestión de Historias Clínicas en Papel

Sistema web para administrar el registro, seguimiento y control de historias clínicas físicas (en papel) dentro de una institución de salud. Permite rastrear la ubicación de cada HC, registrar movimientos entre servicios, y mantener un log de auditoría de todas las operaciones.

---

## Módulos del Sistema

### 1. Pacientes
Registro central de pacientes con datos demográficos y documentación.

**Funcionalidades:**
- **Crear paciente:** Ingresar nuevo paciente con tipo/nro de documento, nombre, apellido, fecha de nacimiento, sexo y teléfono
- **Editar paciente:** Modificar datos existentes (el documento no se puede cambiar)
- **Buscar:** Por documento, nombre o apellido
- **Ver HC del paciente:** Cada paciente puede tener una o más historias clínicas asociadas (de distintas fuentes/servicios)

### 2. Historias Clínicas (HC)
Gestión de las historias clínicas físicas y su numeración.

**Funcionalidades:**
- **Crear HC:** Asocia un paciente con un número de HC dentro de una fuente (servicio). Se registra automáticamente el movimiento inicial de ingreso a archivo
- **Buscar:** Por número de HC, paciente o documento
- **Ver detalle:** Muestra información completa de la HC, datos del paciente, observaciones y todo el historial de movimientos
- **Editar número HC:** Solo administradores pueden modificar el número de una HC
- **Eliminar HC:** Solo administradores, con confirmación explícita ("ELIMINAR") y eliminación en cascada de todos sus movimientos

### 3. Movimientos
Registro de cada vez que una HC cambia de ubicación o estado.

**Tipos de movimiento:**
| Tipo | Descripción |
|------|-------------|
| Ingreso a Archivo | Ingreso inicial al sistema |
| Salida a Servicio | HC solicitada por un servicio |
| Devolución a Archivo | HC devuelta al archivo central |
| Salida Extramuro | HC retirada fuera de la institución |
| Ingreso desde Extramuro | HC que regresa de extramuro |
| Traslado Interno | Cambio entre servicios |
| Dar de Baja | HC dada de baja definitiva |
| Reportar Extraviada | HC reportada como perdida |
| Recuperada | HC extraviada que fue recuperada |

**Funcionalidades:**
- **Registrar movimiento:** Seleccionar tipo, ubicación destino y observaciones. Cada movimiento actualiza automáticamente el estado y ubicación de la HC
- **Ver historial:** Todos los movimientos ordenados del más reciente al más antiguo
- **Eliminar movimiento:** Solo administradores, con confirmación

### 4. Fuentes (Servicios)
Configuración de los servicios o áreas que tienen su propia numeración de HC.

**Datos de cada fuente:**
- Nombre del servicio
- Código identificatorio (ej: CM, PED, CG)
- Color (para identificación visual en listados)
- ¿Tiene archivo físico propio?
- Formato de numeración (autoincremental, letra+autoincremental, DNI paciente)

### 5. Usuarios
Gestión de usuarios del sistema con diferentes roles.

**Roles:**
| Rol | Permisos |
|-----|----------|
| administrador | Acceso completo: crear/editar/eliminar HC, pacientes, fuentes, usuarios, ver auditoría |
| archivo | Registrar movimientos, crear HC y pacientes |
| servicio | Registrar movimientos (salidas/devoluciones) |
| auditor | Solo lectura: puede ver detalle de HC pero no registrar movimientos |

### 6. Log de Actividades (Auditoría)
Registro narrativo de todas las operaciones realizadas en el sistema.

**Qué registra:**
- Creación, modificación y eliminación de pacientes
- Creación, modificación y eliminación de HC
- Registro y eliminación de movimientos
- Cambios en fuentes

**Datos que incluye cada registro:**
- Fecha y hora exacta
- Usuario que realizó la operación
- Tipo de entidad afectada (paciente, HC, movimiento, fuente)
- Resumen descriptivo del evento
- Detalle expandible con valores anterior y nuevo (cuando corresponde)

**Filtros disponibles:**
- Por rango de fechas
- Por tipo de entidad
- Por usuario
- Por tipo de acción (CREAR, EDITAR, ELIMINAR)

### 7. Búsqueda Avanzada
Búsqueda combinada por múltiples criterios.

### 8. Reportes
Estadísticas y reportes del sistema.

---

## Reglas de Negocio

- **Numeración única:** No puede existir el mismo número de HC dentro de la misma fuente. La validación se hace en tiempo real al escribir el número
- **Espacios no permitidos:** El número de HC no puede contener espacios
- **Documento único:** No se puede registrar dos pacientes con el mismo tipo y número de documento
- **Movimiento inicial:** Al crear una HC se genera automáticamente un movimiento de "Ingreso a Archivo"
- **Protección de movimientos iniciales:** El movimiento de creación de HC no puede eliminarse
- **Redirección:** Al registrar un movimiento, el sistema redirige automáticamente al detalle de la HC

---

## Instalación

1. Ejecutar `db_schema.sql` en MySQL para crear la base de datos y tablas
2. Ejecutar los scripts de migración en orden si se actualiza desde una versión anterior
3. Configurar `conexion.php` con los datos de conexión a la base de datos
4. El usuario admin por defecto es `admin` con contraseña `admin123`
