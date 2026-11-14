# Despliegue PHP y recuperación

## Preparación Projecta 0.2.0
La [integración](../evidence/2026-11-14-projecta-integracion.md) identifica el ZIP
exacto y su hash, con restauración sintética comprobada. Build-Release.py
--working-tree genera un preview de fuentes revisadas y dependencias MCP fijadas;
no es un commit publicado ni una autorización productiva. Incluye mcp/vendor
sin documentación/pruebas de dependencias y conserva licencias.
Respaldar PROJECTA_KEY, RSA/OAuth, configuración, base y adjuntos conjuntamente;
revertir código no revierte migraciones 7..9. Configurar carpeta MCP privada fuera
del document root conforme a [MCP](../../mcp/README.md). F-012 sigue bloqueada
hasta verificar servidor y recuperación efectivos en Hostinger.

## Destino informado el 2026-11-13

Ronnie indicó PHP 8.5 y MariaDB 11.8.9-MariaDB-log en Hostinger, dominio ya creado
`projecta.rvsoftware.site`. No se recibió acceso ni instrucción de cargar código.
Una consulta HEAD pública devuelve HTTPS 200, Server hcdn y X-Powered-By PHP/8.3.36:
esto puede corresponder a la página actual/intermediario, no demuestra el runtime
de la futura aplicación. Verificar PHP efectivo, extensiones y servidor de origen.

El ensayo local usa PHP 8.5.12 y MariaDB 11.8.9 portable, con binary log activado,
datos sintéticos y puertos loopback. Acredita ese ensayo, no las reglas del hosting.
La base upstream permanece fijada. Los párrafos siguientes conservan la planificación
histórica; evidencia y pendientes actuales están en feature-list.json/current-task.md.

## Confirmado / pendiente
Hostinger permite un sitio PHP/HTML. No se verificaron destino, PHP/extensiones,
motor/versiones, servidor, permisos, límites, cron, SSH/SFTP o mecanismo de carga.
No desplegar sobre otro sitio (incluido Node existente) sin identificar destino.
No hay credenciales, conexión, infraestructura ni proceso de despliegue configurado
por esta entrega. Producción requiere alcance y autorización vigentes.

## Preparación de F-012
1. Obtener ficha técnica y verificar requisitos locales de app/check_setup.php y
   composer.lock contra PHP de producción; confirmar driver PDO y GD, entre otros.
2. Elegir motor soportado y probar migraciones/plugin en ese motor. SQLite local
   no demuestra compatibilidad MySQL/MariaDB del hosting.
3. Configurar entornos separados y secretos fuera del repo. No asumir Composer,
   Node o build disponibles en hosting: preparar dependencias/assets previamente.
4. Artefacto reproducible con código, plugin, assets, vendor/libs necesarios y
   licencias; manifiesto con commit/versiones y checksum. No incluir AGENTS.md,
   harness/, .git, tests/, evidencias, configs locales, dumps ni backups.
5. .gitattributes ya excluye harness/AGENTS.md y recursos privados desde F-012.
   Build-Release.py comprueba el contenido del ZIP y genera manifiesto; las pruebas
   de recuperación local están en evidencia F-012. Revalidar el artefacto exacto
   antes de cada entrega; las exclusiones por sí solas no prueban seguridad HTTP.
6. Proteger configuración, datos y copias mediante reglas del servidor real o
   ubicación no pública compatible; no confiar en .gitignore. data/.htaccess e IIS
   son defensas presentes, no prueba del servidor de Hostinger.
7. Persistencia de adjuntos, backups y restauración conjunta de código/base/archivos.
   Restauración ensayada en copia antes de migrar producción.
8. Ventana sin escrituras concurrentes durante migración; inspeccionar también la
   carga de esquema de plugins. Revertir código no revierte esquema.
9. Smoke test: PHP ejecutado (no fuente descargable), login, roles, Dashboard,
   proyecto/tarea, tiempo, reporte, adjuntos y denegación de rutas sensibles.

## Límites y comandos
Makefile archive usa git archive; es mecanismo upstream de referencia, no artefacto
productivo ya validado. php cli db:migrate existe y escribe base: no ejecutarlo
antes de confirmar destino y recuperación. Los builds php cli css/js también
cargan app/common.php. No añadir Docker/Redis/workers como requisito implícito.
El harness y Node no necesitan ejecutarse en hosting. No crear gastos ni cambiar DNS.

Fuentes: [requisitos](https://docs.kanboard.org/v1/admin/requirements/) y
[actualización](https://docs.kanboard.org/v1/admin/upgrade/); verificar contra base local.
