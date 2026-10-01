# Registro de cambios

Formato basado en Keep a Changelog. Versionado semantico.

## [2.0.0] - 2026-10-01

Separación de backend y frontend. Detalle completo en `docs/MIGRACION-2.0.md`.

### Arquitectura

- El backend PHP pasa a `backend/` y expone una API JSON bajo `/api`.
- Nuevo frontend en React 19 + TypeScript + Vite en `frontend/`.
- Nginx sirve la SPA y reenvía `/api` a PHP-FPM (mismo origen).
- Imagen de producción propia para el frontend; CI con dos suites.

### Nuevo

- Sitio público con servicios, equipo, confidencialidad, preguntas frecuentes,
  contacto, privacidad y datos estructurados.
- Solicitud de cita en tres pasos y bandeja de solicitudes para el equipo.
- Perfil público opcional por profesional, WhatsApp y línea de emergencias
  configurables.
- Sistema de diseño con tokens, modo oscuro, diseño móvil propio y
  movimiento que respeta `prefers-reduced-motion`.

### Seguridad

- Corregido XSS almacenado en la firma de consentimientos.
- Corregido acceso de un paciente a consentimientos ajenos (IDOR).
- Corregido XSS en la búsqueda global.
- Intentos de acceso persistentes, cierre por inactividad, rotación de CSRF y
  CSP estricta.

### Cambiado

- Validación de catálogos, de enlaces de videollamada y de respuestas de
  cuestionarios; la edición de citas valida como el alta.
- Firmar una nota queda reservado a su autor o a administración.
- Contraseñas de al menos 10 caracteres; cambiarla exige la actual.

## [1.0.0] - 2026-08-01

Primera version funcional completa.

### Clinico

- Ficha de paciente con datos personales, contacto, motivo de consulta,
  antecedentes, medicacion, contacto de emergencia y semaforo de riesgo.
- Agenda semanal con deteccion de choques de horario, tres modalidades y cinco
  estados de asistencia.
- Notas de sesion en formato SOAP, DAP o libre, con catalogo de intervenciones,
  escala de animo, firma que bloquea la nota y version imprimible.
- Diagnosticos CIE-10 y DSM-5.
- Linea de tiempo unificada por paciente.

### Instrumentos psicometricos

- PHQ-9, GAD-7, DASS-21, PSS-10, RSES y WHO-5 con correccion automatica.
- Soporte de items inversos, subescalas, multiplicadores y bandas de severidad.
- Deteccion de items criticos con alerta en el informe.
- Grafica de evolucion por instrumento y por paciente.

### Portal del paciente

- Consulta de citas, con acceso directo a la videollamada.
- Respuesta de cuestionarios asignados con barra de progreso.
- Curva de evolucion propia.
- Firma de consentimientos con trazo vectorial.
- Descarga de documentos.

### Administrativo

- Cinco plantillas de consentimiento informado.
- Repositorio de documentos con validacion de tipo y tamano.
- Facturacion con conceptos, impuestos, pagos parciales y cierre automatico.
- Configuracion de la clinica, gestion de usuarios y registro de auditoria.

### Interfaz

- Pantalla de acceso con ilustracion vectorial e ingreso rapido de demostracion.
- Modo claro y oscuro persistente por usuario.
- Busqueda global con `Ctrl+K`.
- Graficas SVG generadas en el servidor: barras, lineas, dona y medidor.
- Iconografia vectorial inline. Ninguna imagen rasterizada.
- Diseno responsive y hoja de estilos de impresion.

### Infraestructura

- Docker Compose con Nginx, PHP-FPM 8.3, MySQL 8.4 y Adminer.
- Dockerfile multietapa con objetivos `development` y `production`.
- Archivo de composicion propio para produccion.
- Migraciones idempotentes y CLI `bin/console`.
- Suite de 167 pruebas sin dependencias externas, con corredor propio.
- Integracion continua en GitHub Actions.

### Seguridad

- Contrasenas con `password_hash` y rehash automatico.
- Token CSRF en toda peticion que modifica datos.
- Bloqueo temporal tras cinco intentos fallidos.
- Consultas preparadas y escapado de salida en todas las vistas.
- Control de acceso por rol mediante middleware.
- Registro de auditoria de accesos y cambios.
