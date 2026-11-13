# Checkpoint

Estado canónico: [feature-list.json](feature-list.json). F-001..F-011 validated;
F-012 blocked, sin activa. Rama feat/012. [DEC-022](feedback/2026-11-13-DEC-022-f012-destino.md)
y [evidencia](evidence/2026-11-13-f012-compatibilidad.md).

Ronnie informó PHP 8.5/MariaDB 11.8.9-MariaDB-log y projecta.rvsoftware.site creado
en Hostinger. Runtimes oficiales portables y SHA256 comprobados, sin instalación
global. PHP 8.5.12/MariaDB -log: 36 tests/203 aserciones del plugin; HTTP concurrente,
permisos/CSV y restauración de ZIP con base/adjuntos pasan. Suite nativa PHP 8.5:
1273 tests/181101 aserciones, 1 warning, 33 skips del plugin; regresión PHP 8.3 /
SQLite: 36 tests/203 aserciones. FLOAT readonly y deadlock transitorio corregidos;
segundos/ledger exactos, sin nueva migración/core/vendor/libs.

Pendiente: verificar staging real (PHP/SAPI/extensiones, servidor/reglas HTTP y
recuperación). HEAD público da HTTPS 200, Server hcdn, X-Powered-By PHP/8.3.36;
no prueba el runtime de la futura app. No acceso ni carga de código en Hostinger.
Commit/push dev feature 012 con preparación actualizada; no marcar validated solo
por versiones informadas o tests locales. Producción requiere autorización separada.
