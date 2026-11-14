# Seguridad y permisos

## Base inspeccionada
AuthenticationProvider configura autenticación y ACL de aplicación/proyecto/API.
Role define app-admin/app-manager/app-user y project-manager/member/viewer.
AuthenticationMiddleware valida sesión; ApplicationAuthorizationMiddleware y
ProjectAuthorizationMiddleware verifican permisos. El proyecto real de una tarea
prevalece sobre un project_id enviado por URL. BaseController::getUser limita
consulta de otros usuarios a administradores. No se ejecutó auditoría dinámica.

SubtaskStatusController usa comprobación CSRF para cambio de estado y timer;
API tiene autorización propia en app/Api/Authorization. Usar mecanismos existentes
según entrada; no asumir que todo método nuevo queda protegido automáticamente.
Response conserva CSP; plantillas deben escapar contenido. No reemplazar autenticación.

## Revisión por feature
- Autorización por objeto real y acción, incluido acceso directo, API y exportación.
- Reglas separadas para horas ajenas, tarifas, bolsas compartidas, correcciones y cierre.
- Validación de duración/fechas/IDs y pertenencia; SQL parametrizado; escape XSS.
- CSRF y métodos de modificación; no degradar CSP para aplicar branding.
- Archivos subidos: autorización, tipo/tamaño y descarga controlada.
- Exportación: datos mínimos, filtros por permisos y neutralización de fórmulas CSV
  cuando corresponda. Verificar sin exponer información comercial en errores.
- Auditoría y preservación de historia, incluida eliminación de usuario/subtarea.

## Datos y HTTP
Usar sintéticos; nunca copiar producción sin autorización específica. No guardar
credenciales, tokens, cookies, dumps ni logs completos en evidencia. Revisar solo
fragmentos depurados. No habilitar DEBUG público para reproducir Dashboard.
data/.htaccess y data/web.config existen; verificar servidor efectivo y denegación
HTTP de configuración, base, backups, Git y harness. .gitignore no protege HTTP.
Preservar acceso legítimo a adjuntos mediante controladores. No desplegar el checkout.

Hallazgo estático no equivale a vulnerabilidad reproducida: registrar alcance,
precondiciones, evidencia y mitigación proporcional. Pruebas en entorno autorizado.
