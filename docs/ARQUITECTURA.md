# Arquitectura

Documento de referencia para entender y extender el codigo.

## Principios

1. **Sin magia.** No hay contenedor de dependencias ni ORM. Cada capa hace una
   sola cosa y se puede leer de arriba abajo.
2. **Sin build step.** No hay Composer ni npm. El autoloader es PSR-4 propio en
   `src/autoload.php` y el CSS y el JS se sirven tal cual.
3. **Consultas explicitas.** El SQL vive en la capa `Domain`, no repartido por
   los controladores.
4. **Escapado por defecto.** Toda salida en las vistas pasa por `e()`.

## Capas

```
public/index.php     Punto de entrada unico. Todo el trafico pasa por aqui.
src/Core/            Infraestructura: no conoce las reglas del negocio.
src/Domain/          Reglas del negocio y consultas. No imprime nada.
src/Controllers/     Orquestacion: valida, llama al dominio y elige la vista.
src/Support/         Ayudantes transversales: iconos, graficas, instalador.
views/               Presentacion. Solo lee datos, nunca consulta la base.
```

### src/Core

| Clase | Responsabilidad |
|---|---|
| `App` | Arranque: carga el `.env`, inicia la sesion y recoge datos flash |
| `Env` | Lectura tipada del archivo `.env` |
| `Database` | Envoltorio de PDO con `all`, `first`, `value`, `insert`, `update`, `transaction` |
| `Router` | Tabla de rutas con parametros `{id}` y middlewares por ruta |
| `Request` | Acceso normalizado a `$_GET`, `$_POST` y `$_FILES`, con soporte de `_method` |
| `Response` | Emision de HTML, JSON, redirecciones y descargas |
| `View` | Renderizado de plantillas PHP con layout |
| `Session` | Sesion, mensajes flash, errores y valores previos de formulario |
| `Auth` | Autenticacion, roles y limitacion de intentos |
| `Csrf` | Generacion y verificacion del token |
| `Validator` | Reglas encadenadas por campo |
| `Controller` | Clase base con `view`, `json`, `redirect`, `validate` |

### Middlewares

Se declaran por ruta en `src/routes.php` y se ejecutan antes del controlador.

- `VerifyCsrf`: obligatorio en `POST`, `PUT` y `DELETE`.
- `Authenticate`: exige sesion iniciada.
- `RequireStaff`: exige rol clinico o administrativo.
- `RequireAdmin`: exige rol de administrador.

## Ciclo de una peticion

```
Navegador
   v
public/index.php          Carga el autoloader y arranca App
   v
Router::dispatch          Compara metodo y ruta
   v
Middlewares               CSRF, sesion, rol
   v
Controller                Valida la entrada
   v
Domain                    Consulta o escribe en la base
   v
View::render              Compone plantilla y layout
   v
Response                  Envia el HTML
```

## Puntos de extension

**Agregar un instrumento psicometrico**

Editar `src/Domain/instruments.php` y anadir una entrada con `name`, `domain`,
`window`, `description`, `scale`, `items`, `bands`, y de forma opcional
`reverse`, `subscales`, `subscale_bands`, `multiplier` y `critical_items`. El
resto del sistema (formulario, correccion, graficas, portal) lo toma automatico.

**Agregar un modulo**

1. Crear la tabla en un nuevo archivo `database/migrations/00N_*.sql`.
2. Crear la clase de dominio en `src/Domain/`.
3. Crear el controlador en `src/Controllers/`.
4. Registrar las rutas en `src/routes.php`.
5. Crear las vistas en `views/<modulo>/`.
6. Anadir el enlace en `views/partials/sidebar.php`.

**Cambiar a PostgreSQL**

Ajustar el DSN en `Database::connection()`, cambiar los tipos `ENUM` por
`VARCHAR` con `CHECK`, `AUTO_INCREMENT` por `GENERATED ALWAYS AS IDENTITY` y
reemplazar `ON DUPLICATE KEY UPDATE` por `ON CONFLICT DO UPDATE` en
`Settings::put()` y en el instalador. El resto del codigo no cambia porque todo
el acceso pasa por PDO.

## Convenciones

- Clases y metodos con tipos declarados y `strict_types`.
- Nombres en ingles en el codigo, textos de interfaz en espanol.
- Metodos publicos primero, privados al final.
- Sin comentarios que repitan lo que dice el codigo.
- Vistas sin logica de negocio: solo presentacion y bucles.

---

Hecho por Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
