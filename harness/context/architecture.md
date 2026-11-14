# Arquitectura y extensión

## Actualización Projecta 0.2.0
DEC-026 autoriza MFA obligatorio, política de funciones, API individual y MCP PHP.
El plugin usa esquema 9 y dependencias MCP fijadas en mcp/composer.lock. Hay
excepciones mínimas del núcleo para cerrar accesos y preservar historia;
justificación en [DEC-027](../feedback/2026-11-14-DEC-027-integracion-projecta.md).
No se modifican vendor/ ni libs/, ni se añade un servicio o base de datos.
El artefacto completo requiere núcleo, configuración pública, plugin y MCP;
copiar solo el plugin no entrega esta versión. Los párrafos históricos se conservan.

## Existente
PHP >=8.1, Composer, Pimple, PicoDB/PDO. MVC con plantillas PHP; no SPA.
Controladores app/Controller; modelos app/Model; plantillas app/Template;
validadores app/Validator; servicios app/ServiceProvider; middleware app/Middleware;
API JSON-RPC app/Api; CLI app/Console; assets CSS/JS y bibliotecas en assets/vendor.
Dependencias empaquetadas en libs y vendor: no editarlas para personalizar producto.

Puntos comprobados:
- [Loader](../../app/Core/Plugin/Loader.php): descubre directorios bajo PLUGINS_DIR,
  registra clases/helpers, carga esquema y llama initialize/onStartup.
- [Template](../../app/Core/Template.php): setTemplateOverride y prefijo kanboard:.
- [Layout](../../app/Template/layout.php): template:layout:css/js/head/top/bottom.
- [Dashboard](../../app/Template/dashboard/overview.php): hooks antes/después de filtros,
  títulos y pie de tareas. Sidebar en app/Template/dashboard/sidebar.php.
- [Rutas](../../app/ServiceProvider/RouteProvider.php): rutas y controlador/acción.
- [Temporizador](../../app/Model/SubtaskTimeTrackingModel.php): hook
  model:subtask-time-tracking:calculate:time-spent; solo altera cálculo, no atomicidad.

## Plugin propio desde F-005
`plugins/RvSoftware` existe y usa loader, ACL, hooks y migraciones nativos.
Versión interna 0.1.0 sobre commit exacto documentado en su README. Pruebas en
tests/units/Plugin y HTTP sintético; sin dependencias nuevas ni cambios del núcleo.
Evidencia: [F-005](../evidence/2026-11-12-f005-plugin.md).
Las secciones siguientes conservan el diseño de extensión para features futuras.

## Diseño de extensión
Plugin cohesionado `plugins/RvSoftware/` con Plugin.php, Controller/, Model/,
Template/, Assets/, Schema/ y pruebas en ubicación convencional que se defina en
F-005 según integración con PHPUnit. Los assets y esquemas no van en harness.
Aplicar configuración/nativo primero; hooks/overrides después; núcleo como excepción.
No agregar Node permanente, segunda base, Redis, workers o microservicios por defecto.

Para bolsas, evaluar sustitución del servicio de seguimiento vía contenedor para
interceptar tanto UI como API y cambios de estado. El stop nativo actualiza la
subtarea con fireEvent=false: un listener de eventos no garantiza capturar consumos.
La cobertura de todas las entradas deberá demostrarse en F-007, no suponerse.

Límites: overrides completos pueden divergir de upstream; hook de cálculo no añade
idempotencia; ACL por proyecto no autoriza automáticamente clientes/bolsas compartidas.
El plugin propio ya está versionado mediante la excepción acotada de plugins/.gitignore.
Las hipótesis de diseño anteriores son históricas: usar código y evidencia F-005..F-011
para determinar el comportamiento actual, no repetir una implementación entregada.

## Excepción de núcleo
Registrar feature, archivos, causa comprobada, alternativas descartadas, cambio
mínimo, pruebas, impacto upstream y reversión. Aprobación dentro del alcance vigente;
una decisión de nueva arquitectura no se infiere de permiso para una edición menor.

Fuentes: [overrides](https://docs.kanboard.org/v1/plugins/overrides/),
[plugins](https://docs.kanboard.org/v1/plugins/). Compatibilidad determinada por código local.
