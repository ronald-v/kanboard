# DEC-027 · Integración y límites de Projecta

Fecha: 2026-11-14T12:44:39-05:00. Features: F-013..F-018; F-012 permanece bloqueada.

Evidencia: implementación autorizada en DEC-026, pruebas aisladas y
[informe integrado](../evidence/2026-11-14-projecta-integracion.md).
Decisiones técnicas del ejecutor dentro del alcance aprobado; sin nueva autorización
para producción, datos reales o correo externo.

Decisión: mantener MVC/PicoDB/PDO y plugin propio, con excepciones acotadas del núcleo
para guard obligatorio de MFA, cookies, CSRF, política de rutas/configuración, API y
protección de eliminaciones. La sustitución exclusiva de servicios no protegía
instancias ya congeladas durante actualización, reproducida en pruebas de upgrade;
los puntos nativos delegan al plugin y conservan comportamiento sin él. No se edita
vendor/ ni libs/. La recuperación CLI del factor obliga una nueva alta.

La consulta conjunta de liderazgo conserva precedencia directa y revocación inmediata.
Los roles personalizados pueden empatar con manager en la jerarquía nativa: esos casos
se resuelven por el modelo original, sin elevar acceso por la optimización.

MCP usa SDK PHP oficial 0.10.0 y OAuth League 9.4.1 fijados, en vendor propio bajo mcp/.
No necesita otro runtime. OAuth exige PKCE S256, recurso y callbacks exactos, con
almacenamiento privado fuera del document root. El control de escritura reserva una
clave durable antes del procedimiento nativo: evita reejecutar un intento, pero una
caída entre escritura y resultado requiere consultar el objeto; no ofrece atomicidad
exactamente-una-vez entre todos los eventos/modelos nativos.

La clave MFA estable puede venir del entorno o del config privado ignorado. Se añadió
un respaldo aleatorio privado a la configuración local autorizada sin mostrar su valor,
sin conectar esa base ni migrarla. Respaldar clave/base/adjuntos/configuración/RSA antes
de migrar un entorno real. Revertir archivos no revierte tablas ni factores; no eliminar
esquemas de seguridad ni reabrir el login antiguo como recuperación.

Impacto: paquete completo con esquema 9 y dependencias verificadas/restauradas en
sintéticos. F-015 conserva pendiente visual por fallo de integración Chrome; pruebas
HTTP no sustituyen móvil, foco o zoom. F-012 conserva pendiente Hostinger. Protocolo MCP
probado no equivale a conexión de ChatGPT; esa aceptación se mantiene separada conforme
a DEC-026. SMTP local TLS sintético no acredita entrega de correo real.

Siguiente acción: entregar preview y evidencia, restablecer navegador para F-015 y,
cuando exista alcance/destino efectivo, verificar staging y conexión del cliente.
