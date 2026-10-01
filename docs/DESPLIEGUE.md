# Despliegue

El proyecto usa dos archivos de composicion: `docker-compose.yml` para el
entorno local y `docker-compose.prod.yml` para el servidor. Son archivos
independientes, no una sobrecapa, para que ningun volumen de desarrollo pueda
sobreescribir el codigo que viaja dentro de la imagen.

---

## Diferencias entre entornos

| Aspecto | Local | Produccion |
|---|---|---|
| Backend | `backend/` montado como volumen | copiado dentro de la imagen `app` |
| Frontend | Vite en 5173 con recarga + `frontend/dist` en 8080 | compilado dentro de la imagen `web` (Nginx) |
| Etapa del Dockerfile | `development` | `production` |
| Puerto web | `0.0.0.0:8080` | `127.0.0.1:8080` detras del proxy |
| OPcache | revalida archivos | cache fija, sin revalidacion |
| Adminer | publicado en 8081 | no se levanta |
| Puerto de MySQL | publicado en 3307 | sin publicar |
| `APP_DEBUG` | `true` | `false` |
| `SESSION_SECURE` | `false` | `true` |
| Reinicio | `unless-stopped` | `always` |

---

## Lista de comprobacion previa

1. `APP_ENV=production` y `APP_DEBUG=false`.
2. `APP_KEY` generado con `php bin/console key`.
3. Contrasenas de base de datos distintas a las del `.env.example`.
4. Contrasenas de demostracion cambiadas o cuentas eliminadas.
5. `SESSION_SECURE=true` y certificado TLS delante de Nginx.
6. `APP_URL` con el dominio real.
7. Copia de seguridad programada del volumen `mysql_data`.
8. Suite de pruebas en verde: `php bin/test`.

---

## Primer despliegue

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic

cp .env.example .env
nano .env                      # ajustar segun la lista de comprobacion

docker compose -f docker-compose.prod.yml up -d --build
docker compose exec app php bin/console migrate
```

En produccion se ejecuta `migrate`, no `install`: los datos de demostracion no
deben cargarse.

Crear la primera cuenta de administrador:

```bash
docker compose exec app php -r '
require "/var/www/html/src/autoload.php";
PsiClinic\Core\Env::load("/var/www/html/.env");
PsiClinic\Core\Database::insert("users", [
    "uuid" => uuid(),
    "username" => "admin",
    "email" => "admin@tudominio.com",
    "password_hash" => password_hash("CAMBIA-ESTA-CLAVE", PASSWORD_DEFAULT),
    "full_name" => "Administrador",
    "role" => "admin",
]);
echo "Cuenta creada", PHP_EOL;
'
```

---

## Actualizaciones

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose exec app php bin/console migrate
```

Las migraciones son idempotentes: reejecutarlas sobre una base existente no
duplica tablas ni restricciones.

---

## HTTPS

La forma mas simple es poner un proxy inverso delante:

```nginx
server {
    listen 443 ssl http2;
    server_name psiclinic.tudominio.com;

    ssl_certificate     /etc/letsencrypt/live/psiclinic.tudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/psiclinic.tudominio.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Con el proxy activo, poner `SESSION_SECURE=true` y reiniciar `app`.

El Nginx del contenedor `web` toma la IP real del visitante de `X-Forwarded-For`
(solo cuando la petición llega desde una red privada, como el proxy). Es
importante: los límites de intentos de ingreso y de solicitudes de cita se
calculan por IP. Si el proxy no reenvía esa cabecera, todas las personas
parecerían venir de la misma dirección.

---

## Copias de seguridad

```bash
# Volcado diario
docker compose exec -T mysql mysqldump \
  -u root -p"$DB_ROOT_PASSWORD" --single-transaction psiclinic \
  | gzip > "respaldo-$(date +%F).sql.gz"

# Archivos adjuntos
tar czf "archivos-$(date +%F).tar.gz" backend/storage/uploads
```

Restaurar:

```bash
gunzip < respaldo-2026-08-01.sql.gz \
  | docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" psiclinic
```

---

## Integracion continua

`.github/workflows/ci.yml` se ejecuta en cada push y pull request sobre `main`:

1. `php -l` sobre todo el backend y suite de pruebas contra MySQL 8.4.
2. Frontend: `npm ci`, comprobación de tipos, Vitest y compilación.
3. Construcción de las dos imágenes de producción (`app` y `web`).

---

## Migrar a PostgreSQL

Todo el acceso a datos pasa por PDO, asi que el cambio se concentra en tres
puntos:

1. `Database::connection()`: cambiar el DSN a `pgsql:`.
2. `database/migrations/001_schema.sql`: reemplazar `ENUM` por `VARCHAR` con
   `CHECK`, `AUTO_INCREMENT` por `GENERATED ALWAYS AS IDENTITY` y `JSON` por
   `JSONB`.
3. `Settings::put()` e `Installer`: cambiar `ON DUPLICATE KEY UPDATE` por
   `ON CONFLICT (setting_key) DO UPDATE SET`.

Las consultas del resto del dominio usan SQL estandar salvo
`DATE_FORMAT`, `DATE_SUB` y `FIELD` en `Metrics` y `DashboardController`, que
tienen equivalentes directos en PostgreSQL.

---

Hecho por Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
