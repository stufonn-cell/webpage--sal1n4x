# Pruebas

La suite no depende de PHPUnit ni de Composer. El corredor esta en
`tests/Runner.php` y se invoca con `bin/test`.

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

  86 correctas, 0 fallidas, 0 omitidas | 412 aserciones | 1.84 s
```

El proceso termina con codigo `1` si algo falla, lo que permite usarlo en CI.

---

## Que cubre cada archivo

### Unitarias (sin base de datos)

| Archivo | Cubre |
|---|---|
| `Unit/InstrumentsTest.php` | Puntuacion de los seis instrumentos, items inversos, subescalas, multiplicadores, items criticos, cobertura completa de bandas |
| `Unit/ValidatorTest.php` | Cada regla de validacion y la combinacion de varias |
| `Unit/RouterTest.php` | Coincidencia de rutas, parametros, verbo HTTP, suplantacion de metodo, middlewares y pagina 404 |
| `Unit/RequestTest.php` | Normalizacion de entrada, casteos, archivos y atributos de ruta |
| `Unit/HelpersTest.php` | Escapado, iniciales, edad, fechas, moneda y generacion de UUID |
| `Unit/ChartTest.php` | Generacion de SVG para barras, lineas, dona y medidor, mas los iconos |
| `Unit/EnvTest.php` | Lectura del `.env`: comillas, comentarios en linea, booleanos y valores por defecto |

### De integracion (requieren MySQL)

| Archivo | Cubre |
|---|---|
| `Feature/SchemaTest.php` | Tablas, motor, cotejamiento, claves foraneas y transacciones |
| `Feature/AuthTest.php` | Ingreso por usuario o correo, cuentas inactivas, roles, bloqueo por intentos, auditoria y CSRF |
| `Feature/PatientTest.php` | Numeracion de historias, busqueda, filtros, paginacion y linea de tiempo |
| `Feature/AppointmentTest.php` | Deteccion de choques de horario, vista semanal y proximas citas |
| `Feature/ClinicalNoteTest.php` | Numeracion de sesiones, busqueda en el cuerpo, curva de animo y firma |
| `Feature/AssessmentTest.php` | Correccion y persistencia de cada instrumento, series de evolucion y filtros |
| `Feature/BillingTest.php` | Totales, pagos parciales, saldo, cierre automatico y unicidad del numero |
| `Feature/InstallerTest.php` | Datos de demostracion, idempotencia, configuracion y metricas del panel |
| `Feature/PageRenderTest.php` | Renderizado real de cada pantalla, incluido el portal del paciente y la busqueda global |

Si la base de datos no esta disponible, las pruebas de integracion se marcan
como omitidas en lugar de fallar.

---

## Base de datos de pruebas

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

Hecho por Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
