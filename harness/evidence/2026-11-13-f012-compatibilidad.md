# F-012 · PHP 8.5 y MariaDB 11.8.9

Fecha: 2026-11-13T05:07:00-05:00. Rama feat/012. Un agente implementa y verifica.

## Destino y entorno

Usuario: PHP 8.5, MariaDB 11.8.9-MariaDB-log, Hostinger y
projecta.rvsoftware.site ya creado. HEAD público sin autenticación: HTTPS 200,
Server hcdn, X-Powered-By PHP/8.3.36. Solo se leyó esa respuesta; no se subió
código, conectó a una base real ni modificó DNS/cuentas/hPanel.

ZIP oficiales portables, fuera del producto en .runtime/compatibility:

- [PHP Windows](https://www.php.net/downloads.php?os=windows),
  php-8.5.12-1-nts-Win32-vs17-x64.zip; runtime PHP 8.5.12.
  SHA256 2a36a2edcb7a5b9252f638fd5e4cf84d7f6e066092654220dc0b63e5cf4e9723.
- [MariaDB 11.8.9](https://dlm.mariadb.com/browse/mariadb_server/11.8.9/winx64-packages/),
  mariadb-11.8.9-winx64.zip, comparado con su sha256sums.txt oficial.
  SHA256 830c46727d9278eae212ae3eca44eeb9e71b2a68704e95f344a64fba7b1963f5.

MariaDB se inicia exclusivamente en 127.0.0.1:13318 con datadir dedicado y binary
log (identidad observada 11.8.9-MariaDB-log). Preflight verifica datadir/versión/puerto;
cuenta sintética limitada a kanboard_unit_test, kanboard_compat_http y
kanboard_compat_restore. PHP 8.5 usa -n y extensiones explícitas por proceso.

## Comandos y resultados observados

- `python harness/scripts/Initialize-Compatibility.py`: exit 0, hashes
  verificados; instalación portable, sin servicios ni cambios de PHP global.
- Composer bajo el PHP portable, `check-platform-reqs --lock`: exit 0.
- `Test-CompatibilityBootstrap.php` con el perfil: exit 0; creación desde cero
  de esquema nativo y plugin schema 6 sobre MariaDB 11.8.9.
- `powershell -NoProfile -File harness/scripts/Invoke-CompatibilityTests.ps1`:
  exit 0, PHP 8.5.12 / MariaDB 11.8.9-MariaDB-log, **36 tests/203 aserciones**.
  Incluye migración plugin 4→6, precision en segundos, timers, permisos, aprobación,
  ledger, correcciones, savepoints y rollback; retry de deadlock único, límite de
  tres intentos sin escrituras persistidas, y sin retry de transacción exterior.
- `powershell -NoProfile -File harness/scripts/Invoke-CompatibilityTests.ps1 -Native -Sqlite`:
  exit 0, PHP 8.5.12, 1273 tests/181101 aserciones, 1 warning conocido y 33 skips
  del plugin deshabilitado. Ejecutada antes de agregar tres pruebas de deadlock;
  estas sí se ejecutaron en la suite final del plugin. No atribuir esta suite
  SQLite al motor MariaDB: ese motor se comprueba por la suite/HTTP específicos.
- Regresión `Invoke-UnitTests.ps1 -Plugin -Filter RvSoftware` en PHP 8.3/SQLite:
  exit 0, 36 tests/203 aserciones.
- Servidores Start-CompatibilityServer.ps1 en 18015/18017 y
  `python harness/scripts/Test-CompatibilityHttp.py`: exit 0, dos corridas
  consecutivas tras el ajuste de locks. Procesos PHP/PDO independientes:
  registro manual y aprobación idempotentes, timer único/start/stop duplicados,
  rectificación simultánea con aprobación de otro usuario, ledger reconciliado.
  UI/CSRF, roles/URLs/API, proyecciones, reporte Bogotá y CSV por segundos,
  neutralización de fórmulas y autorización de adjuntos comprobados.
- `Build-Release.py --staged`: preview 60109bc106ae, 2130 archivos; SHA256
  9a02acb3fc4a56f725df912911c5f3c0c9b8da8a3b44e258c4dac4ed6bf1301e.
- `Test-CompatibilityRecovery.py` con ese ZIP y el perfil: exit 0. Backup lógico
  nativo con mariadb-dump + adjuntos; mutación del origen después; importación en
  otro schema. PHP/HTTP del ZIP extraído recupera reporte/saldo exactos, auditoría,
  hash de adjunto, schema 6 y tablas rv_* InnoDB. Datos fuera del document root.
  Rutas de desarrollo/config/dumps ausentes físicamente; el servidor PHP puede
  devolver login al pedir una ruta inexistente. No se confunde ese fallback con
  una regla de denegación efectiva de Hostinger. Proceso HTTP temporal detenido.
- Lint PHP 8.5: 40 archivos del plugin/perfil, exit 0; py_compile de scripts,
  exit 0. `git diff --check` y `--cached --check`: exit 0.
- `Test-Harness.ps1`: exit 0, coherencia estática. `Test-HarnessScenarios.ps1`:
  exit 0, 18 fixtures y cero fallos; no son pruebas funcionales de Kanboard.

## Fallos encontrados y corrección

Primera suite MariaDB: dos igualdades de FLOAT fallaron por representaciones como
1.0199999995529652 frente a 1.02. Se usan tolerancias solo para esa proyección
nativa, y una comparación de centésimas para campos readonly descartados. Nueva
prueba asegura que editar el título no altera 3673 segundos de trabajo.

Contención HTTP con binary log produjo 40001. Se añadió orden de bloqueo de tarea
y retry transaccional acotado en operaciones exclusivamente de base, probado sin
duplicados ni pérdida. Tras cambios, suite y dos corridas HTTP pasan.

El primer ensayo de rutas ausentes esperaba siempre HTTP 404; PHP 8.5 puede
redirigir al login. Se comprobó ausencia real en ZIP/document root y respuesta de
login, sin afirmar que esto pruebe las reglas del hosting. No se ocultó un archivo
servido ni se relajaron permisos. No se cambió CSP/autenticación o integridad.

## Dictamen y recuperación

Compatibilidad local con los runtimes indicados: comprobada. Condiciones reales
del hosting (PHP/SAPI/extensiones, servidor de origen, reglas HTTP y recuperación
en staging): pendientes. F-012 sigue blocked con causa más precisa; se recibió
la ficha parcial del usuario, no autorización de producción. Sin captura visual
nueva ni revisión independiente. Mantener respaldo conjunto antes de una conexión
real; no eliminar ledger/tablas para revertir. El snapshot final se construye
desde el nuevo commit con manifiesto y checksum, sin binarios portables o pruebas.
