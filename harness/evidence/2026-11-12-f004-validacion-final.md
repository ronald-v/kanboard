# F-004: cierre comprobado

Fecha: 2026-11-12T05:08:45-05:00. Rama feat/004.
Diseño aprobado expresamente en DEC-010. Resultado manual del usuario registrado
en DEC-012: «Si todo funciono» ante el protocolo concreto de escritorio/móvil,
Tab/foco, Nueva tarea/Escape, campos avanzados, menú móvil y zoom 200%.

Pruebas del agente: Test-F004Mockup.py exit 0 (26 controles etiquetados y nueve
contrastes); node --check exit 0; Test-Harness.ps1 exit 0; diff check exit 0.
HTTP 200 en puertos 8004 y 18004; inventario de Chrome confirmó título y URL del
mockup cargado. La integración falló al inspeccionar DOM/captura: no se atribuye
éxito automatizado. No hay revisión independiente ni auditoría WCAG completa.

Archivos: mockup/activos/CSS/JS, documentación, comprobador estático y estado/casos.
No modificaciones al producto en F-004. Recuperación: revertir su commit y detener
servidores estáticos si se requiere; sin DB ni migración. La versión aprobada queda
lista para integrar mediante plugin en F-005/F-006.
