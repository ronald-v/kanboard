# Seguimiento de acceso · 2026-11-14T13:31:02-05:00

Rama actual: feat/refactor. Corrección solicitada del login/alta MFA en F-015,
DEC-028: inputs iguales, asteriscos alineados con etiquetas, botón sin flecha,
contenedor ajustado al viewport y alta MFA en columnas adaptables. Solo tres
archivos de presentación del plugin, sin base ni configuración privada.
Sintaxis y seis ramas HTML con helpers nativos pasan; consistencia del harness
pasa. Chrome sigue fallando con timeoutMs: sin revisión visual ni medición de
scroll ejecutada. F-015 queda blocked, sin feature activa; otras sin cambios.
[Evidencia nueva](evidence/2026-11-14-f015-login-responsive.md).
Siguiente: comprobar login/MFA en navegador y completar matriz visual de F-015.
El paquete preview anterior no contiene esta corrección. Sin nuevo despliegue.

---

# Checkpoint

Actualizaci?n: 2026-11-14T12:48:52-05:00. Rama feat/012, cambios sin commit/push.
Estado can?nico: [feature-list.json](feature-list.json). Sin feature activa.
F-001..F-011 validated; F-012 blocked sin cambios. F-013/F-014/F-016/F-017/F-018
validated en el alcance sint?tico autorizado; F-015 blocked por revisi?n visual.

DEC-026 ampli? el encargo del harness a la implementaci?n del plan Projecta.
[DEC-027](feedback/2026-11-14-DEC-027-integracion-projecta.md) registra excepciones
m?nimas de n?cleo, dependencias, permisos y recuperaci?n. [Informe de integraci?n](evidence/2026-11-14-projecta-integracion.md)
contiene resultados, paquete exacto, comandos, fallos resueltos y l?mites.

Projecta 0.2.0/esquema 9: MFA TOTP obligatorio, recuperaci?n y l?mites at?micos;
identidad/icono/cabecera y estilos compartidos; proyecto visible en bolsas; recorte
controlado, SMTP por entorno, API individual y MCP PHP con OAuth/PKCE y 15 herramientas.
No se edit? vendor/ ni libs/ ni se a?adi? otro runtime/base. No conectar pruebas
con data/config.php: su MariaDB es real. Solo se a?adi? include p?blico y clave MFA
estable privada, sin arrancar/migrar esa base ni exponer secretos. Respaldar esa clave.

Validaci?n final: plugin 48 tests/274 aserciones tanto SQLite como MariaDB 11.8.9-log;
95 PHP y 17 Python con sintaxis v?lida; HTTP de MFA/API/31 vistas, SMTP TLS loopback,
MCP protocolo/negativos y carrera de 10 procesos pasan. Suite nativa anterior al
?ltimo caso del plugin: 1287 tests/181101 aserciones, 1 warning/47 skips, exit 0.
Benchmark final HTTP, 10 trabajadores/100 proyectos/10000 tareas: p95 m?ximo lectura
154.69 ms y escritura 618.35 ms, dentro de 800/1500 ms acordados. No mide hosting.

Paquete: .runtime/releases/projecta-0.2.0-preview-e4203f1668ff.zip.
SHA256 ac2b1e92d679b7a040a6c4d55ec0905e7106698dd1e79d413201453f08950731.
Restauraci?n del ZIP exacto con base sint?tica, adjuntos, factor/clave y API/MCP pasa.
Es preview de fuentes de trabajo, productionAuthorized=false; ZIP previos no entregar.
Harness final: 18 features/29 casos/13 prompts/10 escenarios pasan; 26 fixtures,
0 fallos. Servidores propios de pruebas 18100/13318 cerrados y sin listeners;
el servidor local del usuario no se detuvo.

Pendientes: F-015 requiere conexi?n de navegador funcional para revisi?n real por
vista/m?vil/foco/zoom (Chrome falla al crear/navegar pesta?as; no hay capturas).
F-012 requiere servidor, extensiones, reglas HTTP y recuperaci?n efectivos en
Hostinger; conocer versiones no resuelve el bloqueo. MCP todav?a no est? publicado
ni conectado a ChatGPT: validar callbacks y cliente reales por separado. SMTP no
contiene credenciales ni destinatario real y no se envi? correo externo.
No desplegar sin destino, autorizaci?n vigente y recuperaci?n verificada.

Historial conservado: DEC-022/F-012, DEC-024 arranque local 18001 con la configuraci?n
MariaDB elegida por Ronnie y DEC-025 revisi?n del harness. No reactivar F-012 por
las pruebas sint?ticas de esta entrega. F-001..F-012 y los 23 casos previos permanecen
id?nticos a HEAD; las nuevas evidencias complementan sin reescribir decisiones.
