# Pruebas y comandos reales

## Perfil aislado del destino PHP 8.5 / MariaDB 11.8.9

`python harness/scripts/Initialize-Compatibility.py` descarga ZIP portables
oficiales bajo .runtime/compatibility, verifica SHA256 y crea únicamente un
datadir sintético. No instala servicios Windows ni cambia PHP global.
`powershell -NoProfile -File harness/scripts/Start-CompatibilityDb.ps1` inicia
MariaDB en 127.0.0.1:13318, con binary log y credenciales exclusivamente sintéticas.
Initialize-CompatibilityDb.php verifica versión, puerto y datadir antes de crear
los tres schemas fijos; la cuenta de pruebas solo tiene permisos sobre ellos.

```powershell
powershell -NoProfile -File harness/scripts/Invoke-CompatibilityTests.ps1
powershell -NoProfile -File harness/scripts/Invoke-CompatibilityTests.ps1 -Native -Sqlite
powershell -NoProfile -File harness/scripts/Start-CompatibilityServer.ps1 -Port 18015
# En otro proceso dedicado:
powershell -NoProfile -File harness/scripts/Start-CompatibilityServer.ps1 -Port 18017
python harness/scripts/Test-CompatibilityHttp.py
# En un proceso que haga dot-source del perfil CompatibilityEnvironment.ps1:
python harness/scripts/Test-CompatibilityRecovery.py .runtime/releases/ARTEFACTO.zip
```

El runner respeta DB_PORT en el fixture nativo y genera XML exclusivo, sin
sobrescribir los originales. El modo plugin usa MariaDB; -Native -Sqlite usa el
PHP 8.5 portable con SQLite en memoria y plugin deshabilitado. La recuperación
usa mariadb-dump/import sobre un schema reservado diferente, archivos fuera del
document root y PHP/HTTP del ZIP extraído. No apuntar estos scripts al hosting.
Requisitos de desarrollo Composer siguen en .runtime/vendor, no vendor productivo.

## Entorno aislado autorizado en DEC-003
La línea base siguiente conserva el diagnóstico histórico. Desde F-001 se usan
extensiones GD/PDO SQLite/ZIP/LDAP por proceso; no se cambia php.ini global ni vendor/.
Dependencias exactas del lock en `.runtime/vendor` (ignorado por Git).
Ejecutar cada script en un proceso PowerShell dedicado; el helper cambia el
entorno de ese proceso y rechaza config.php/data/config.php no revisados.

```powershell
powershell -NoProfile -File harness/scripts/Initialize-TestEnvironment.ps1
powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1
powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1 -Filter DashboardPaginationTest
powershell -NoProfile -File harness/scripts/Start-IsolatedServer.ps1
# En otra consola, mientras el servidor está activo:
python harness/scripts/Test-IsolatedHttp.py
```

El servidor escucha exclusivamente en 127.0.0.1:18001; usa
`.runtime/data/db.sqlite`, adjuntos/caché y directorio de plugins vacíos aislados.
No reutiliza DATABASE_URL ni DB_DRIVER heredados; detener con Ctrl+C. Solo datos
sintéticos, admin/admin del esquema nuevo. El smoke no registra cookies ni tokens.
El test runner fuerza la configuración SQLite :memory: y el autoload aislado.
`-Plugin` en Invoke-UnitTests.ps1/Start-IsolatedServer.ps1 copia solo RvSoftware
al directorio `.runtime/plugins-enabled`; el modo nativo usa `plugins-disabled`.
HTTP de integración: servidores 18005 con plugin y 18006 sin él, luego
`python harness/scripts/Test-RvSoftwareHttp.py`. No es automatización de navegador.
MySQL no es necesario para esta línea base; sí se requiere validación en el motor
objetivo antes de una entrega que lo use. Resultados reales en evidence/.

## Línea base
Ver [evidencia](../evidence/2026-11-11-baseline.md). PHP 8.3.27, Composer 2.8.12.
composer validate retorna 0 con advertencias por versiones exactas; check-platform-reqs
--lock retorna 2 por GD ausente. PDO::getAvailableDrivers devuelve []. PHPUnit no
está instalado: comando de pruebas retorna 1 antes de ejecutar casos.
13 archivos seleccionados pasaron php -l en diagnóstico; no es una suite funcional.

## Comandos
Desde raíz; indicar siempre ejecutado/no ejecutado y destino.

| Comando | Estado / requisito |
| --- | --- |
| git status --short; git diff; git diff --cached | Inspección de cambios, sin escritura |
| composer --no-plugins --no-scripts validate --no-check-publish | Ejecutado, válido con advertencias |
| composer --no-plugins --no-scripts check-platform-reqs --lock | Ejecutado, falta GD; no comprueba driver objetivo implícito |
| php -r 'echo json_encode(PDO::getAvailableDrivers()), PHP_EOL;' | Ejecutado, [] |
| php -l app/Model/SubtaskTimeTrackingModel.php | Sintaxis, sin arranque ni DB |
| php vendor/bin/phpunit -c tests/units.sqlite.xml | Bloqueado; PHPUnit y pdo_sqlite faltan; DB :memory: |
| php vendor/bin/phpunit -c tests/units.sqlite.xml --filter SubtaskTimeTrackingModelTest | Planeado, tras preparar entorno |
| php vendor/bin/phpunit -c tests/units.sqlite.xml --filter DashboardPaginationTest | Planeado, tras preparar entorno |
| php vendor/bin/phpunit -c tests/units.mysql.xml | No ejecutado; recrea base, aislar destino |
| php vendor/bin/phpunit -c tests/units.postgres.xml | No ejecutado; recrea base, aislar destino |
| php vendor/bin/phpunit -c tests/integration.sqlite.xml | No ejecutado; HTTP 127.0.0.1 y API sintética |
| php -S localhost:8000 -t . | No iniciado; extensiones y configuración local antes |
| php cli css; php cli js | Existentes, no ejecutados; escriben bundles y cargan app |

composer install --prefer-dist es el mecanismo existente para dependencias de
desarrollo fijadas en lock. No ejecutado ni autorizado como parte de generar el
harness; no usar update ni ignorar requisitos para aparentar compatibilidad.
CI upstream: .github/workflows/unit_tests.yml, integration_tests.yml y linters.yml.
JSHint y PHP-CS-Fixer no encontrados en PATH; Docker/make tampoco. No inventar éxito.

## Estrategia
Unitarias sobre modelos/ACL/cálculo; integración sobre rutas/API y motor objetivo;
navegador para navegación y accesibilidad. Concurrencia con solicitudes/conexiones
independientes: un test secuencial no la demuestra. Comparar plugin deshabilitado
en una copia limpia cuando ayude a aislar un fallo. No conectar a bases reales.
Ejecutar pruebas relevantes una vez y repetir solo por cambios o fallos nuevos.

## Dashboard: protocolo pendiente
Registrar versión/URL sin secretos, usuario/rol sintético, ruta y datos previos.
Pasos: login, pulsar Dashboard desde origen reportado, observar URL, estado HTTP,
redirecciones y respuesta; revisar consola y logs pertinentes depurados.
Esperado: vista propia accesible, sin datos ajenos y estado vacío correcto.
Observado: solo reporte del usuario; no reproducción local/remota.
Hipótesis: rutas/rewrite, sesión/ACL, plugin, configuración o datos. Ninguna elegida.
Regresión: administrador/miembro/observador, sin proyectos, sin tareas, proyecto
revocado, parámetros user_id/project_id ajenos, navegación proyecto-tarea-tiempo-reporte.
No declarar resuelto al cambiar un enlace o reemplazar la pantalla.

## Evidencias y casos
Casos en ../test-cases/cases.json: feature, precondiciones, datos, pasos, comando
cuando existe, esperado, observado, estado, evidencia y limitación. Reglas no
decididas dejan caso bloqueado, nunca aprobado por una expectativa inventada.
Verificación estática del harness y fixtures en scripts son independientes de
pruebas del producto y de preparación productiva.
