# DEC-005: completar corrección de rutas observada en F-001

Fecha: 2026-11-12T04:33:02-05:00.
Feature: F-001; amplía DEC-004 por evidencia adicional.
Evidencia: el siguiente test del mismo almacenamiento falla porque `../` no se
normaliza en Windows. La comprobación por prefijo también admite un hermano cuyo
nombre empieza con el nombre del directorio base.

Decisión: normalizar barras de las claves al separador nativo antes del saneamiento
y exigir el separador final en el límite del directorio. Mantener creación de
carpetas a partir de la ruta saneada. Sin cambios globales a sanitize_path ni libs.

Impacto: tres líneas de FileStorage.php y dos regresiones nuevas en
tests/units/Core/ObjectStorage/FileStorageTest.php. Las trece pruebas de ese archivo
pasan (24 aserciones, exit 0), incluidas traversal, put/get/remove y move anidado.
El fallo previo de diez tests no se oculta; motivó esta corrección.

Siguiente acción: completar suite de base y documentar límites. Recuperación:
reversión del commit F-001, sin migración ni escritura externa; seguir comprobando
estos tres puntos al incorporar una actualización upstream.
