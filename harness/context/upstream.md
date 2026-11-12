# Upstream y recuperación

## Base verificada
HEAD 60d2caf29edfa58e313b17cadaab76b6d4307742; origin fork ronald-v/kanboard;
rama main. git ls-remote https://github.com/kanboard/kanboard.git confirmó el mismo
HEAD oficial. Tag remoto v1.2.57 = 2ddb31bf72d355727d0639fc3423630f3bae1a9c;
dos commits posteriores, cambios de iCalendar/permisos y workflow Docker.
No se detectaron personalizaciones iniciales. Esta entrega añade solo AGENTS.md
y harness/. No se han creado ramas, remotos, commits ni plugins.

Versión propia `targetVersion` pendiente. No etiquetar esta copia como v1.2.57
exacta. app/functions.php puede generar main.unknown_revision en un checkout sin
APP_VERSION/version.txt ni sustituciones de git archive. Conservar commit en manifiesto.

## Propuesta
Crear rama rvsoftware/main y remoto upstream oficial cuando se autorice la
implementación; no cambiar origin. Inventario de extensiones actualmente vacío.
Cada excepción al núcleo debe tener registro de archivos, razón, pruebas y reversión.

## Actualización controlada
1. Revisar changelog, requisitos, diff y dependencias desde commit anterior exacto.
2. Preparar copia aislada con datos sintéticos/depurados autorizados.
3. Comparar hooks, firmas de servicios, ACL y plantillas reemplazadas.
4. Ejecutar suites pertinentes, migración en motor objetivo y casos de actualización.
5. Probar restauración de código, base y archivos antes de producción.
6. Registrar versión origen/destino, evidencia y autorización de release.

Revertir código no deshace migraciones. No ejecutar pull/update ciego ni editar
dependencias empaquetadas. Conservar LICENSE y avisos de terceros.
Fuente: [actualizaciones](https://docs.kanboard.org/v1/admin/upgrade/).
