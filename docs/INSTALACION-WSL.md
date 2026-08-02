# Guia paso a paso: WSL, Docker y publicacion en GitHub

Guia completa para dejar PsiClinic corriendo en un PC con Windows y para subir el
repositorio a la cuenta `stufonn-cell`.

Tiempo estimado: 30 a 40 minutos la primera vez.

---

## Parte 1. Instalar WSL2

### 1.1 Requisitos

- Windows 10 version 2004 o superior, o Windows 11.
- Virtualizacion habilitada en la BIOS (normalmente ya lo esta).
- 8 GB de RAM como minimo.

### 1.2 Instalar

Abrir **PowerShell como administrador** (clic derecho en el menu de inicio ->
Terminal (Administrador)) y ejecutar:

```powershell
wsl --install -d Ubuntu-22.04
```

Este comando activa las caracteristicas necesarias e instala Ubuntu. Al terminar,
**reiniciar el equipo**.

Si el comando responde que WSL ya esta instalado, actualizarlo:

```powershell
wsl --update
wsl --set-default-version 2
wsl --install -d Ubuntu-22.04
```

### 1.3 Primer arranque

Tras reiniciar, se abre la ventana de Ubuntu. Pide crear un usuario:

```
Enter new UNIX username: salinas
New password: ********
```

La contrasena no se muestra al escribirla. Es la que se usara con `sudo`.

### 1.4 Verificar

En PowerShell:

```powershell
wsl -l -v
```

Debe mostrar:

```
  NAME            STATE           VERSION
* Ubuntu-22.04    Running         2
```

La columna VERSION debe decir `2`. Si dice `1`:

```powershell
wsl --set-version Ubuntu-22.04 2
```

### 1.5 Actualizar el sistema

Dentro de la terminal de Ubuntu:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git make curl unzip
```

---

## Parte 2. Instalar Docker Desktop

### 2.1 Descargar

Descargar Docker Desktop para Windows desde <https://www.docker.com/products/docker-desktop/>
e instalarlo con la opcion **Use WSL 2 instead of Hyper-V** marcada.

### 2.2 Conectar Docker con Ubuntu

Abrir Docker Desktop -> icono de engranaje (Settings):

1. **General**: marcar *Use the WSL 2 based engine*.
2. **Resources -> WSL Integration**: activar *Enable integration with my default
   WSL distro* y el interruptor de **Ubuntu-22.04**.
3. Pulsar **Apply & Restart**.

### 2.3 Verificar desde Ubuntu

```bash
docker --version
docker compose version
docker run --rm hello-world
```

Si `docker` no se encuentra, cerrar y reabrir la terminal de Ubuntu.

---

## Parte 3. Obtener el proyecto

### 3.1 Ubicacion correcta de los archivos

Trabajar **siempre dentro del sistema de archivos de Linux** (`/home/usuario/`),
no en `/mnt/c/`. En `/mnt/c` el rendimiento de Docker cae hasta diez veces.

```bash
mkdir -p ~/proyectos
cd ~/proyectos
```

### 3.2 Clonar el repositorio

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic
```

Si aun no se ha creado el repositorio remoto, saltar a la Parte 6 y volver aqui.

Para trabajar con la carpeta desde VS Code en Windows:

```bash
code .
```

Requiere la extension *WSL* de VS Code.

---

## Parte 4. Configurar el archivo .env

### 4.1 Crear el archivo

```bash
cp .env.example .env
```

`.env` esta en `.gitignore` y nunca debe subirse al repositorio.

### 4.2 Generar la clave de aplicacion

```bash
docker compose run --rm app php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Copiar el resultado en la variable `APP_KEY`.

### 4.3 Estructura completa del .env

Editar con `nano .env` (guardar con `Ctrl+O`, salir con `Ctrl+X`):

```dotenv
# --- Aplicacion -----------------------------------------------------------
APP_NAME="PsiClinic"          # Nombre visible en la interfaz
APP_ENV=local                 # local en desarrollo, production al desplegar
APP_DEBUG=true                # false en produccion: oculta trazas de error
APP_URL=http://localhost:8080 # URL base
APP_TIMEZONE=America/Bogota   # Zona horaria de fechas y agenda
APP_LOCALE=es
APP_KEY=                      # Clave generada en el paso 4.2

# --- Base de datos --------------------------------------------------------
DB_HOST=mysql                 # Nombre del servicio en docker-compose
DB_PORT=3306                  # Puerto interno de la red de Docker
DB_DATABASE=psiclinic
DB_USERNAME=psiclinic
DB_PASSWORD=una_clave_larga_y_unica
DB_ROOT_PASSWORD=otra_clave_distinta_para_root
DB_CHARSET=utf8mb4

# --- Puertos publicados en el host ---------------------------------------
APP_PORT=8080                 # Interfaz web
ADMINER_PORT=8081             # Cliente de base de datos
DB_EXTERNAL_PORT=3307         # Acceso a MySQL desde Windows (DBeaver, etc.)

# --- Sesion y seguridad ---------------------------------------------------
SESSION_NAME=psiclinic_session
SESSION_LIFETIME=7200         # Segundos de inactividad antes de cerrar sesion
SESSION_SECURE=false          # true solo cuando se sirva por HTTPS
LOGIN_MAX_ATTEMPTS=5          # Intentos fallidos antes del bloqueo
LOGIN_LOCKOUT_SECONDS=900     # Duracion del bloqueo

# --- Archivos -------------------------------------------------------------
UPLOAD_MAX_SIZE=10485760      # 10 MB expresados en bytes
UPLOAD_PATH=storage/uploads

# --- Correo (opcional) ----------------------------------------------------
MAIL_ENABLED=false
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=no-reply@psiclinic.local
MAIL_FROM_NAME="PsiClinic"
```

Reglas del formato:

- Una variable por linea, sin espacios alrededor del `=`.
- Comillas solo si el valor contiene espacios.
- El texto despues de `#` se ignora.
- `DB_HOST` debe ser `mysql`, no `localhost`: dentro de la red de Docker los
  servicios se resuelven por su nombre.

---

## Parte 5. Levantar la aplicacion

### 5.1 Construir y arrancar

```bash
docker compose up -d --build
```

La primera vez descarga las imagenes de PHP, Nginx, MySQL y Adminer. Tarda unos
minutos.

### 5.2 Verificar los contenedores

```bash
docker compose ps
```

Los cuatro servicios deben aparecer como `running`, y `psiclinic_mysql` como
`healthy`.

### 5.3 Crear el esquema y los datos

```bash
docker compose exec app php bin/console install
```

Salida esperada:

```
Migracion aplicada: 001_schema.sql
Datos de demostracion cargados.
  admin / Psiclinic2026
  l.moreno / Psiclinic2026
  hc-2026-0001 / Paciente2026 (portal del paciente)
```

### 5.4 Abrir en el navegador

- Aplicacion: <http://localhost:8080>
- Adminer: <http://localhost:8081> (servidor `mysql`, usuario y clave del `.env`)

### 5.5 Atajo para los siguientes arranques

Los contenedores no sobreviven a un reinicio del equipo. Para no repetir los
comandos cada vez, registrar el atajo una sola vez:

```bash
cd ~/proyectos/psiclinic
chmod +x bin/psiclinic
echo "alias psiclinic='~/proyectos/psiclinic/bin/psiclinic'" >> ~/.bashrc
source ~/.bashrc
```

A partir de ahi, tras cada reinicio:

```bash
psiclinic up
```

El script levanta los contenedores, espera a que MySQL este listo, aplica las
migraciones pendientes e imprime la URL. `psiclinic` sin argumentos muestra la
lista completa de comandos.

Para un acceso directo en el escritorio de Windows y arranque automatico al
iniciar sesion, ver la seccion correspondiente del README.

### 5.6 Comandos de uso diario

```bash
docker compose logs -f app        # Ver errores de PHP en vivo
docker compose restart app        # Reiniciar tras cambiar php.ini
docker compose down               # Apagar (los datos se conservan)
docker compose up -d              # Volver a arrancar
docker compose exec app sh        # Entrar al contenedor
docker compose exec app php bin/console fresh   # Reiniciar la base de datos
```

Los archivos PHP se leen en caliente: al editarlos, basta con recargar el
navegador.

---

## Parte 6. Subir el repositorio a GitHub

### 6.1 Configurar Git

```bash
git config --global user.name "Salinas"
git config --global user.email "tu-correo@ejemplo.com"
git config --global init.defaultBranch main
```

El correo debe ser el asociado a la cuenta `stufonn-cell` para que los commits se
atribuyan correctamente.

### 6.2 Autenticacion con token

GitHub no acepta contrasena por HTTPS. Crear un token:

1. Entrar en <https://github.com/settings/tokens> -> *Generate new token (classic)*.
2. Nota: `psiclinic-wsl`. Expiracion: 90 dias.
3. Marcar el permiso **repo**.
4. Generar y copiar el token. Solo se muestra una vez.

Guardarlo en cache para no reescribirlo:

```bash
git config --global credential.helper "cache --timeout=604800"
```

### 6.3 Crear el repositorio en GitHub

Entrar en <https://github.com/new> con la cuenta `stufonn-cell`:

- Repository name: `psiclinic`
- Visibilidad: **Private** (recomendado, contiene un modelo de datos clinicos)
- **No** marcar *Add a README*, *Add .gitignore* ni *Choose a license*: el
  proyecto ya los trae.

### 6.4 Comprobar antes del primer commit

```bash
cd ~/proyectos/psiclinic
git status --short
git check-ignore -v .env
```

El segundo comando debe confirmar que `.env` esta ignorado. Si no imprime nada,
**detenerse** y revisar el `.gitignore` antes de continuar.

### 6.5 Primer commit y publicacion

```bash
git init
git add .
git commit -m "Version inicial de PsiClinic"
git branch -M main
git remote add origin https://github.com/stufonn-cell/psiclinic.git
git push -u origin main
```

Al pedir credenciales:

```
Username: stufonn-cell
Password: <pegar el token, no la contrasena de la cuenta>
```

### 6.6 Trabajo posterior

```bash
git add .
git commit -m "Agrega escala AUDIT al catalogo de instrumentos"
git push
```

Mensajes utiles: en imperativo, describiendo el cambio, no el archivo tocado.

### 6.7 Si el repositorio ya tenia contenido

```bash
git pull origin main --allow-unrelated-histories
```

Resolver conflictos si aparecen, y volver a hacer `git push`.

### 6.8 Clonar en otro equipo

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic
cp .env.example .env      # el .env no viaja en el repositorio
nano .env                 # rellenar claves
docker compose up -d --build
docker compose exec app php bin/console install
```

---

## Parte 7. Problemas frecuentes

| Sintoma | Causa | Solucion |
|---|---|---|
| `port is already allocated` | Otro proceso usa el 8080 | Cambiar `APP_PORT=8090` en `.env` y `docker compose up -d` |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL aun arrancando | Esperar 30 s. Verificar con `docker compose ps` que este `healthy` |
| `Access denied for user` | Se cambiaron las claves con el volumen ya creado | `docker compose down -v` y volver a `up` (borra los datos) |
| Pagina en blanco | Error de PHP oculto | `docker compose logs -f app` y `APP_DEBUG=true` |
| `403 Forbidden` en Nginx | Permisos de la carpeta | `docker compose exec app chmod -R 775 storage` |
| Cambios que no se reflejan | Cache de OPcache | `docker compose restart app` |
| Docker muy lento | Proyecto en `/mnt/c/` | Mover a `~/proyectos/` dentro de WSL |
| `Permission denied` al subir archivos | Propietario del directorio | `docker compose exec app chown -R psiclinic:psiclinic storage` |
| `wsl: command not found` | WSL no instalado | Repetir la Parte 1 desde PowerShell como administrador |
| `fatal: Authentication failed` | Se uso la contrasena en vez del token | Repetir el paso 6.2 |

### Copia de seguridad de la base de datos

```bash
docker compose exec mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" psiclinic > respaldo.sql
```

Restaurar:

```bash
docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" psiclinic < respaldo.sql
```

### Desinstalar por completo

```bash
docker compose down -v
docker system prune -a
```

---

Hecho por Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
