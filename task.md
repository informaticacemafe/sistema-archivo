# Planificación de Tareas

- [ ] **1. Relación Usuario - Fuentes**
  - [ ] Crear tabla `usuarios_fuentes` en la base de datos para la relación N a M.
  - [ ] Modificar la interfaz de [usuarios.php](file:///c:/Users/Sectorial/Documents/Sistemas/Historias%20clinicas%20en%20papel/proyecto%20claude/usuarios.php) para permitir asociar 0 o múltiples fuentes a un usuario.
  - [ ] Adaptar el backend para guardar y actualizar estas relaciones en la creación o edición de usuarios.

- [ ] **2. Configuración de Formato de Numeración en Fuentes**
  - [ ] Agregar campo `formato_numeracion` a la tabla `fuentes` (opciones: `autoincremental`, `letra_autoincremental`, `dni`).
  - [ ] Modificar [fuentes.php](file:///c:/Users/Sectorial/Documents/Sistemas/Historias%20clinicas%20en%20papel/proyecto%20claude/fuentes.php) para añadir un selector en la creación/edición de fuentes para elegir el formato de numeración.

- [ ] **3. Interfaz de Creación de Historias Clínicas (Mejoras Varias)**
  - [ ] **3a. Navegación en Búsqueda de Pacientes:** Modificar JS en [historias_clinicas.php](file:///c:/Users/Sectorial/Documents/Sistemas/Historias%20clinicas%20en%20papel/proyecto%20claude/historias_clinicas.php) para navegar la lista de resultados con flechas (Arriba/Abajo) y seleccionar con Enter, resaltando en gris sutil.
  - [ ] **3b. Autoselección de Ubicación Inicial:** Modificar JS para que al seleccionar una fuente, si tiene archivo físico, se seleccione automáticamente "Archivo de Servicio" (u opción correspondiente) en el selector de ubicación.
  - [ ] **3c. Autoselección de Fuente por Usuario:** Al cargar el modal de nueva HC, autoseleccionar la fuente si el usuario logueado tiene solo una fuente asociada, o priorizar una según el usuario logueado. (Se ajustará recuperando las fuentes del usuario desde la sesión o vía AJAX).
  - [ ] **3d. Sugerencia de Número de HC:** 
    - [ ] Crear `sugerir_numero_hc_ajax.php` que reciba `id_fuente` y `id_paciente`.
    - [ ] Implementar la lógica de sugerencia (DNI del paciente, Número autoincremental, o Letra del apellido + autoincremental).
    - [ ] Enlazar en el frontend para llamar a esta API y autocompletar el campo Número de HC cuando se selecciona el paciente y la fuente.

- [ ] **4. Verificación y Pruebas**
  - [ ] Verificar la correcta inserción en `usuarios_fuentes`.
  - [ ] Probar creación de fuentes con distintos formatos de numeración.
  - [ ] Probar atajos de teclado en búsqueda de paciente.
  - [ ] Validar sugerencias de números (Mastología, Dermatología, DNI) en formulario.
