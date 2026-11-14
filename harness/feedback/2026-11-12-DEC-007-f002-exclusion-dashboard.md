# DEC-007: excluir incidente externo del Dashboard

Fecha: 2026-11-12T04:35:30-05:00.
Feature: F-002.
Evidencia: respuesta del usuario al solicitar reproducción: «Omite ese error para
este proyecto». Base local validada en [F-001](../evidence/2026-11-12-f001-entorno.md).

Decisión: retirar del alcance la reproducción y corrección del incidente externo.
F-002 se cierra como exclusión expresamente aceptada, sin cambio del Dashboard y
sin afirmar que el incidente fue corregido. Se sustituyen sus criterios por
documentar exclusión, conservar comportamiento nativo y no alterar permisos.

Impacto: solo estado, caso y documentación; no cambia PHP, ACL ni navegación.
El flujo integrado posterior conserva las regresiones locales del Dashboard.
No se ha ejecutado la matriz de regresión original por roles sobre aquel incidente.

Siguiente acción: F-003 en feat/003, incorporando BR-01..10 aceptadas por el usuario.
Recuperación: si el usuario reincorpora el incidente, abrir decisión nueva y
reproducción con información verificable, sin sobrescribir esta exclusión.
