# DEC-014: integrar diseño conservando flujos nativos

Fecha: 2026-11-12T05:22:08-05:00.
Feature: F-006; diseño aprobado DEC-010/012, plugin F-005 validado.

Decisión: sidebar por hook, estilos acotados, Inicio/Proyectos propios sobre
membresías nativas y enlaces a tableros, tareas, tiempo, actividad y administración.
No publicar destinos comerciales vacíos antes de sus features. Mantener listado
y Dashboard completos en accesos secundarios, incluida navegación móvil.

Overrides mínimos: layout envuelve kanboard:layout y retira exclusivamente la
restricción de zoom; task_creation/show reorganiza helpers nativos sin perder
campos, CSRF, hooks ni acciones. Sin excepciones del núcleo ni CSS de ocultación
usado como autorización. Identidades/títulos se escapan en servidor.

Impacto: getActiveProjectIds conserva membresía directa/grupos; proyecto revocado
desaparece del Inicio. Controles de creación verifican permisos nativos. Tablero
mantiene implementación y capacidades upstream. No modifica DB ni reglas de horas.

Siguiente acción: verificar flujo real y revisión visual de implementación por
usuario en Chrome, dado fallo de integración. Recuperación: deshabilitar plugin
o revertir commit; comparar wrappers/helpers al actualizar upstream.
