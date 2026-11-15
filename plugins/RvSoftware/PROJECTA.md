# Operación de Projecta

Versión 0.2.0. Aplicación PHP interna para proyectos, tareas, horas y bolsas.
Cambios de producto autorizados en DEC-026; no acreditan un despliegue.

## Acceso

Cada persona debe configurar una aplicación autenticadora tras ingresar con su
contraseña. Guarda los diez códigos de recuperación: se muestran una sola vez.
Recuperar la contraseña por correo no elimina el segundo factor. Sesiones con
30 minutos de inactividad o 8 horas de duración expiran; no se usan cookies persistentes.

Seguridad permite renovar recuperaciones, cambiar autenticador, emitir/revocar
credenciales API y revocar ChatGPT. Estas operaciones requieren contraseña y un
código nuevo. Administradores pueden reiniciar el factor de otro usuario; el usuario
deberá darlo de alta de nuevo. El operador del servidor conserva recuperación CLI:
`php cli user:reset-2fa USUARIO` (revisar destino antes de arrancar; puede migrar).

La clave MFA está en PROJECTA_KEY del entorno o en la configuración privada local.
Respaldarla con base y configuración: perderla impide descifrar los factores.
No volver a un login antiguo ni borrar las tablas de seguridad para recuperar acceso.

## SMTP

data/config.php incluye config/projecta.php. Variables:
PROJECTA_SMTP_HOST, PROJECTA_SMTP_PORT (587), PROJECTA_SMTP_ENCRYPTION (tls o ssl),
PROJECTA_SMTP_USER, PROJECTA_SMTP_PASSWORD y PROJECTA_SMTP_FROM.
Sin configuración completa no se encola correo ni se usa PHP mail como alternativa.
El estado administrativo solo informa configuración, no entrega comprobada.
Comprobar recepción real únicamente con un destinatario y envío autorizados.

## API

POST /jsonrpc.php con JSON-RPC2 y HTTP Basic: usuario + credencial individual prj_.
Crear credenciales en Seguridad; vigencia 90 días, revocables. No usar contraseña,
token global jsonrpc ni la antigua clave nativa. La contraseña/factor/estado activo
se verifican en cada petición. Cambios de contraseña o factor invalidan credenciales.
TLS obligatorio en el destino productivo; loopback HTTP solo para desarrollo aislado.

La API conserva procedimientos nativos de proyectos, tareas y subtareas. Añade
projectaListProjects(offset,limit), projectaListTasks(project_id,offset,limit) y
projectaListSubtasks(task_id,offset,limit), con limit máximo100. Errores JSON-RPC
pueden venir dentro de HTTP200: comprobar siempre error/result. Historia protegida
devuelve -32009. Cerrar/archivar conserva historia; no insistir en borrado físico.

MCP/OAuth se configura conforme a [mcp/README](../../mcp/README.md). Nunca proporciona
acceso mayor al del usuario conectado. No editar acumulados de horas por API/MCP:
las sesiones y el ledger siguen siendo la fuente de verdad.

## Límites de esta entrega

No contiene envío real SMTP, publicación en Hostinger ni conexión verificada de ChatGPT.
La revisión visual por vista está pendiente de restablecer la conexión de navegador.
Las mediciones sintéticas no garantizan latencia sobre una base remota o un hosting
todavía no medido. Revisar el informe de integración antes de producción.

## Seguridad antes de empaquetar

Las cuatro entradas HTTP contienen errores de arranque y ejecuci?n. Un fallo de
conexi?n/cuota devuelve HTTP 503; otros fallos internos, 500. Las respuestas no
incluyen detalles t?cnicos y contienen una referencia para buscar el evento
`projecta.http_error` en el registro privado de PHP. JSON-RPC y MCP conservan el
formato de protocolo. No reintentar autom?ticamente escrituras tras una ca?da.
Los tableros se actualizan manualmente mediante ?Actualizar? y despu?s de acciones.
Esto reduce conexiones peri?dicas; no garantiza que una cuota de 500/h sea suficiente.

Configurar el registro PHP **fuera del directorio p?blico**, permisos m?nimos,
rotaci?n y alertas sobre errores 503/500 y c?digo MariaDB 1226. Las advertencias se
agrupan por ubicaci?n con m?ximo diez por petici?n para acotar el ruido. Mantener
DEBUG desactivado en producci?n; otros diagn?sticos nativos de DEBUG pueden incluir SQL.
`.user.ini` desactiva la presentaci?n de errores en FastCGI/FPM. Confirmar que el
hosting lo aplica; en otras SAPIs usar php.ini. Un error anterior a la ejecuci?n,
un proceso terminado por el sistema o un fallo del propio servidor requiere una
p?gina gen?rica del servidor y configuraci?n PHP efectiva: no lo cubre un catch.

Las reglas Apache 2.4 se prueban localmente. Verificar AllowOverride, mod_rewrite,
mod_version y reglas efectivas en el destino. Para Nginx deben trasladarse a la
configuraci?n del servidor; web.config se entrega para IIS, sin validaci?n din?mica
IIS. Deben denegarse configuraci?n, data, logs, copias, c?digo interno y dependencias;
los adjuntos se sirven por controladores autorizados con Cache-Control private/no-store.

Solo confiar en X-Forwarded-Proto desde REMOTE_ADDR incluido en
TRUSTED_PROXY_NETWORKS. Si el hosting termina TLS en un proxy, identificar sus rangos
reales y configurarlos antes de publicar; nunca usar 0.0.0.0/0 ni rangos inferidos.
Conservar HTTPS directo y comprobar cookies Secure/HttpOnly/SameSite y OAuth.

Verificaci?n de desarrollo (sin configuraci?n privada ni hosting): PHP portable 8.5,
MariaDB portable 11.8.9, PHPUnit y Chromium de los perfiles ya documentados. Apache
Lounge 2.4.71 se coloca ?nicamente en .runtime/f020/apache/Apache24 tras verificar
SHA256 44961a929dff16b767b3adbcea55760a942a00239b2891dc55806be34029e1fc
contra https://www.apachelounge.com/download/VS18/binaries/httpd-2.4.71-261112-Win64-VS18.zip.txt.
Los puertos 13318, 18100, 18114 y 18116 deben estar libres. El runner verifica el
datadir reservado antes de fixtures y cierra solo procesos propios.

```powershell
python harness/scripts/F020-Errors.py
python harness/scripts/F020-Integration.py
python harness/scripts/F020-Units.py
python harness/scripts/F020-Audit.py
python harness/scripts/Test-ReleaseSecurity.py
# Tras revisar hallazgos y guardar review.json en la carpeta local de evidencias:
python harness/scripts/F020-Evidence.py
python harness/scripts/Build-Release.py --working-tree --security-evidence .runtime/f020/security-evidence.json
```

El informe de esta intervenci?n est? en harness/evidence/2026-11-14-f020-security.md.
La revisi?n debe registrar sus hallazgos en .runtime/f020/review.json con passed=true
solo despu?s de los retests y la disposici?n expl?cita de los riesgos restantes.
El gate comprueba resultados, hashes de producto/dependencias/locks y herramientas,
y rechaza evidencia caducada, pruebas pendientes o hallazgos altos/cr?ticos abiertos.
Una evidencia local es un registro del ejecutor, no una firma de un auditor externo.
El manifiesto mantiene productionAuthorized=false: empaquetar no aprueba el hosting.

Recuperaci?n: conservar copia de los archivos anteriores y aplicar/revertir ?nicamente
el diff de esta intervenci?n. No hay migraciones nuevas ni cambios de configuraci?n
privada. No restaurar tablas MFA ni reemplazar claves para revertir c?digo.
