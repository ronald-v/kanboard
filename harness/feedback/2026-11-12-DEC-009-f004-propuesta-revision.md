# DEC-009: propuesta F-004 lista para revisión

Fecha: 2026-11-12T04:44:55-05:00.
Feature: F-004.
Evidencia: [comprobaciones](../evidence/2026-11-12-f004-diseno.md) y
[mockup](../mockups/f004/index.html).

Decisión técnica: propuesta en HTML/CSS/JS estático dentro del harness, con logos
oficiales y sin dependencias, para aprobar un resultado concreto antes de tocar
el producto. No interpretar DEC-003 ni la aprobación comercial DEC-008 como
aprobación de un diseño que el usuario todavía no ha visto.

Impacto: pruebas estáticas de etiquetas/contraste y sintaxis pasan; revisión visual
interactiva bloqueada por integración de Chrome. No implementar plugin/overrides
antes de satisfacer F-004. No crear commit de feature validada para un borrador.

Siguiente acción: mostrar enlaces al usuario, pedir revisión/aprobación y continuar
las comprobaciones visuales cuando el navegador esté operativo o haya evidencia
del usuario. La aprobación se registrará en una decisión nueva, sin alterar ésta.
