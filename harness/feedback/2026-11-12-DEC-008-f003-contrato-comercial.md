# DEC-008: contrato comercial inicial aceptado

Fecha: 2026-11-12T04:37:00-05:00.
Feature: F-003; impacto en F-007..F-011.
Evidencia: propuesta completa BR-01..10 enviada al usuario y respuesta textual
«Me gusta, vamos con esas». No se atribuye aprobación por silencio.

Decisión:
- BR-01: un cliente por proyecto; bolsas por proyecto.
- BR-02: horas fijas ampliables; vigencia opcional; sin renovación ni arrastre automático.
- BR-03: consumir al aprobar trabajo.
- BR-04: avisar y permitir excedente.
- BR-05/06: autor registra/corrige lo pendiente; líder o administrador clasifica,
  aprueba y rectifica lo aprobado, con auditoría.
- BR-07: reportes de horas, sin importes ni tarifas inicialmente.
- BR-08: miembro ve propias horas; líder horas y saldos de sus proyectos; administrador todo.
- BR-09: un temporizador por usuario; sesiones olvidadas se corrigen manualmente.
- BR-10: America/Bogota, filtros por fecha trabajada y correcciones auditadas,
  sin cierre mensual automático.

Impacto: contrato vigente en time-billing.md; separar trabajado, facturable,
consumido y facturado. Solo trabajo facturable aprobado genera consumo de bolsa;
no se implementa emisión de facturas. No compartir bolsas, calcular importes,
inventar históricos ni agregar políticas adicionales por defecto.

Siguiente acción: diseñar F-004; implementar cada dominio en su feature. Cualquier
caso comercial adicional se precisa allí; no bloquea diseño/inspección independiente.
Recuperación: decisión posterior versiona el contrato, sin borrar auditoría.
