# DEC-029 ? Optimizaci?n de la espera local

Fecha: 2026-11-14T14:06:14-05:00. Feature: F-019.

Fuente: reporte de lentitud general, elecciones de entorno local/MariaDB, reducci?n por flujo, pruebas aisladas + uso normal, y ?Please implement the plan?.

Evidencia: inspecci?n est?tica del cargador de esquemas y benchmark previo F-013; no demuestra mejora nueva.

Decisi?n: implementar el plan aprobado, con comparaci?n antes/despu?s y objetivo >=50 % en mediana/p95 por flujo; automatizar exclusivamente en sint?ticos. Contrastar usando actividad normal, sin carga ni CRUD de prueba contra la base actual. Roles consultados por el mismo ejecutor, sin subagentes.

Impacto: nueva F-019 conserva F-013 hist?rica y bloqueos F-012/F-015. Permite excepci?n m?nima de n?cleo en SchemaHandler para evitar transacci?n/FK/upsert cuando no hay migraciones. No autoriza despliegue, cambios en datos reales ni infraestructura.

Siguiente: reproducir, medir, corregir el coste demostrado, verificar compatibilidad y documentar aceptaci?n real pendiente cuando corresponda.
