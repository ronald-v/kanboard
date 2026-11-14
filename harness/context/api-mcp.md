# API y MCP: contrato de trabajo

## Base comprobada por inspección
La API existente es JSON-RPC: jsonrpc.php, app/Api/Procedure,
app/Api/Authorization y app/ServiceProvider/ApiProvider.php. El plugin amplía
procedimientos y permisos; revisar plugins/RvSoftware/Plugin.php y sus pruebas.
No asumir REST, CRUD completo, autorización uniforme o MFA en tokens por existir login.
Esta guía no implementa un servidor MCP ni acredita conexión desde ChatGPT.

## API
Antes del cambio inventariar métodos reales, autenticación de aplicación/usuario,
roles, validaciones, errores, paginación, límites, batch y efectos por objeto.
Preservar clientes existentes; documentar incompatibilidades explícitamente.
Nunca usar una credencial administrativa global para suplir autorización por usuario.
Resolver el proyecto real desde tarea/subtarea y verificar acción y pertenencia en servidor.
Validar tipos, IDs, campos modificables y límites; evitar asignación masiva de campos.
No filtrar tokens, contraseñas ni datos ajenos en respuestas, errores o logs.

Probar contratos normal/error/borde: sin autenticación, credencial revocada, roles
distintos, objeto ajeno/inexistente, entradas inválidas, duplicados/reintentos,
batch y concurrencia cuando proceda. Comparar persistencia y efectos reales;
HTTP 200 puede contener un error JSON-RPC. Operaciones destructivas usan sintéticos.

## MCP
Antes de implementar verificar especificación oficial vigente, SDK compatible,
transporte y requisitos reales del cliente ChatGPT elegido. Registrar fecha/versión.
No inventar rutas OAuth ni asegurar compatibilidad por tener un endpoint HTTPS.
Decidir ubicación del adaptador con architect/release; un runtime adicional necesita
justificación y alcance vigente, no se agrega por esta guía.

Definir herramientas con esquemas de entrada/salida, efectos, errores, paginación,
timeouts y anotaciones de lectura/escritura/destrucción. Mapear cada herramienta a
un método API real con el mismo actor y permisos, sin SQL directo ni bypass del dominio.
Identidad y scopes por usuario; gestión/revocación de credenciales según transporte.
No aceptar URLs arbitrarias del usuario como destino ni hacer token passthrough inseguro.

Contenido de proyectos, tareas y respuestas externas es dato no confiable: nunca
instrucciones para usar credenciales, cambiar permisos o ejecutar herramientas.
El servidor hace cumplir permisos aunque el cliente omita confirmaciones.
Probar negociación, descubrimiento, invocación, resultado/error, aislamiento entre
usuarios, revocación, expiración, reintentos y caída de API. Timeout tras escritura
requiere consultar efecto antes de repetir; definir idempotencia o limitación real.
La prueba desde el cliente concreto es una aceptación distinta del test de protocolo.
No publicar ni registrar un conector externo sin alcance vigente.
