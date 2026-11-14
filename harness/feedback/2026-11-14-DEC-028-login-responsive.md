# DEC-028 · Corrección visual de acceso y alta MFA

Fecha: 2026-11-14T13:31:02-05:00. Feature: F-015.

Fuente: solicitud actual del usuario con captura del login local: igualar los
inputs, corregir el asterisco, quitar la flecha y ajustar login/alta MFA al alto
disponible. Esta solicitud autoriza la corrección reversible del producto dentro
de F-015; no reabre otras features ni autoriza despliegue.

Evidencia: [comprobaciones y límites](../evidence/2026-11-14-f015-login-responsive.md).
Decisión: mantener helpers nativos, required, CSRF, autocompletado y hooks;
corregir únicamente CSS y estructura de presentación del plugin. Alta MFA con QR
al lado de controles en escritorio y una columna en móvil. Ajustar el contenedor
al viewport dinámico; ante contenido excepcionalmente alto, errores o zoom,
permitir desplazamiento dentro de la tarjeta para no perder controles.

Impacto: tres archivos del plugin. Sin cambios a configuración privada, cuentas,
datos, migraciones o autenticación. Recuperación: revertir solo este diff de
CSS/plantillas; no hay recuperación de datos que ejecutar.

Siguiente acción: revisar visualmente estas vistas cuando funcione Chrome.
F-015 permanece bloqueada por verificación visual pendiente; no se declara
validación de todas las vistas a partir del chequeo de HTML.
