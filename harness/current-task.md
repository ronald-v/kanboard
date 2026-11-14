# Checkpoint

Estado canónico: [feature-list.json](feature-list.json). F-001 validated;
sin feature activa. Rama feat/001. Alcance ampliado en DEC-003.

## Último resultado
Entorno aislado reproducible, arranque HTTP y suite SQLite completados.
Corregidos almacenamiento y saneamiento de rutas Windows con regresión.
Evidencia: [F-001](evidence/2026-11-12-f001-entorno.md).

## Archivos
Scripts de preparación, tests, servidor y smoke HTTP en harness/scripts; README,
testing, estado y casos. FileStorage, resolución de rutas en app/functions.php y
regresiones. Vendor/libs/lock y PHP global sin cambios.

## Verificaciones
Composer install/check del lock: exit 0. Suite: 1240 tests/181101 aserciones,
exit 0, 1 warning (también aviso Windows por which). FileStorage 13/24 y regresión
de rutas/LDAP 22/50: exit 0. Login admin/miembro, proyecto vacío, Dashboard y rechazo
de consulta ajena comprobados por HTTP. 18 fixtures harness sin fallos.
Sin pruebas MySQL ni revisión independiente. Servidor detenido al cerrar.

## Siguiente paso
Crear feat/002 desde esta entrega. Usuario pide omitir el incidente externo del
Dashboard y acepta BR-01..10 propuestas; registrar cada decisión en su feature.
Luego F-003 y mockup F-004, con aprobación concreta antes de implementación.
Sin autorización de despliegue productivo ni destino MySQL configurado.
