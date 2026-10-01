# PsiClinic

Sistema de historia clínica para consulta psicológica, con sitio público, portal
del paciente y módulo de instrumentos psicométricos con corrección automática.

Desde la versión 2.0 el proyecto está separado en dos piezas:

| Pieza | Tecnología | Responsabilidad |
|---|---|---|
| `backend/` | PHP 8.3 sin frameworks, MySQL 8.4 | API JSON bajo `/api`, reglas clínicas, seguridad, auditoría |
| `frontend/` | React 19, TypeScript, Vite | Sitio público, aplicación del equipo clínico y portal del paciente |

Nginx sirve el frontend compilado y reenvía `/api/*` a PHP-FPM: el navegador ve
un único origen, así que la cookie de sesión y el token CSRF funcionan sin CORS.

Hecho por Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)

---

## Qué incluye

**Sitio público** (`/`)

- Portada con presentación de la clínica, servicios explorables, cómo empezar,
  equipo (solo profesionales que activan su perfil), confidencialidad, preguntas
  frecuentes y contacto.
- Solicitud de cita en tres pasos, con validación por paso, campo trampa para
  bots y límite de envíos por conexión. Las solicitudes llegan a una bandeja del
  equipo.
- Aviso permanente de ayuda inmediata con la línea de emergencias configurable.
- Página de privacidad basada en los consentimientos que la clínica ya usa.
- Metadatos, Open Graph, URL canónica y datos estructurados (schema.org).

**Equipo clínico** (`/app`)

- Panel del día, bandeja de solicitudes, agenda semanal con detección de choques,
  pacientes, notas SOAP/DAP/libres con firma que bloquea, diagnósticos CIE-10 y
  DSM-5, evaluaciones con bandas de severidad e ítems críticos, consentimientos
  con firma de trazo, documentos, facturación con pagos parciales,
  configuración, usuarios y auditoría.
- Búsqueda global con `Ctrl+K`, modo claro y oscuro, versión imprimible de notas
  y facturas.

**Portal del paciente** (`/portal`)

- Próxima sesión con acceso a la videollamada, cuestionarios con progreso, curva
  de evolución, firma de consentimientos y descarga de documentos.

**Instrumentos**: PHQ-9, GAD-7, DASS-21, PSS-10, RSES y WHO-5.

---

## Arranque rápido

Requisitos: Docker Desktop con WSL2 (Windows) o Docker Engine (Linux, macOS).

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic
cp .env.example .env
make install          # compila el frontend, levanta todo y carga datos demo
```

Sin `make`:

```bash
docker compose run --rm --no-deps frontend sh -c "npm install && npm run build"
docker compose up -d
```

| Dirección | Qué es |
|---|---|
| <http://localhost:8080> | Aplicación completa (frontend compilado + API) |
| <http://localhost:5173> | Frontend con recarga en caliente para desarrollar |
| <http://localhost:8081> | Adminer (servidor `mysql`) |

Cuentas de demostración (solo con `APP_ENV=local`; la pantalla de ingreso las
muestra para rellenarlas con un clic):

| Rol | Usuario | Contraseña |
|---|---|---|
| Administración | `admin` | `Psiclinic2026` |
| Psicóloga | `l.moreno` | `Psiclinic2026` |
| Paciente | `hc-2026-0001` | `Paciente2026` |

La guía detallada para Windows está en [docs/INSTALACION-WSL.md](docs/INSTALACION-WSL.md).

---

## Comandos

```bash
bin/psiclinic up          # levanta todo (compila el frontend si falta)
bin/psiclinic build       # vuelve a compilar el frontend
bin/psiclinic test        # pruebas del backend
bin/psiclinic test-front  # tipos y pruebas del frontend
bin/psiclinic logs        # registros de PHP, Nginx y Vite
bin/psiclinic down        # detiene todo conservando los datos
```

Dentro del contenedor del backend:

```bash
php bin/console install    # migraciones + datos de demostración
php bin/console migrate    # solo el esquema (idempotente)
php bin/console fresh      # borra todo y reinstala
php bin/console key        # genera un APP_KEY
```

Equivalentes con `make`: `make up`, `make build`, `make test`, `make test-front`,
`make fresh`, `make logs`, `make down`.

---

## Pruebas

- **Backend**: 167 pruebas sin PHPUnit ni Composer (`php bin/test`). Incluyen
  pruebas de la API real con rutas, middlewares, CSRF, permisos por rol, IDOR,
  firmas, choques de agenda, validación y límites de envío.
- **Frontend**: comprobación estricta de tipos (`npm run typecheck`) y pruebas
  con Vitest (`npm test`).

Detalle en [docs/PRUEBAS.md](docs/PRUEBAS.md). CI ejecuta ambas suites y
construye las dos imágenes de producción en cada push.

---

## Estructura

```
psiclinic/
├── backend/
│   ├── bin/                 console y test
│   ├── database/migrations  esquema SQL idempotente
│   ├── public/index.php     controlador frontal de la API
│   ├── src/Core             router, request, sesión, auth, CSRF, errores HTTP
│   ├── src/Controllers      un controlador por módulo (JSON)
│   ├── src/Domain           reglas de negocio y consultas
│   ├── src/Support          serialización, firma, instalador
│   └── tests/               unitarias y de integración
├── frontend/
│   └── src/
│       ├── styles/          tokens del sistema de diseño y base
│       ├── components/      ui, gráficas y formularios compartidos
│       ├── site/            sitio público
│       ├── app/             aplicación del equipo clínico
│       ├── portal/          portal del paciente
│       ├── session/         sesión y tema
│       ├── hooks/ lib/      utilidades, cliente de la API, tipos
│       └── pages/           ingreso, perfil, errores
├── docker/                  Dockerfiles y Nginx
├── bin/psiclinic            atajos para el día a día
└── docs/
```

Arquitectura en [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md), referencia de la
API en [docs/API.md](docs/API.md) y cambios de la versión 2 en
[docs/MIGRACION-2.0.md](docs/MIGRACION-2.0.md).

---

## Despliegue

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Construye una imagen del backend con el código dentro y otra de Nginx con el
frontend ya compilado. Ver [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).

---

## Advertencia clínica y legal

El uso de instrumentos psicométricos requiere formación profesional. Los puntajes
son orientativos y no sustituyen el juicio clínico ni constituyen un diagnóstico.
Antes de usar el sistema con datos reales, revisa la normativa local de
protección de datos de salud y la sección [SECURITY.md](SECURITY.md).

---

## Licencia

Software propietario. Ver [LICENSE](LICENSE) y [NOTICE](NOTICE).
El aviso de autoría debe conservarse en cualquier copia o derivado.
