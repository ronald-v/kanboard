# DEC-024 · Arranque local con configuración MariaDB

Fecha: 2026-11-14T08:45:59-05:00. Feature F-012.
Evidencia: [arranque local](../evidence/2026-11-14-f012-arranque-local.md).

Ronnie eligió expresamente usar MariaDB de data/config.php ante el bloqueo del
lanzador aislado. Se añade Start-LocalServer.ps1, conservando el perfil SQLite
original y la configuración del usuario. DEBUG/PLUGIN_INSTALLER true no causan
el bloqueo: era la guarda de TestEnvironment.ps1. No se exige desactivarlos para
este servidor loopback. Se elimina DATABASE_URL del proceso para usar el archivo.

El primer acceso con el límite PHP original agotó tiempo durante la migración
nativa. Se verificó schema_version=115 y ambas columnas de version_116 presentes,
VARCHAR(255) nullable. Se reparó exclusivamente el marcador a 116, con transacción
y comprobaciones previas, sin borrar columnas ni modificar filas de negocio.
El lanzador local elimina el límite de ejecución; el siguiente acceso completó
el esquema nativo 139 y plugin 6. No se incorpora una reparación automática de
schema a los lanzadores ni se usa esta base para pruebas de datos sintéticos.

Siguiente: usar Start-LocalServer.ps1 para esa conexión. Staging/hosting efectivo
siguen pendientes; no se publica código en Hostinger ni se declara F-012 validada.
