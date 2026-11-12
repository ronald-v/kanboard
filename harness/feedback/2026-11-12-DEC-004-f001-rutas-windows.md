# DEC-004: excepción mínima del núcleo para F-001

Fecha: 2026-11-12T04:33:02-05:00.
Feature: F-001.
Evidencia: ejecución real de 358 tests; FileStorageTest::testPutAndGetWithSubdirectory
falla en Windows al escribir subdir/test.txt. createFolder busca exclusivamente
DIRECTORY_SEPARATOR, aunque las claves nativas contienen barras `/`.

Decisión: calcular dirname sobre getSanitizedFilePath, reutilizando saneamiento y
validación existentes. Archivo: app/Core/ObjectStorage/FileStorage.php, una línea.
Configuración y plugin no pueden corregir el servicio que la suite instancia
directamente; no tocar vendor/libs ni relajar validación de rutas.

Impacto: creación de carpetas consistente con la ruta que put/moveFile escriben.
Compatibilidad upstream: conservar este cambio al actualizar o quitarlo cuando
upstream incorpore equivalente. Recuperación: revertir la línea del commit de
F-001; no implica migraciones ni cambios de datos externos.

Siguiente acción: FileStorageTest completo y suite SQLite completa, registrando
fallos residuales de plataforma/versión sin convertirlos en pruebas aprobadas.
