# Migración a la versión 2.0

Qué cambió al separar el backend del frontend, qué se conservó, qué se corrigió
y qué queda pendiente.

## Resumen

| Antes (1.x) | Ahora (2.0) |
|---|---|
| PHP renderizaba HTML con plantillas (`views/`) | PHP expone una API JSON bajo `/api` |
| CSS y JS sueltos en `public/assets` | SPA en React + TypeScript (`frontend/`) compilada con Vite |
| Sin sitio público: `/` redirigía al login | Sitio público con solicitud de cita |
| Formularios con recarga y mensajes flash | Formularios con validación en vivo, errores por campo y avisos |
| Código PHP en la raíz | Código PHP en `backend/` |

## Qué se conservó del backend

- Toda la capa `Domain`: pacientes, agenda y detección de choques, notas,
  instrumentos y su corrección, consentimientos, documentos, facturación,
  métricas y auditoría. Las consultas SQL no cambiaron.
- El esquema `001_schema.sql` y el instalador con datos de demostración.
- El router, la validación, la sesión basada en cookie y el CSRF por sesión.
- Los roles y la matriz de permisos por ruta.
- La suite de pruebas propia (sin PHPUnit), ampliada.

## Qué cambió en el backend (y por qué)

| Cambio | Motivo |
|---|---|
| Controladores devuelven JSON; se eliminaron `View`, `views/`, `Icons` y `Chart` | La presentación vive ahora en el frontend |
| `HttpException` y respuesta JSON uniforme de errores | Mensajes humanos y errores por campo para los formularios |
| CSRF en la cabecera `X-CSRF-Token` y rotación al iniciar sesión | Patrón estándar para SPA; evita fijar el token |
| Intentos de acceso en la tabla `login_attempts`, por usuario y por IP | Antes vivían en la sesión: borrar la cookie reiniciaba el contador |
| Cierre de sesión tras inactividad (`SESSION_LIFETIME`) | Historias clínicas abiertas en equipos compartidos |
| Valores de catálogo restringidos con `oneOf` | Un valor fuera del `ENUM` producía un error 500 |
| La edición de citas ahora valida igual que el alta | Antes no validaba |
| `meeting_url` solo acepta `http(s)` | Evita enlaces `javascript:` mostrados al paciente |
| Firmar una nota: solo autor o admin, una sola vez | La firma acredita autoría |
| Respuestas de cuestionarios validadas contra la escala | Antes se aceptaba cualquier entero |
| Contraseña: mínimo 10 caracteres y confirmar la actual para cambiarla | Endurecimiento de cuentas |
| SVG eliminado de los tipos de documento permitidos | Un SVG puede contener código |
| Mensajes de validación con tildes y en tono cercano | Calidad del texto para personas |

## Vulnerabilidades corregidas

1. **XSS almacenado en la firma de consentimientos.** El SVG enviado por el
   navegador se guardaba y se imprimía sin escapar en la vista del equipo. Ahora
   el navegador envía coordenadas, el servidor construye el SVG y además lo
   sanea al devolverlo; el frontend lo muestra como `<img>`.
2. **IDOR en consentimientos.** Un paciente podía ver y firmar el consentimiento
   de otra persona cambiando el número en la URL.
3. **XSS en la búsqueda global.** Los resultados se insertaban con `innerHTML`.
4. **Cuestionarios reenviables.** Un cuestionario respondido podía enviarse de
   nuevo y sobrescribir el resultado.
5. **Cuentas demo visibles en producción.** Ahora solo se anuncian con
   `APP_ENV=local`.
6. **Nombre de archivo en la cabecera de descarga.** Se codifica para evitar
   inyección de cabeceras.

Todas tienen prueba de regresión en `backend/tests/Feature/ApiTest.php`.

## Base de datos

`002_public_site.sql` (idempotente, se aplica con `php bin/console migrate`):

- `appointment_requests`: solicitudes del sitio público. La IP se guarda como
  HMAC con `APP_KEY`, no en claro.
- `login_attempts`: intentos fallidos de acceso.
- `users.show_on_site` y `users.public_bio`: perfil público opcional.
- Normaliza los nombres de intervenciones en notas existentes al catálogo con
  tildes.

Nuevas claves de configuración: `clinic_about`, `whatsapp_number`, `crisis_line`.

Las etiquetas de severidad (`Minima`, `Depresion`…) **no** se cambiaron porque
se guardan con cada resultado; el frontend les agrega tildes solo al mostrarlas.
Los consentimientos ya generados conservan su texto original.

## Funcionalidades nuevas

- Sitio público completo y solicitud de cita en tres pasos, con bandeja para el
  equipo y conversión a ficha de paciente.
- Perfil público opcional de cada profesional.
- Aviso de ayuda inmediata con línea de emergencias configurable.
- Enlace de WhatsApp opcional.
- Página de privacidad.
- Cambio de contraseña desde el perfil.
- Filtro en la auditoría y descripciones legibles de cada acción.
- Accesos directos: escribir la nota desde una cita realizada, crear la ficha
  desde una solicitud.

## Pendiente o recomendado después

| Tema | Detalle |
|---|---|
| Prerenderizado del sitio público | Hoy es una SPA: los buscadores modernos ejecutan JS, pero un HTML estático de la portada mejoraría el SEO y el primer pintado. Opciones: `vite-plugin-ssr`/`react-router` en modo framework, o generar `index.html` con los datos públicos en el build |
| Imagen Open Graph | `og:image` apunta al logo SVG; varias redes requieren PNG/JPG de 1200×630 |
| Fotografías reales | El diseño usa una ilustración de línea; si la clínica tiene fotos propias del consultorio o del equipo, conviene añadir un campo de foto al perfil público |
| Notificaciones | Avisar por correo al equipo cuando llega una solicitud y recordar citas al paciente. Requiere configurar SMTP (las variables `MAIL_*` ya existen) y una cola o cron |
| Calendario | Exportar citas en `.ics` o sincronizar con Google/Outlook. Endpoint sugerido: `GET /api/appointments/{id}.ics` |
| Recuperación de contraseña | Hoy la restablece el consultorio. Requiere correo saliente y una tabla de tokens de un solo uso |
| Segundo factor | Recomendable para cuentas del equipo |
| Pruebas de extremo a extremo | Playwright sobre los flujos críticos (ingreso, nota, firma, solicitud) |
| Actualización de dependencias | Se fijaron React Router 7, Vite 7 y TypeScript 5.9 por estabilidad; evaluar las siguientes versiones mayores en una rama aparte |
| Etiquetas de catálogo del portal | El portal tiene sus propias etiquetas porque `/api/meta` es solo del equipo; podría exponerse un subconjunto público |
