# Registro de cambios

Formato basado en Keep a Changelog. Versionado semantico.

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
