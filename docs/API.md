# API

Todas las rutas viven bajo `/api` y responden JSON. Las escrituras (`POST`,
`PUT`, `PATCH`, `DELETE`) exigen la cabecera `X-CSRF-Token` con el token que
devuelve `GET /api/session`. La sesión viaja en la cookie `HttpOnly`.

Leyenda de acceso: **Pública** · **Sesión** (cualquier usuario) · **Equipo**
(admin, psicología, asistente) · **Admin** · **Paciente**.

## Sesión y sitio público

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| GET | `/api/session` | Pública | Usuario actual, token CSRF, nombre de la clínica y cuentas demo (solo local) |
| POST | `/api/auth/login` | Pública | `{identifier, password}`. 422 si no coinciden, 429 tras demasiados intentos |
| POST | `/api/auth/logout` | Sesión | Cierra la sesión y devuelve un token nuevo |
| GET | `/api/public/site` | Pública | Datos públicos de la clínica, profesionales con perfil activo, enfoques, instrumentos y opciones del formulario |
| POST | `/api/public/appointment-requests` | Pública | Solicitud de cita. Máximo 3 por hora y conexión (429) |
| GET/PUT | `/api/profile` | Sesión | Perfil propio. Cambiar la contraseña exige `current_password` |
| PUT | `/api/profile/theme` | Sesión | `{theme: "light" \| "dark"}` |

## Equipo clínico

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| GET | `/api/meta` | Equipo | Catálogos (estados, modalidades, roles…), lista de pacientes y profesionales, ajustes de agenda |
| GET | `/api/dashboard` | Equipo | Métricas y listas del panel |
| GET | `/api/search?q=` | Equipo | Búsqueda de pacientes y notas |
| GET | `/api/appointment-requests?status=&page=` | Equipo | Bandeja de solicitudes |
| PATCH | `/api/appointment-requests/{id}` | Equipo | `{status}` |
| GET/POST | `/api/patients` | Equipo | Listado paginado (`q`, `status`, `page`) y alta |
| GET/PUT | `/api/patients/{id}` | Equipo | Ficha completa / edición |
| DELETE | `/api/patients/{id}` | Admin | Elimina la historia completa |
| POST | `/api/patients/{id}/diagnoses` | Equipo | Nuevo diagnóstico |
| DELETE | `/api/patients/{id}/diagnoses/{dx}` | Equipo | Elimina un diagnóstico |
| POST | `/api/patients/{id}/portal-access` | Equipo | Crea la cuenta del portal y devuelve la contraseña temporal una sola vez |
| POST | `/api/patients/{id}/assessments` | Equipo | Asigna un cuestionario al portal |
| GET | `/api/patients/{id}/note-context` | Equipo | Siguiente número de sesión y citas del paciente |
| GET/POST | `/api/appointments` | Equipo | Semana (`week`, `psychologist_id`) y alta. 409 si hay choque |
| GET/PUT/DELETE | `/api/appointments/{id}` | Equipo | Detalle, edición, eliminación |
| POST | `/api/appointments/{id}/status` | Equipo | `{status}` |
| GET/POST | `/api/notes` | Equipo | Listado (`q`, `page`) y alta |
| GET/PUT | `/api/notes/{id}` | Equipo | Detalle / edición (409 si está firmada) |
| POST | `/api/notes/{id}/sign` | Equipo | Firma y bloquea. Solo autor o admin (403), una sola vez (409) |
| GET | `/api/instruments`, `/api/instruments/{code}` | Equipo | Catálogo y definición de cada instrumento |
| GET/POST | `/api/assessments` | Equipo | Listado (`instrument`, `status`, `page`) y aplicación en consulta |
| GET/DELETE | `/api/assessments/{id}` | Equipo | Informe / eliminación |
| GET/POST | `/api/consents` | Equipo | Listado y generación desde plantilla |
| GET | `/api/consents/{id}` | Sesión | El paciente solo ve los suyos (404 si no) |
| POST | `/api/consents/{id}/sign` | Sesión | `{signed_name, strokes: [[[x, y], …], …]}` |
| GET/POST | `/api/documents` | Equipo | Repositorio y carga (`multipart/form-data`) |
| GET | `/api/documents/{id}/download` | Sesión | Descarga; el paciente solo los suyos |
| DELETE | `/api/documents/{id}` | Equipo | Elimina archivo y registro |
| GET/POST | `/api/invoices` | Equipo | Listado (`status`, `page`) y alta con `items[]` |
| GET | `/api/invoices/{id}` | Equipo | Factura, conceptos, pagos y saldo |
| POST | `/api/invoices/{id}/payments` | Equipo | Registra un pago |
| GET | `/api/settings` | Equipo | Configuración |
| PUT | `/api/settings` | Admin | Guarda la configuración |
| GET | `/api/users` | Equipo | Cuentas (sin hashes) |
| POST | `/api/users` | Admin | Nueva cuenta del equipo |
| PATCH | `/api/users/{id}` | Admin | Perfil público: `show_on_site`, `specialty`, `license_number`, `public_bio` |
| POST | `/api/users/{id}/toggle` | Admin | Activa o desactiva (no la propia) |
| GET | `/api/audit` | Equipo | Últimas 150 acciones |

## Portal del paciente

Todas exigen sesión de paciente y filtran por el paciente de la sesión: el
identificador nunca se toma de la petición.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/portal` | Próximas citas, pendientes, consentimientos y cuestionarios completados |
| GET | `/api/portal/appointments` | Todas las citas |
| GET | `/api/portal/questionnaires` | Cuestionarios con fecha y puntaje (sin interpretación clínica) |
| GET/POST | `/api/portal/questionnaires/{id}` | Definición para responder / envío de respuestas (409 si ya se respondió) |
| GET | `/api/portal/documents` | Documentos compartidos |
