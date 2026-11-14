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
