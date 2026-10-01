# Step-by-step guide: WSL, Docker and publishing on GitHub

A complete guide to get PsiClinic running on a Windows PC and to push the
repository to the `stufonn-cell` account.

Estimated time: 30 to 40 minutes the first time.

---

## Part 1. Install WSL2

### 1.1 Requirements

- Windows 10 version 2004 or later, or Windows 11.
- Virtualization enabled in the BIOS (it usually already is).
- At least 8 GB of RAM.

### 1.2 Install

Open **PowerShell as administrator** (right-click the Start menu ->
Terminal (Admin)) and run:

```powershell
wsl --install -d Ubuntu-22.04
```

This command turns on the required features and installs Ubuntu. When it
finishes, **restart the computer**.

If the command says WSL is already installed, update it:

```powershell
wsl --update
wsl --set-default-version 2
wsl --install -d Ubuntu-22.04
```

### 1.3 First start

After the restart, the Ubuntu window opens. It asks you to create a user:

```
Enter new UNIX username: salinas
New password: ********
```

The password is not shown while you type it. It is the one you will use with
`sudo`.

### 1.4 Check

In PowerShell:

```powershell
wsl -l -v
```

It should show:

```
  NAME            STATE           VERSION
* Ubuntu-22.04    Running         2
```

The VERSION column must say `2`. If it says `1`:

```powershell
wsl --set-version Ubuntu-22.04 2
```

### 1.5 Update the system

Inside the Ubuntu terminal:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git make curl unzip
```

---

## Part 2. Install Docker Desktop

### 2.1 Download

Download Docker Desktop for Windows from <https://www.docker.com/products/docker-desktop/>
and install it with the **Use WSL 2 instead of Hyper-V** option checked.

### 2.2 Connect Docker to Ubuntu

Open Docker Desktop -> gear icon (Settings):

1. **General**: check *Use the WSL 2 based engine*.
2. **Resources -> WSL Integration**: turn on *Enable integration with my default
   WSL distro* and the **Ubuntu-22.04** switch.
3. Click **Apply & Restart**.

### 2.3 Check from Ubuntu

```bash
docker --version
docker compose version
docker run --rm hello-world
```

If `docker` is not found, close and reopen the Ubuntu terminal.

---

## Part 3. Get the project

### 3.1 Where to keep the files

Always work **inside the Linux file system** (`/home/<user>/`), not in
`/mnt/c/`. On `/mnt/c` Docker can be up to ten times slower.

```bash
mkdir -p ~/projects
cd ~/projects
```

### 3.2 Clone the repository

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic
```

If the remote repository does not exist yet, skip to Part 6 and come back here.

To work on the folder from VS Code on Windows:

```bash
code .
```

This needs the VS Code *WSL* extension.

---

## Part 4. Set up the .env file

### 4.1 Create the file

```bash
cp .env.example .env
```

`.env` is listed in `.gitignore` and must never be pushed to the repository.

### 4.2 Generate the application key

```bash
docker compose run --rm app php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Copy the result into the `APP_KEY` variable.

### 4.3 Full structure of .env

Edit it with `nano .env` (save with `Ctrl+O`, exit with `Ctrl+X`):

```dotenv
# --- Application ----------------------------------------------------------
APP_NAME="PsiClinic"          # Name shown in the interface
APP_ENV=local                 # local in development, production when deployed
APP_DEBUG=true                # false in production: hides error traces
APP_URL=http://localhost:8080 # Base URL
APP_TIMEZONE=America/Bogota   # Time zone for dates and the schedule
APP_KEY=                      # Key generated in step 4.2

# --- Database -------------------------------------------------------------
DB_HOST=mysql                 # Service name in docker-compose
DB_PORT=3306                  # Internal port on the Docker network
DB_DATABASE=psiclinic
DB_USERNAME=psiclinic
DB_PASSWORD=a_long_unique_password
DB_ROOT_PASSWORD=another_password_for_root
DB_CHARSET=utf8mb4

# --- Ports published on the host ------------------------------------------
APP_PORT=8080                 # Web interface
ADMINER_PORT=8081             # Database client
FRONTEND_PORT=5173            # Vite development server
DB_EXTERNAL_PORT=3307         # MySQL access from Windows (DBeaver, etc.)

# --- Session and security -------------------------------------------------
SESSION_NAME=psiclinic_session
SESSION_LIFETIME=7200         # Seconds of inactivity before signing out
SESSION_SECURE=false          # true only when served over HTTPS
LOGIN_MAX_ATTEMPTS=5          # Failed attempts before the lockout
LOGIN_LOCKOUT_SECONDS=900     # Lockout length

# --- Files ----------------------------------------------------------------
UPLOAD_MAX_SIZE=10485760      # 10 MB expressed in bytes
UPLOAD_PATH=storage/uploads

# --- Email (optional) -----------------------------------------------------
MAIL_ENABLED=false
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=no-reply@psiclinic.local
MAIL_FROM_NAME="PsiClinic"
```

Format rules:

- One variable per line, no spaces around the `=`.
- Quotes only when the value contains spaces.
- Text after `#` is ignored.
- `DB_HOST` must be `mysql`, not `localhost`: inside the Docker network,
  services are reached by their name.

---

## Part 5. Start the application

### 5.1 Build and start

```bash
make install
```

This compiles the frontend, starts every container and loads the schema, the
ICD-11 catalog and the demo data. The first time it downloads the PHP, Nginx,
Node, MySQL and Adminer images, so it takes a few minutes.

Without `make`:

```bash
docker compose run --rm --no-deps frontend sh -c "npm install && npm run build"
docker compose up -d --build
```

### 5.2 Check the containers

```bash
docker compose ps
```

All five services should show as `running`, and `psiclinic_mysql` as
`healthy`.

### 5.3 Create the schema and the data

The `app` container already runs `php bin/console install` every time it
starts (it is idempotent). To run it by hand:

```bash
docker compose exec app php bin/console install
```

The output looks like this:

```
Migration applied: 001_schema.sql
Migration applied: 002_public_site.sql
Migration applied: 003_icd11_rips.sql
ICD-11 catalog 2025-01 loaded: ... codes.
Demo data loaded.
  admin / Psiclinic2026
  l.moreno / Psiclinic2026
  mr-2026-0001 / Patient2026 (patient portal)
```

### 5.4 Open it in the browser

- Application: <http://localhost:8080> (sign in at <http://localhost:8080/login>)
- Frontend with hot reload: <http://localhost:5173>
- Adminer: <http://localhost:8081> (server `mysql`, user and password from `.env`)

### 5.5 Shortcut for the next starts

Containers do not survive a computer restart. To avoid typing the commands
every time, register the shortcut once:

```bash
cd ~/projects/psiclinic
chmod +x bin/psiclinic
echo "alias psiclinic='~/projects/psiclinic/bin/psiclinic'" >> ~/.bashrc
source ~/.bashrc
```

From then on, after each restart:

```bash
psiclinic up
```

The script starts the containers, waits until MySQL is ready, applies any
pending migrations and prints the URL. `psiclinic help` shows the full list
of commands.

For a desktop shortcut on Windows, use `bin/PsiClinic.bat`: it starts Docker
Desktop if needed, runs `psiclinic up` inside WSL and opens the browser. Edit
its `PROJECT_DIR` variable if you cloned the project somewhere other than
`~/projects/psiclinic`. To start PsiClinic automatically when you sign in to
Windows, put a shortcut to that file in the Startup folder (`Win+R`, then
`shell:startup`).

### 5.6 Everyday commands

```bash
docker compose logs -f app        # Watch PHP errors live
docker compose restart app        # Restart after changing php.ini
docker compose down               # Shut down (the data is kept)
docker compose up -d              # Start again
docker compose exec app sh        # Open a shell in the container
docker compose exec app php bin/console fresh   # Reset the database
```

PHP files are read live: after editing one, just reload the browser. Frontend
changes show up instantly on <http://localhost:5173>; run `psiclinic build` to
update the build served on port 8080.

---

## Part 6. Push the repository to GitHub

### 6.1 Set up Git

```bash
git config --global user.name "Salinas"
git config --global user.email "your-email@example.com"
git config --global init.defaultBranch main
```

The email must be the one linked to the `stufonn-cell` account so commits are
attributed correctly.

### 6.2 Authenticate with a token

GitHub does not accept passwords over HTTPS. Create a token:

1. Go to <https://github.com/settings/tokens> -> *Generate new token (classic)*.
2. Note: `psiclinic-wsl`. Expiration: 90 days.
3. Check the **repo** scope.
4. Generate and copy the token. It is shown only once.

Cache it so you do not have to type it again:

```bash
git config --global credential.helper "cache --timeout=604800"
```

### 6.3 Create the repository on GitHub

Go to <https://github.com/new> with the `stufonn-cell` account:

- Repository name: `psiclinic`
- Visibility: **Private** (recommended, it contains a clinical data model)
- Do **not** check *Add a README*, *Add .gitignore* or *Choose a license*: the
  project already has them.

### 6.4 Check before the first commit

```bash
cd ~/projects/psiclinic
git status --short
git check-ignore -v .env
```

The second command must confirm that `.env` is ignored. If it prints nothing,
**stop** and review `.gitignore` before you continue.

### 6.5 First commit and push

```bash
git init
git add .
git commit -m "Initial version of PsiClinic"
git branch -M main
git remote add origin https://github.com/stufonn-cell/psiclinic.git
git push -u origin main
```

When asked for credentials:

```
Username: stufonn-cell
Password: <paste the token, not the account password>
```

### 6.6 Later work

```bash
git add .
git commit -m "Add the AUDIT scale to the instruments catalog"
git push
```

Useful messages are in the imperative and describe the change, not the file
you touched.

### 6.7 If the repository already had content

```bash
git pull origin main --allow-unrelated-histories
```

Resolve any conflicts and run `git push` again.

### 6.8 Clone on another computer

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic
cp .env.example .env      # .env does not travel with the repository
nano .env                 # fill in the passwords and keys
make install
```

---

## Part 7. Common problems

| Symptom | Cause | Fix |
|---|---|---|
| `port is already allocated` | Another process is using 8080 | Set `APP_PORT=8090` in `.env` and run `docker compose up -d` |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL is still starting | Wait 30 s. Check with `docker compose ps` that it is `healthy` |
| `Access denied for user` | The passwords changed after the volume was created | `docker compose down -v` and `up` again (this deletes the data) |
| Blank page or "not found" on 8080 | The frontend has not been built | `psiclinic build` (or `make build`) |
| API error 500 | Hidden PHP error | `docker compose logs -f app` and `APP_DEBUG=true` |
| `403 Forbidden` in Nginx | Folder permissions | `docker compose exec app chmod -R 775 storage` |
| Changes do not show up | OPcache | `docker compose restart app` |
| Docker is very slow | Project in `/mnt/c/` | Move it to `~/projects/` inside WSL |
| `Permission denied` when uploading files | Directory owner | `docker compose exec app chown -R psiclinic:psiclinic storage` |
| `wsl: command not found` | WSL is not installed | Repeat Part 1 from PowerShell as administrator |
| `fatal: Authentication failed` | The password was used instead of the token | Repeat step 6.2 |

### Database backup

```bash
docker compose exec mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" psiclinic > backup.sql
```

Restore:

```bash
docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" psiclinic < backup.sql
```

### Uninstall completely

```bash
docker compose down -v
docker system prune -a
```

---

Made by Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
