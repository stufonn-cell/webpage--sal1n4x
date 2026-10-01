# Arquitectura

Documento de referencia para entender y extender el código.

## Visión general

```
Navegador
   │  mismo origen (cookie de sesión HttpOnly + cabecera X-CSRF-Token)
   ▼
Nginx ──────────────► frontend/dist        (SPA: sitio, app, portal)
   │
   └── /api/* ──────► PHP-FPM → backend/public/index.php
                         Router → Middlewares → Controller → Domain → JSON
                                                                │
                                                              MySQL
```

### Por qué esta separación

- **El backend PHP se conserva.** La lógica clínica (puntuación de instrumentos,
  choques de agenda, firma de notas, auditoría) ya estaba probada. Reescribirla
  en otro lenguaje habría añadido riesgo sin beneficio para el usuario.
- **El frontend pasa a React.** La interfaz tiene mucho estado (formularios por
  pasos, pestañas, búsqueda, firma, cuestionarios) y se beneficia de componentes
  reutilizables y tipado estricto.
- **Mismo origen.** Nginx sirve ambas piezas, así que no hace falta CORS ni
  tokens en `localStorage`: la sesión vive en una cookie `HttpOnly`.

## Backend

### Principios

1. **Sin magia.** No hay contenedor de dependencias ni ORM.
2. **Consultas explícitas.** El SQL vive en `Domain`.
3. **Solo JSON.** Ya no hay vistas: cada controlador devuelve datos.
4. **Errores esperados como excepciones.** `HttpException` lleva el código
   HTTP, un mensaje pensado para personas y los errores por campo.
5. **Nada sensible sale por la API.** `Support/Present` filtra columnas
   (por ejemplo, nunca devuelve `password_hash`).

### Capas

| Capa | Responsabilidad |
|---|---|
| `public/index.php` | Arranque, cabeceras, captura de `HttpException` (JSON) y de cualquier otro error (500 genérico, detalle solo al log) |
| `src/Core` | `Router` (404/405 en JSON), `Request` (cuerpo JSON), `Session` (expiración por inactividad), `Auth` (intentos fallidos en base de datos), `Csrf` (rotación al iniciar sesión), `Validator`, `HttpException` |
| `src/Core/Middleware` | `VerifyCsrf` (cabecera `X-CSRF-Token`), `Authenticate` (401), `RequireStaff`, `RequireAdmin`, `RequirePatient` (403) |
| `src/Controllers` | Validan la entrada, restringen los valores de catálogo (`oneOf`) y llaman al dominio |
| `src/Domain` | Reglas y consultas. Las constantes de catálogo son la fuente de las etiquetas que muestra la interfaz (`/api/meta`) |
| `src/Support` | `Present` (serialización), `Signature` (construye el SVG de la firma a partir de coordenadas), `Installer` |

### Formato de respuesta

```json
{ "data": { ... }, "message": "Texto opcional para mostrar" }
{ "error": { "message": "Texto para la persona", "fields": { "email": "..." } } }
```

Códigos usados: `200`, `201`, `401`, `403`, `404`, `405`, `409` (conflicto:
choque de agenda, nota ya firmada…), `419` (CSRF), `422` (validación), `429`
(demasiados intentos), `500`.

## Frontend

### Estructura

| Carpeta | Contenido |
|---|---|
| `styles/tokens.css` | Única fuente de colores, tipografía, espaciado, radios, sombras, movimiento y breakpoints, en claro y oscuro |
| `styles/base.css` | Reset, foco visible, enlace para saltar al contenido, utilidades de layout, animación de aparición, impresión |
| `components/ui` | Button, Field, Badge, Panel, PageHeader, EmptyState, Tabs, Toast, diálogo de confirmación, estados de carga, paginación, iconos |
| `components/charts` | Línea, barras y distribución en SVG, con resumen accesible |
| `components/forms` | Firma (Pointer Events), cuestionario Likert, documento de consentimiento |
| `site/` | Sitio público. Los textos editoriales están en `site/content.ts` |
| `app/` | Aplicación del equipo; una carpeta por módulo en `app/pages` |
| `portal/` | Portal del paciente |
| `session/` | Sesión (usuario, CSRF, cierre por 401) y tema |
| `lib/` | Cliente de la API, formatos es-CO, catálogos, tipos |

### Decisiones

- **Tres bundles.** El sitio público no descarga el código clínico y un paciente
  no descarga el del equipo (`React.lazy` por área).
- **Datos con TanStack Query.** Caché, reintentos y estados de carga uniformes.
  No se reintenta un error 4xx.
- **Formularios sin librería.** `hooks/useForm` gestiona valores, errores del
  cliente y los `422` de la API, y enfoca el primer campo con error.
- **Sin librerías de animación.** Transiciones CSS (`opacity` + `transform`) y
  un `IntersectionObserver` para la aparición al hacer scroll. Todo se desactiva
  con `prefers-reduced-motion`.
- **Fuentes propias.** Newsreader y Source Sans 3 se sirven desde el propio
  dominio (`@fontsource`): sin peticiones a terceros.
- **Nada personal en `localStorage`.** Solo se guarda la preferencia de tema.
  Los datos de una solicitud nunca viajan en la URL (se usa el estado del router).

## Seguridad

| Riesgo | Control |
|---|---|
| CSRF | Token por sesión en cabecera; rota al iniciar sesión; reintento automático tras un 419 |
| XSS | React escapa toda salida; la firma se reconstruye en el servidor y se muestra como `<img>`; CSP `script-src 'self'` sin `unsafe-inline` |
| IDOR | Consentimientos, documentos y cuestionarios se filtran por el paciente de la sesión |
| Fuerza bruta | Intentos fallidos en base de datos, por usuario y por IP |
| Sesión olvidada | Cierre tras `SESSION_LIFETIME` segundos sin actividad |
| Datos en caché | `Cache-Control: no-store` en toda respuesta de la API |
| Exposición | `Present` filtra columnas; tarifas y correos del equipo no son públicos |
| Errores | Nunca se muestran detalles técnicos; van al log del servidor |

## Puntos de extensión

**Agregar un instrumento**: editar `backend/src/Domain/instrument_catalog.php`.
La API (`/api/instruments`), el formulario, el portal y las gráficas lo toman
automáticamente.

**Agregar un módulo**

1. Migración en `backend/database/migrations/00N_*.sql` (idempotente).
2. Dominio en `backend/src/Domain`, controlador en `backend/src/Controllers`.
3. Rutas en `backend/src/routes.php` con el middleware adecuado.
4. Prueba en `backend/tests/Feature/ApiTest.php`.
5. Página en `frontend/src/app/pages/<modulo>/`, ruta en `app/StaffApp.tsx` y
   enlace en `app/layout/StaffLayout.tsx`.

**Cambiar textos del sitio**: `frontend/src/site/content.ts`. Los datos de
contacto, la presentación y el número de WhatsApp se editan desde
*Configuración* en la aplicación.

## Convenciones

- Backend: `strict_types`, nombres en inglés, textos de interfaz en español.
- Frontend: TypeScript estricto, componentes pequeños, sin estilos sueltos
  (siempre tokens), etiquetas de catálogo desde `/api/meta`.
- Textos para personas: español neutro, cercano, con tildes, sin tecnicismos.

---

Hecho por Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
