# DEC-023 · Configuración y guía de despliegue

Fecha: 2026-11-14T08:16:10-05:00. Feature: F-012.

Evidencia: [configuración y documentación](../evidence/2026-11-14-f012-configuracion.md).
Ronnie solicitó un archivo de conexión y pasos de despliegue visibles en README.
Se añade una plantilla sin credenciales para el mecanismo nativo `data/config.php`
y se documentan también variables del entorno, su precedencia, preparación de
staging, migraciones, protección y recuperación. El README actualizado de `main`
se conserva en feat/012; no se integran sus demás cambios en esta rama.

Impacto: no se conecta ni migra una base real, ni se publica en Hostinger. F-012
continúa bloqueada hasta comprobar el entorno real. Siguiente: recibir acceso y
alcance para verificar staging y recuperación, con producción autorizada aparte.
