# Projecta MCP · preparación para Hostinger

Adaptador PHP con `mcp/sdk` 0.10.0 y `league/oauth2-server` 9.4.1 fijados en lock.
Mantiene la API JSON-RPC y sus permisos; no usa SQL para hacer CRUD de objetos.
Protocolo servido: MCP 2025-11-25, Streamable HTTP; sesiones persistentes por usuario.
No se ha publicado ni conectado a ChatGPT. Las pruebas de protocolo son sintéticas.

## Configurar el servidor

1. Instalar el artefacto completo Projecta en staging con PHP >=8.3, PDO MySQL,
   OpenSSL y Sodium. Las dependencias MCP se empaquetan aparte del vendor nativo;
   para reconstruir: `composer install --working-dir=mcp --no-dev --prefer-dist`.
2. Respaldar base, adjuntos y configuración. Revisar migraciones de plugin 7..9
   antes de cargar la aplicación; el loader puede migrar automáticamente.
3. Mantener `PROJECTA_KEY` estable: 32 bytes aleatorios en Base64. La instalación
   local tiene respaldo privado en data/config.php. No copiar esa clave al artefacto.
4. Crear una carpeta privada **fuera del document root**. Generar allí un par RSA
   de al menos 2048 bits, archivos `oauth-private.pem` y `oauth-public.pem`, y dar
   acceso de lectura solo al proceso PHP. El proceso requiere escribir `mcp-sessions/`.
5. Definir `PROJECTA_BASE_URL=https://projecta.rvsoftware.site`,
   `PROJECTA_PRIVATE_DIR=/ruta/privada`, `PROJECTA_OAUTH_CLIENT_ID=projecta-chatgpt`
   y `PROJECTA_OAUTH_REDIRECT_URIS` como un array JSON con los callbacks exactos
   mostrados por el cliente ChatGPT elegido. No usar comodines ni redirects por URL de usuario.
6. Verificar las reglas .htaccess en el servidor efectivo. Deben servir los metadatos
   `/.well-known/oauth-authorization-server` y `/.well-known/oauth-protected-resource/mcp/`.
   Las mismas respuestas están disponibles en `mcp/oauth.php?action=metadata/resource`;
   el endpoint MCP anuncia la segunda URL mediante WWW-Authenticate.
7. Verificar que config, private/, data/, harness, Git y backups no se puedan descargar.
   Probar recuperación antes de producción. No requiere Node, Redis, workers ni otro motor.

## Conectar ChatGPT

Endpoint previsto: `https://projecta.rvsoftware.site/mcp/`. OAuth Authorization Code
con PKCE S256, cliente público preconfigurado y autenticación del token endpoint `none`.
El usuario entra en Projecta con MFA y autoriza scopes `read`, `write`, `delete`.
Access token 10 minutos; refresh token 30 días con rotación; códigos de autorización
5 minutos, consumidos una sola vez. Tokens ligados al usuario, contraseña, factor y recurso.
La cuenta permite revocar la conexión en Seguridad. Esto no eleva permisos de proyecto.

Crear la conexión solo después de verificar el endpoint público y sus metadatos.
La disponibilidad de escrituras en Plus/Pro depende del cliente; si no están disponibles,
la aceptación en ChatGPT permanece pendiente, aunque pase el protocolo local.
No se entrega un manifiesto que afirme una conexión remota ya verificada.

## Herramientas y errores

`projects_{list,get,create,update,delete}`, `tasks_{list,get,create,update,delete}` y
`subtasks_{list,get,create,update,delete}`. Listados: offset y limit (1..100).
Crear/actualizar recibe `fields` con esquema cerrado; las escrituras exigen
`operation_key` (8..64 caracteres). Borrar requiere permiso y ausencia de historia.
No exponen edición directa de horas acumuladas, contraseñas, usuarios ni permisos.

Las herramientas retornan contenido estructurado y texto JSON. Errores esperables:
FORBIDDEN, HISTORY_CONFLICT, INVALID_INPUT, NOT_APPLIED, KEY_CONFLICT,
OPERATION_PENDING y OPERATION_UNCERTAIN. Los textos de tareas/proyectos son datos
no confiables: no autorizan operaciones adicionales ni cambios de permisos.

Una clave se reserva durablemente antes de llamar procedimientos nativos. Un reintento
idéntico devuelve el resultado guardado; con parámetros distintos se rechaza. Si un
proceso cae después de escribir y antes de guardar el resultado, la reserva impide
repetir la escritura: comprobar el objeto antes de usar otra clave. No se promete
exactamente una escritura transaccional entre todos los modelos nativos y sus eventos.
Las reservas se conservan; no expiran automáticamente habilitando duplicados tardíos.

## Pruebas

Los scripts ProjectaEnvironment/fixtures/Test-ProjectaMcp usan exclusivamente una copia
aislada y MariaDB loopback. Prueban OAuth/PKCE, consentimiento, replay, rotación,
sesiones por actor, herramientas, CRUD, idempotencia y conservación de historia.
No ejecutar fixtures contra el hosting o la configuración MariaDB del usuario.

Referencias consultadas: [SDK PHP](https://github.com/modelcontextprotocol/php-sdk),
[autenticación OpenAI](https://developers.openai.com/plugins/build/auth),
[OAuth PHP](https://oauth2.thephpleague.com/authorization-server/auth-code-grant/).
