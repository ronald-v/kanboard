# DEC-003: desarrollo secuencial autorizado

Fecha: 2026-11-12T04:22:13-05:00.
Features: F-001..F-012.
Evidencia: instrucción del usuario «Desarrolla todas las features, una a la vez»;
crear/usar `feat/[número]`, validar, commit `dev feature [número]` y cargar cambios.
El usuario indica que aún no tiene DB y puede crearla en MySQL.

Decisión: autorización de implementación reversible, preparación de pruebas,
ramas, commits y push al origin existente. Ramas encadenadas desde la última
entrega para conservar dependencias; numeración 001..012 según IDs canónicos.
Sin cambio de remotos, merge a main ni despliegue productivo. No aprueba reglas
comerciales pendientes ni sustituye la aprobación concreta del diseño.

Impacto: F-001 se retoma con extensiones PHP por proceso, Composer del lock en
`.runtime/vendor`, SQLite sintético y scripts reproducibles. No modificar PHP
global, dependencias vendorizadas ni crear una base real. Los registros históricos
siguen describiendo su momento; esta decisión amplía DEC-001/002.

Siguiente acción: ejecutar suite y arranque aislados; luego F-002. Solicitar datos
del incidente y decisiones de negocio mientras se realiza trabajo independiente.
