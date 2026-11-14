# DEC-012: comprobaciones manuales de F-004 confirmadas

Fecha: 2026-11-12T05:08:45-05:00.
Feature: F-004.
Evidencia: el usuario respondió «Si todo funciono» a la solicitud que enumeraba
carga de escritorio/móvil, foco con Tab, diálogo Nueva tarea y Escape, campos
avanzados, navegación del menú móvil y accesibilidad de controles a zoom 200%.

Decisión: aceptar ese resultado como verificación manual proporcionada por el
usuario. Se combina con controles estáticos de etiquetas, contraste y sintaxis;
F-004 satisface sus criterios y puede validarse. Aprobación de diseño: DEC-010.

Impacto: no se atribuyen esas interacciones al agente ni a la integración fallida.
No hay captura automatizada ni auditoría WCAG completa. Se conserva DEC-011:
solo integración Chrome, sin instalar Playwright local. Cerrar commit/push de
F-004 y continuar F-005, sin pedir de nuevo aprobación del diseño.

Siguiente acción: plugin propio compatible en feat/005; mantener controles
estáticos y pruebas HTTP/servidor. Verificar flujos implementados en cada feature.
