# F-012 · Lanzador local MariaDB

Fecha: 2026-11-14T08:45:59-05:00. Rama feat/012.

El comando original falla por una guarda deliberada ante data/config.php. El
usuario eligió usar su MariaDB. Nuevo Start-LocalServer.ps1 carga ese archivo y
habilita extensiones PHP por proceso, en loopback y sin cambiar sus constantes.
El archivo de configuración y sus credenciales no se copian al repositorio.

Verificación: lint de Validate-LocalConfig.php sin errores; parser PowerShell
sin errores; fixtures sintéticos valid/invalid/syntax devuelven 0/2/2 sin revelar
el marcador de contraseña. Estos preflights no conectan ni migran una base.

Arranque real autorizado por el usuario: configuración válida, servidor local en
18001. Puerto de la base remota accesible. El primer acceso agotó tiempo durante
migración, dejando el marcador 115 y las dos columnas de 116 ya creadas. Se
verificaron sus tipos y nulabilidad, y se actualizó únicamente el marcador 116
en transacción. Tras reiniciar con max_execution_time=0, el bootstrap nativo
terminó: schema_version 139 y plugin_schema_versions 6. No se consultaron filas
de negocio, usuarios ni credenciales en la evidencia. No se ejecutaron suites
destructivas contra esta conexión. Staging/servidor Hostinger siguen pendientes.

Petición HTTP final a 127.0.0.1:18001: status 200, formulario de contraseña presente,
sin error de migración ni Fatal error. Test-Harness.ps1 se ejecuta tras registrar
la evidencia; es coherencia estática, no una prueba de despliegue.
