# PsiClinic

Sistema de historia clinica para consulta psicologica. Toma el modelo funcional
de OpenEMR (pacientes, agenda, notas de evolucion, documentos, facturacion) y lo
reconstruye con una interfaz moderna, un modulo de instrumentos psicometricos con
correccion automatica y un portal para el paciente.

Stack: PHP 8.3, MySQL 8.4, Nginx, Docker. Sin frameworks ni dependencias de
compilacion: no requiere Composer, npm ni build step.

Hecho por Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)

---

## Que incluye

**Clinico**

- Ficha completa del paciente con motivo de consulta, antecedentes, medicacion y
  contacto de emergencia.
- Agenda semanal con deteccion de choques de horario, modalidad presencial,
  virtual o telefonica y estados de asistencia.
- Notas de sesion en formato SOAP, DAP o libre, con catalogo de intervenciones,
  escala de animo, nivel de riesgo, firma que bloquea la nota y version imprimible.
- Diagnosticos CIE-10 y DSM-5 por paciente.
- Linea de tiempo unificada de notas, citas y evaluaciones.

**Instrumentos psicometricos**

Seis escalas de uso libre con puntuacion, bandas de severidad, interpretacion,
deteccion de items criticos y grafica de evolucion:

| Codigo  | Escala                                | Items | Rango  |
|---------|---------------------------------------|-------|--------|
| PHQ-9   | Depresion                             | 9     | 0-27   |
| GAD-7   | Ansiedad generalizada                 | 7     | 0-21   |
| DASS-21 | Depresion, ansiedad y estres          | 21    | 0-126  |
| PSS-10  | Estres percibido                      | 10    | 0-40   |
| RSES    | Autoestima de Rosenberg               | 10    | 0-30   |
| WHO-5   | Bienestar subjetivo                   | 5     | 0-100  |

Soporta items de puntuacion inversa, subescalas y multiplicadores.

**Portal del paciente**

Acceso propio para consultar citas, responder cuestionarios asignados, ver su
curva de evolucion, firmar consentimientos con trazo vectorial y descargar
documentos.

**Administrativo**

Consentimientos informados con cinco plantillas, repositorio de documentos con
validacion MIME, facturacion con conceptos, impuestos y pagos parciales,
configuracion de la clinica, gestion de usuarios y registro de auditoria.

**Interfaz**

Modo claro y oscuro persistente, busqueda global con `Ctrl+K`, graficas SVG
generadas en el servidor, iconografia vectorial inline, diseno responsive y
estilos de impresion. No se usa ninguna imagen rasterizada.

---

## Arranque rapido

Requisitos: Docker Desktop con WSL2 (Windows) o Docker Engine (Linux o macOS).

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic
cp .env.example .env
docker compose up -d --build
docker compose exec app php bin/console install
```

Abrir <http://localhost:8080>.

| Rol           | Usuario        | Contrasena     |
|---------------|----------------|----------------|
| Administrador | `admin`        | `Psiclinic2026`|
| Psicologa     | `l.moreno`     | `Psiclinic2026`|
| Paciente      | `hc-2026-0001` | `Paciente2026` |

Adminer queda en <http://localhost:8081> (servidor `mysql`).

La guia detallada para Windows esta en [docs/INSTALACION-WSL.md](docs/INSTALACION-WSL.md).

---

## Arranque despues de reiniciar el PC

Docker no conserva los contenedores en marcha tras un reinicio. El proyecto trae
`bin/psiclinic`, un script que levanta todo, espera a que MySQL responda y aplica
las migraciones pendientes en un solo paso.

### 1. Crear el atajo en WSL

```bash
cd ~/proyectos/psiclinic
chmod +x bin/psiclinic

echo "alias psiclinic='~/proyectos/psiclinic/bin/psiclinic'" >> ~/.bashrc
source ~/.bashrc
```

Desde ese momento, tras cada reinicio basta con abrir Ubuntu y escribir:

```bash
psiclinic up
```

Salida:

```
  Levantando contenedores...
  Esperando a que MySQL responda...
  MySQL listo.
  PsiClinic disponible en http://localhost:8080
```

### 2. Comandos del atajo

| Comando | Que hace |
|---|---|
| `psiclinic up` | Levanta los contenedores y aplica migraciones pendientes |
| `psiclinic down` | Detiene todo conservando los datos |
| `psiclinic restart` | Reinicia por completo |
| `psiclinic status` | Estado de los cuatro contenedores |
| `psiclinic logs` | Registros de PHP y Nginx en vivo |
| `psiclinic install` | Primera instalacion con datos de demostracion |
| `psiclinic test` | Ejecuta la suite de pruebas |
| `psiclinic shell` | Terminal dentro del contenedor de PHP |
| `psiclinic db` | Cliente de MySQL |
| `psiclinic backup` | Volcado comprimido con la fecha en el nombre |
| `psiclinic open` | Abre la aplicacion en el navegador |

### 3. Acceso directo en el escritorio de Windows

`bin/PsiClinic.bat` arranca Docker Desktop si hace falta, levanta los
contenedores y abre el navegador.

1. Abrir el Explorador de Windows en `\\wsl$\Ubuntu-22.04\home\TU_USUARIO\proyectos\psiclinic\bin`.
2. Clic derecho sobre `PsiClinic.bat` y elegir *Enviar a* -> *Escritorio (crear acceso directo)*.
3. Clic derecho en el acceso directo -> *Propiedades* -> *Cambiar icono* si se
   quiere personalizar.

Si el proyecto esta en otra carpeta, editar la linea `set RUTA=` del archivo.

### 4. Arranque totalmente automatico (opcional)

Para no tener que hacer nada tras encender el equipo:

1. En Docker Desktop, *Settings* -> *General* -> marcar
   *Start Docker Desktop when you sign in to your computer*.
2. Pulsar `Win + R`, escribir `shell:startup` y aceptar.
3. Copiar ahi el acceso directo a `PsiClinic.bat`.

Al iniciar sesion en Windows, la aplicacion quedara disponible en
<http://localhost:8080> sin intervencion.

Para dejar de usarlo, quitar el acceso directo de esa carpeta.

---

## Pruebas

167 pruebas en 16 archivos, sin PHPUnit ni Composer.

```bash
docker compose exec app php bin/test           # todo
docker compose exec app php bin/test unit      # solo unitarias
docker compose exec app php bin/test feature   # solo de integracion
```

Las unitarias cubren la puntuacion de los seis instrumentos, el validador, el
enrutador, la lectura del `.env`, los ayudantes y la generacion de graficas SVG.
Las de integracion se ejecutan contra la base `psiclinic_test` y cubren esquema,
autenticacion, pacientes, agenda, notas, evaluaciones, facturacion, instalador y
el renderizado real de cada pantalla.

Detalle en [docs/PRUEBAS.md](docs/PRUEBAS.md).

---

## Comandos

```bash
php bin/console install    # migraciones + datos de demostracion
php bin/console migrate    # solo el esquema
php bin/console seed       # solo los datos de demostracion
php bin/console fresh      # borra todo y reinstala
php bin/console key        # genera un APP_KEY
php bin/console hash Clave # genera un hash de contrasena
```

Con `make`: `make up`, `make install`, `make fresh`, `make logs`, `make down`.

---

## Despliegue

Para el servidor hay un archivo de composicion propio que compila el codigo
dentro de la imagen, fija OPcache, no levanta Adminer y no publica el puerto de
MySQL:

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose exec app php bin/console migrate
```

`.github/workflows/ci.yml` valida sintaxis, ejecuta la suite completa contra
MySQL 8.4 y construye la imagen de produccion en cada push.

Detalle en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).

---

## Estructura

```
psiclinic/
├── bin/console              Utilidades de linea de comandos
├── bin/test                 Corredor de la suite de pruebas
├── database/migrations/     Esquema SQL
├── docker/                  Dockerfile de PHP y configuracion de Nginx
├── docs/                    Guias de instalacion y publicacion
├── public/
│   ├── index.php            Front controller
│   └── assets/              CSS, JS y SVG
├── src/
│   ├── Core/                Router, Request, Response, Database, Auth, View
│   ├── Controllers/         Un controlador por modulo
│   ├── Domain/              Reglas de negocio y consultas
│   ├── Support/             Ayudantes, iconos, graficas, instalador
│   └── routes.php           Tabla de rutas
├── storage/                 Archivos subidos y logs
├── tests/
│   ├── Unit/                Pruebas sin base de datos
│   └── Feature/             Pruebas de integracion
└── views/                   Plantillas PHP por modulo
```

Flujo de una peticion:

```
public/index.php -> App::boot -> Router::dispatch -> Middleware -> Controller
                 -> Domain (consulta) -> View::render -> Response
```

---

## Base de datos

MySQL 8.4. Tablas principales: `users`, `patients`, `appointments`,
`clinical_notes`, `diagnoses`, `assessments`, `consents`, `documents`,
`invoices`, `invoice_items`, `payments`, `audit_log`, `settings`.

Motivo del cambio respecto a la peticion inicial de PostgreSQL: mantener el mismo
motor del ecosistema del que parte el modelo funcional evita reescribir tipos
`ENUM`, `JSON` y funciones de fecha. La capa de acceso a datos usa PDO, asi que
migrar a PostgreSQL implicaria solo ajustar `Database::connection()` y el archivo
de migracion.

---

## Advertencia clinica y legal

El uso de instrumentos psicometricos requiere formacion profesional. Los puntajes
son orientativos y no sustituyen el juicio clinico ni constituyen un diagnostico.
Antes de usar el sistema con datos reales, revisar la normativa local de
proteccion de datos de salud y la seccion [SECURITY.md](SECURITY.md).

---

## Licencia

Software propietario. Ver [LICENSE](LICENSE) y [NOTICE](NOTICE).
El aviso de autoria debe conservarse en cualquier copia o derivado.
