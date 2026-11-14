# Projecta 0.2.0 · integración de la entrega

Fecha de cierre: 2026-11-14T12:48:52-05:00. Mismo ejecutor; sin revisión
independiente. Alcance autorizado: DEC-026; decisiones técnicas y recuperación: DEC-027.

## Resultado y límites

Se implementaron MFA obligatorio, límites de intentos y sesión, identidad Projecta,
cabecera corporativa, selección del proyecto en bolsas, estilos compartidos, recorte
controlado, SMTP por configuración privada/entorno, API individual y MCP PHP remoto.
El protocolo MCP y el paquete se probaron localmente; no se publicaron en Hostinger
ni se creó una conexión real de ChatGPT. No se conectó ni migró la base privada del
usuario. La edición de data/config.php se limitó al include público y respaldo privado
estable de la clave MFA, con lint sin arranque y sin mostrar valores.

F-015 conserva pendiente la revisión visual por vista, móvil, Tab/foco y zoom: la
integración Chrome falla al crear/navegar pestañas con error interno timeoutMs;
IAB no estaba disponible. No se presentan capturas ni aprobación visual. F-012
conserva su bloqueo de hosting efectivo. Los resultados locales no resuelven por sí
solos latencia de red o de la MariaDB remota del usuario.

## Cambios principales

- Seguridad: TOTP de 160 bits cifrado AES-256-GCM; importación del factor nativo;
  antirreplay por contador; diez recuperaciones de 128 bits con hash y consumo
  atómico. Regeneración transaccional, restablecimiento auditado y CLI de recuperación.
  Login, MFA y reautenticación limitados por usuario/IP; POST/CSRF, errores genéricos,
  validación de tipos/longitudes, SameSite/HttpOnly/Secure en HTTPS y no-store.
  Inactividad 30 minutos y duración absoluta 8 horas; sin RememberMe/LDAP/proxy.
- UI: nombre Projecta e icono suministrado, cabecera #0057ff, login/MFA/seguridad,
  base de estilos de formularios/tablas/modales y menús depurados. Bolsas indica el
  proyecto seleccionado con texto, estilo y aria-current. El layout conserva hooks,
  recursos locales y CSP. No se declara una auditoría visual completa.
- Recorte: rutas de Currency, Plugins, integraciones externas, feeds, compartir
  público y conectores desactivadas en servidor. Motores/traducciones internos se
  conservan conforme al recorte controlado aprobado. No se borran datos/historia.
- SMTP: PROJECTA_SMTP_* desde config/projecta.php incluido por configuración privada;
  sin fallback a mail/sendmail ni cola cuando falta configuración. TLS con certificado
  verificado y timeout de 5 segundos. Sin credenciales ni destinatarios reales.
- API: tokens individuales de 256 bits con hash, expiración de 90 días, scopes y
  revocación; factor/contraseña/usuario activo comprobados en cada solicitud.
  Contraseña y token global deshabilitados. Body 256 KiB, batch 20, paginación 100,
  límites por actor/IP y respuestas depuradas. CRUD conserva autorización nativa por
  objeto. El borrado con historia devuelve conflicto antes de tocar filas/adjuntos.
- MCP: SDK PHP oficial 0.10.0 y OAuth League 9.4.1 fijados; Streamable HTTP
  2025-11-25, OAuth Authorization Code/PKCE S256, callbacks/recurso exactos, códigos
  de 5 minutos, acceso de 10 minutos y refresh de 30 días rotado. Sesiones por usuario,
  Host/Origin y permisos actuales. Quince herramientas CRUD con esquemas cerrados y
  clave de operación durable. Una caída entre efecto y resultado requiere consultar
  el objeto; no se promete atomicidad exactamente-una-vez de todos los eventos nativos.

La política y modelos viven en plugins/RvSoftware; las excepciones pequeñas de núcleo
están justificadas en DEC-027. No hay cambios de vendor/ ni libs/ ni otro runtime/base.
La entrega completa requiere núcleo + plugin + config pública + MCP y sus dependencias.

## Rendimiento observado

Destino: copia .runtime/projecta/app, PHP 8.5.12 y MariaDB 11.8.9-log, loopback
13318, esquema reservado kanboard_compat_http. Fixture de referencia de 100 proyectos
con 100 tareas cada uno. Diez usuarios gestores para trabajo normal y diez actores
administrativos separados para crear usuarios/grupos; pico de diez trabajadores
concurrentes. Ninguna carga se ejecutó sobre hosting o datos reales.

Benchmark CLI: 200 muestras por operación, dos calentamientos y veinte iteraciones
por trabajador. Proyectos liderados p95 80.56 → 3.05 ms; resumen 209.30 → 57.19 ms.
El resumen pasa de 409 consultas PicoDB a 5 PicoDB + 2 PDO; liderazgo de 202 PicoDB
a una PDO con roles nativos. Los casos de grupos personalizados se resuelven con
el modelo nativo para conservar desempate y permisos, con cobertura específica en
SQLite/MariaDB. Sin caché de autorización ni retraso de revocación.

Benchmark HTTP final: diez procesos PHP independientes, 200 muestras por operación
después de calentamiento; todas las respuestas se comprobaron. Crear usuario incluye
hash real de contraseña. Los valores CLI de escritura sin hash no se comparan con él.

| Operación HTTP | p95 ms | Presupuesto ms |
| --- | ---: | ---: |
| Navegar al resumen | 154.69 | 800 |
| Listar proyectos | 87.52 | 800 |
| Listar tareas | 90.44 | 800 |
| Crear proyecto | 63.78 | 1500 |
| Crear tarea | 96.22 | 1500 |
| Crear usuario | 618.35 | 1500 |
| Crear grupo | 83.10 | 1500 |

Los siete presupuestos pasan. Es latencia HTTP sintética, sin render del navegador
ni red del hosting; no se atribuye mejora causal de todas las escrituras al cambio.
JSON de muestras resumidas: .runtime/projecta/benchmark-before.json,
benchmark-final.json y http-benchmark.json (locales, no productivos).
Esta medición completa el pendiente HTTP del informe previo F-013.

## Verificaciones ejecutadas

| Comprobación | Resultado observado |
| --- | --- |
| Sintaxis de PHP productivo nuevo/modificado | 75 archivos, 0 fallos |
| Sintaxis de toda la entrega, incluidos scripts y pruebas | 95 PHP y 17 Python, PASS |
| Suite completa plugin PHP 8.5/SQLite, final | 48 tests, 274 aserciones, PASS |
| Suite completa plugin PHP 8.5/MariaDB-log, final | 48 tests, 274 aserciones, PASS |
| Roles personalizados en MariaDB | 2 tests, 10 aserciones, PASS |
| Regresión nativa PHP 8.5/SQLite anterior al último caso del plugin | 1287 tests, 181101 aserciones, 1 warning, 47 skips, exit 0 |
| HTTP seguridad final | Alta obligatoria, URL protegida, CSRF, TOTP, antirreplay, recuperación y bloqueo de desactivación, PASS |
| Concurrencia de seguridad | 10 procesos: una recuperación consumida y máximo 5 intentos admitidos, PASS |
| Inventario HTTP final | 31 vistas/fragmentos, metadatos/iconos/selección y guardado; 6 rutas retiradas rechazan acceso directo, PASS |
| API HTTP final | CRUD de los tres objetos, roles/scopes, objeto ajeno, padres falseados, asignación, paginación, contraseña rechazada e historia protegida, PASS |
| MCP/OAuth HTTP final | Negociación, descubrimiento, 15 herramientas CRUD, PKCE/consentimiento, replay, rotación, idempotencia/conflicto e historia, PASS |
| Negativos MCP final | Roles, objeto ajeno, scope reducido sobre sesión existente, Host/Origin, esquema cerrado, revocación y JWT falseado, PASS |
| Carrera de escritura MCP final | 10 procesos y misma clave crean un solo proyecto; solicitudes pendientes no vuelven a ejecutar, PASS |
| SMTP local TLS | MIME recibido en receptor loopback STARTTLS con CA sintética y certificado verificado, PASS; ningún correo externo |
| Auditoría de dependencias MCP | Composer reportó 0 avisos de seguridad y 0 paquetes abandonados para el lock |
| Restauración del ZIP exacto | Hashes, esquema 9, conteos, hora aprobada/reporte/ledger, adjunto, factor cifrado/clave, API y dependencias MCP/OAuth, login/API HTTP extraídos, PASS |

La suite nativa conservó un warning y skips; exit 0 no significa que esos casos se
ejecutaron ni que el warning quedó resuelto. LDAP faltaba en el primer perfil nativo;
se activó solo en ese proceso y la ejecución final terminó. La restauración detectó
una exclusión incorrecta de la clase pública mcp/src/Config.php por coincidencia con
config.php en Windows; se corrigió export-ignore, se añadieron comprobaciones de
archivos MCP requeridos y el ZIP final pasó. Los ZIP previos no son la entrega.
El TLS de la CLI de dump se desactiva únicamente en la conexión loopback sintética
guardada; no cambia la política TLS SMTP ni la configuración productiva.

## Reproducir sin datos reales

Preparar primero la instancia portátil sintética de testing.md en 13318, sus esquemas
reservados y el autoload de pruebas. No ejecutar fixtures contra data/config.php.

1. `python harness/scripts/Prepare-ProjectaSandbox.py`.
2. `ProjectaEnvironment.ps1 -Script ...` ejecuta los fixtures PHP con guardas; `-Port
   18100` sirve la copia. Para PHPUnit: .runtime/vendor/bin/phpunit, bootstrap
   .runtime/vendor/autoload.php y tests/units.sqlite.xml con filtro RvSoftware.
   MariaDB usa .runtime/compatibility/units.mysql.plugin.xml, nunca una base real.
3. Projecta-SecurityFixture.php reinicia solo la identidad sintética; después
   Test-ProjectaSecurity.py produce recuperaciones para Test-ProjectaViews.py.
   Projecta-ApiFixture.php prepara actores API; Test-ProjectaApi.py comprueba CRUD.
4. Projecta-McpFixture.php prepara SDK/firmas/actores; Test-ProjectaMcp.py antes de
   Test-ProjectaMcpNegative.py (este revoca el token). Test-ProjectaBridgeRace.py y
   Test-ProjectaRace.py crean sus propias identidades/operaciones sintéticas.
5. Projecta-Benchmark.php seed crea el fixture; Benchmark-Projecta.py final mide CLI;
   Projecta-HttpBenchmarkFixture.php renueva tokens/recuperaciones antes de
   Benchmark-ProjectaHttp.py. Los procesos PHP hijos se cierran en finally.
6. Projecta-SmtpCertificate.php antes de Test-ProjectaSmtp.py; la CA solo se agrega
   al proceso PHP del test. Build-Release.py --working-tree construye; luego
   Test-ProjectaArtifact.py con la ruta del ZIP prueba extracción/restauración.

Los JSON locales con tokens/factores son sintéticos y se mantienen fuera del artefacto
y de evidencia versionada. No compartirlos ni sustituir sus variables con datos reales.

## Paquete entregable y recuperación

ZIP: .runtime/releases/projecta-0.2.0-preview-e4203f1668ff.zip, 3835 archivos de
runtime más manifiesto. SHA256:
`ac2b1e92d679b7a040a6c4d55ec0905e7106698dd1e79d413201453f08950731`.
El manifiesto identifica working-tree-preview, versión 0.2.0, hashes y
productionAuthorized=false. Las fuentes siguen sin commit/push. Se excluyen harness,
Git, pruebas, configuración privada, secretos, dumps y datos, preservando licencias.
Las dependencias MCP fijadas sí se incluyen; instalar solo el plugin es insuficiente.

La recuperación se ensayó con backup lógico sintético y una segunda base reservada,
copiando adjuntos/configuración de prueba/clave privada fuera del código extraído.
Antes de migrar un entorno real: backup conjunto de código, base, archivos y claves;
revisar migraciones 7..9, carpeta privada MCP y reglas HTTP efectivas. No volver a
un login sin MFA ni borrar tablas nuevas. Producción requiere alcance vigente,
destino confirmado y recuperación probada allí.

## Cierre del harness y pendientes

Después del cierre canónico, Test-Harness.ps1 pasó: 18 features, 29 casos, 13 prompts
y 10 escenarios. Test-HarnessScenarios.ps1 pasó: 26 fixtures, 0 fallos, incluida
recuperación de candidato y preservación del árbol con cambios. Son verificaciones
estáticas del harness, distintas de las pruebas funcionales del producto.
F-001..F-012 y los 23 casos previos comparados con HEAD: contenido idéntico;
F-012 sigue bloqueada. Los controles y casos anteriores no se retiran.
git diff --check pasó, con aviso informativo LF/CRLF en el ejemplo de configuración.
El hash del ZIP y las fuentes productivas incluidas se cotejaron nuevamente contra
el árbol actual sin diferencias. Los servidores sintéticos de esta ejecución en
18100 y 13318 se cerraron; no quedan listeners en esos puertos ni en 18101..18110.
No se detuvo el servidor local del usuario ni se borraron archivos/datos de trabajo.

Siguiente paso: revisión visual F-015 cuando funcione el navegador; verificación
efectiva de staging F-012 y, por separado, aceptación de ChatGPT con sus callbacks
reales y permisos disponibles. SMTP real requiere credenciales y destinatario
autorizados. No hay un despliegue ni correo externo implícitos en esta entrega.
