# F-012 · Preparación local comprobada; destino pendiente

Fecha: 2026-11-12T23:02:00-05:00. Rama feat/012. Un agente, sin revisión independiente.

Entregables: exclusiones .gitattributes, Build-Release.py, Test-Release.py y
Test-Recovery.php; ensayo de migración; INSTALL/README del plugin actualizados,
cuadrícula responsive de métricas. No despliegue, remotos nuevos ni DB real.

## Resultados observados

- `powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1 -ContinueOnFailure`:
  exit 0, 1272 tests/181101 aserciones, 1 warning conocido y 32 skips de plugin
  deshabilitado. Ejecutada antes de agregar el test de upgrade (que se omite en
  modo nativo). Sin errores ni fallos. Duración 2m56s. Se conservan diagnósticos
  sintéticos esperados de file/webhook; no equivalen a fallos funcionales.
- `powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1 -Plugin -Filter RvSoftware`:
  exit 0, 33 tests/189 aserciones. Actualización plugin 4→6 conserva aprobación,
  duración y nota; no inventa bolsa/consumo, detecta diferencia y migra idempotente.
- `python harness/scripts/Test-RvSoftwareHttp.py`, `Test-RvSoftwareFlow.py`,
  `Test-RvSoftwareTime.py`, `Test-RvSoftwareReview.py`, `Test-RvSoftwareBags.py`,
  `Test-RvSoftwareLedger.py`, `Test-RvSoftwareReports.py`: todos exit 0. Login,
  Dashboard, tareas/subtareas, campos, viewer/miembro/líder/admin, CSRF, API,
  concurrencia real en dos procesos, aprobación, compensación, bolsas y CSV.
- `python harness/scripts/Build-Release.py --staged`: exit 0. Preview local ensayado
  e9d38cf94c97, 2130 archivos; SHA256
  733b4188baae6bb41b95e28419433d7298f757be893ae5560fcc7b15d5fb8eb5.
  Lectura real del ZIP comprueba ausencia de harness/Git/tests/config local/datos,
  archivos runtime/licencias presentes y hashes por archivo. Construcción repetida
  del mismo árbol devuelve el mismo checksum. Manifest incluye base exacta y tree;
  un preview no afirma ser commit final. Snapshot de commit se genera al entregar.
- `python harness/scripts/Test-Release.py .runtime/releases/kanboard-rvsoftware-e9d38cf94c97.zip`:
  exit 0. Extrae ZIP a ruta nueva; crea flujo proyecto/tarea/sesión 3600 s/bolsa
  7200 s/aprobación/reporte/adjunto sintéticos. Backup SQLite coherente + copia de
  archivos; modifica origen +300 s después. PHP del ZIP restaurado conserva saldo
  anterior 3600 s, totales, hash del adjunto, schema 6 e integridad/FK sin errores.
  HTTP del artefacto en 18008 ejecuta PHP, login/Home/reporte/descarga autorizada;
  ajeno no descarga. Git/harness/config/base/dumps no se sirven. DATA_DIR y archivos
  restaurados están fuera del document root. Proceso temporal se detiene al final.
- `powershell -NoProfile -File harness/scripts/Test-HarnessScenarios.ps1`: exit 0,
  18 fixtures, cero fallos, solo validador estático.

## Límites y dictamen

Criterio flujo local/recuperación/artefacto: comprobado. Destino compatible y
actualización en el destino real: no comprobados; falta ficha técnica y no hay
motor MySQL/MariaDB local disponible. La entrega conserva el upstream exacto;
una futura actualización a otra base requiere su propio ensayo. Las reglas efectivas del hosting no se
deducen del servidor PHP integrado. Sin capturas nuevas, auditoría WCAG ni nueva
prueba visual automatizada; continúa autorización DEC-015.

F-012 permanece blocked y ambos casos conservan esa limitación. No producir un
tag ni declarar release productivo. Para retomar: ficha de destino y entorno
aislado de staging; repetir migración/concurrencia y
reglas sensibles con restauración en ese motor. Producción requiere autorización
separada. No borrar datos para revertir; restaurar conjunto código/base/archivos.

Lint PHP: 37 archivos del plugin, todos exit 0. Un preview posterior conserva
permisos Unix de git archive (incluido cli); se repitieron ZIP y recuperación
HTTP/CLI con éxito. El manifiesto del snapshot final registra su commit exacto.
