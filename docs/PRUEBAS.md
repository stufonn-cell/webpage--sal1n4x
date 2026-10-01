# Pruebas

El proyecto tiene dos suites: la del backend (PHP) y la del frontend (TypeScript).
Ambas se ejecutan en CI en cada push.

## Backend

La suite no depende de PHPUnit ni de Composer. El corredor está en
`backend/tests/Runner.php` y se invoca con `bin/test`.

---

## Ejecutar

```bash
docker compose exec app php bin/test           # todo
docker compose exec app php bin/test unit      # solo unitarias
docker compose exec app php bin/test feature   # solo de integracion
```

Sin Docker, con PHP instalado en el equipo:

```bash
php bin/test
```

Salida:

```
  PsiClinic - suite de pruebas

  InstrumentsTest
    [ok]   catalog exposes the six instruments
    [ok]   phq9 scores minimum and maximum
    ...

  167 correctas, 0 fallidas, 0 omitidas | 872 aserciones | 63.68 s
```

El proceso termina con codigo `1` si algo falla, lo que permite usarlo en CI.

---

## Que cubre cada archivo

### Unitarias (sin base de datos)

| Archivo | Cubre |
|---|---|
| `Unit/InstrumentsTest.php` | Puntuacion de los seis instrumentos, items inversos, subescalas, multiplicadores, items criticos, cobertura completa de bandas |
| `Unit/ValidatorTest.php` | Cada regla de validacion y la combinacion de varias |
| `Unit/RouterTest.php` | Coincidencia de rutas, parametros, verbo HTTP, suplantacion de metodo, middlewares y errores 404/405 |
| `Unit/RequestTest.php` | Normalizacion de entrada, casteos, archivos y atributos de ruta |
| `Unit/HelpersTest.php` | Escapado, iniciales, edad, fechas, moneda y generacion de UUID |
| `Unit/EnvTest.php` | Lectura del `.env`: comillas, comentarios en linea, booleanos y valores por defecto |

### De integracion (requieren MySQL)

| Archivo | Cubre |
|---|---|
| `Feature/SchemaTest.php` | Tablas, motor, cotejamiento, claves foraneas y transacciones |
| `Feature/AuthTest.php` | Ingreso por usuario o correo, cuentas inactivas, roles, bloqueo por intentos (persistente entre sesiones), auditoria, CSRF y su rotacion al ingresar |
| `Feature/PatientTest.php` | Numeracion de historias, busqueda, filtros, paginacion y linea de tiempo |
| `Feature/AppointmentTest.php` | Deteccion de choques de horario, vista semanal y proximas citas |
| `Feature/ClinicalNoteTest.php` | Numeracion de sesiones, busqueda en el cuerpo, curva de animo y firma |
| `Feature/AssessmentTest.php` | Correccion y persistencia de cada instrumento, series de evolucion y filtros |
| `Feature/BillingTest.php` | Totales, pagos parciales, saldo, cierre automatico y unicidad del numero |
| `Feature/InstallerTest.php` | Datos de demostracion, idempotencia, configuracion y metricas del panel |
| `Feature/ApiTest.php` | La API real (rutas, middlewares y controladores): sesion, ingreso, CSRF, permisos por rol, validacion por campo, valores de catalogo, choques de agenda, enlaces de videollamada, firma de notas, IDOR y XSS en consentimientos, cuestionarios del portal, sitio publico, solicitudes de cita con limite y campo trampa, y busqueda |

Si la base de datos no esta disponible, las pruebas de integracion se marcan
como omitidas en lugar de fallar.

---

## Base de datos de pruebas

Los comandos se ejecutan desde `backend/` (o dentro del contenedor `app`, donde `backend/` es la raiz).

Las pruebas usan `psiclinic_test`, creada automaticamente por
`docker/mysql/init/01-create-test-database.sql` la primera vez que arranca el
contenedor de MySQL. Nunca tocan la base de trabajo.

Para apuntar a otra base, definir en el `.env`:

```dotenv
DB_TEST_DATABASE=psiclinic_test
```

Cada prueba de integracion vacia las tablas antes de ejecutarse, de modo que el
orden no afecta el resultado.

---

## Agregar una prueba

```php
<?php

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Tests\TestCase;

final class MiFuncionalidadTest extends TestCase
{
    public function testDescribeLoQueDebeOcurrir(): void
    {
        $this->assertSame(2, 1 + 1);
    }
}
```

Reglas del corredor:

- El archivo termina en `Test.php` y vive en `tests/Unit` o `tests/Feature`.
- La clase extiende `TestCase` (o `FeatureTestCase` si necesita base de datos).
- Los metodos de prueba empiezan por `test`.
- `setUp()` y `tearDown()` se ejecutan alrededor de cada metodo.

Aserciones disponibles: `assertTrue`, `assertFalse`, `assertSame`,
`assertEquals`, `assertNull`, `assertNotNull`, `assertCount`, `assertContains`,
`assertGreaterThan`, `assertArrayHasKey` y `assertThrows`.

---

## Frontend

```bash
cd frontend
npm run typecheck   # TypeScript estricto en todo src/
npm test            # Vitest: formatos, catálogos y utilidades
npm run build       # compilación de producción
```

Con Docker, sin Node en el equipo: `bin/psiclinic test-front`.

| Archivo | Cubre |
|---|---|
| `src/lib/format.test.ts` | Fechas de MySQL, edad, saludo según la hora, iniciales, plurales, tamaños de archivo, tildes de etiquetas históricas, etiquetas y tonos de catálogo |

## Comprobación manual recomendada

Antes de publicar, recorrer en el navegador (escritorio y móvil):

1. Sitio público: navegación por secciones, menú móvil, servicios, preguntas,
   y una solicitud de cita completa (incluidos los errores de cada paso).
2. Ingreso con cada rol y cierre de sesión.
3. Equipo: aceptar la solicitud y crear la ficha, agendar una cita (y provocar
   un choque), escribir y firmar una nota, aplicar un instrumento, generar un
   consentimiento, subir un documento, facturar y registrar un pago.
4. Portal: responder el cuestionario asignado, firmar el consentimiento,
   descargar un documento.
5. Teclado: recorrer cada pantalla con `Tab`, abrir la búsqueda con `Ctrl+K`,
   navegar pestañas con las flechas y cerrar diálogos con `Escape`.
6. Activar *reducir movimiento* en el sistema y comprobar que no hay animaciones.

---

Hecho por Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
