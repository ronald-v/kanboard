# Pruebas y comandos reales

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
