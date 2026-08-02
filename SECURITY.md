# Politica de seguridad

## Alcance

PsiClinic maneja datos sensibles de salud. Esta configuracion esta pensada para
ejecucion local en un equipo controlado, no para exposicion directa a internet.

## Antes de usar con datos reales

1. Cambiar todas las contrasenas de demostracion.
2. Generar un `APP_KEY` propio con `php bin/console key`.
3. Definir `APP_DEBUG=false` y `APP_ENV=production` en el `.env`.
4. Servir la aplicacion detras de HTTPS y activar `SESSION_SECURE=true`.
5. No publicar los puertos de MySQL ni de Adminer fuera de `localhost`.
6. Programar copias de seguridad del volumen `mysql_data`.

## Controles implementados

- Contrasenas con `password_hash` y rehash automatico.
- Token CSRF obligatorio en toda peticion que modifica datos.
- Limitacion de intentos de acceso con bloqueo temporal.
- Consultas preparadas en todo el acceso a datos.
- Escapado de salida en las vistas mediante el ayudante `e()`.
- Validacion de tipo MIME y de tamano en la carga de archivos.
- Registro de auditoria de accesos y cambios sobre datos clinicos.
- Control de acceso por rol mediante middleware.

## Reporte de vulnerabilidades

Abrir un issue privado en https://github.com/stufonn-cell.
