# DEC-021 · Preparación local y condiciones de release

Fecha: 2026-11-12T23:02:00-05:00. F-012.
Evidencia: [validación local](../evidence/2026-11-12-f012-preparacion.md).

Decisión: completar empaquetado, flujo integrado, migración y restauración locales
dentro de la autorización vigente. El paquete usa git archive de árbol/commit
exacto, exclusiones, hashes por archivo, manifiesto y SHA256. Es desarrollo;
targetVersion sigue null y productionAuthorized=false, sin tag ni despliegue.

Se conserva F-012 blocked para sus criterios pendientes: ficha técnica del destino
(PHP, motor/versión, servidor/reglas), ensayo en ese motor y staging, sobre la base upstream exacta ya fijada. Responsable de
identificar destino: Ronnie; ejecución de ensayo: agente una vez definido
un entorno aislado autorizado. Se solicitó la ficha sin detener trabajo local.

Impacto: no presentar SQLite/PHP integrado como compatibilidad Hostinger/MySQL ni
la migración plugin 4→6 como una actualización a otro upstream. Esta entrega
conserva la base exacta; una futura actualización upstream es trabajo separado.
TC-F-012-01/02
conservan evidencia parcial y bloqueo, no se reducen criterios para cerrarlos.
El commit dev feature 012 entrega preparación local comprobada y registra estos
pendientes; no significa que la feature ni producción estén validadas.

Siguiente: recibir ficha del destino, reproducir motor en aislado y staging,
probar reglas HTTP, actualización y recuperación allí. Producción requiere su
autorización separada y recuperación verificada; no está autorizada por esta tarea.
