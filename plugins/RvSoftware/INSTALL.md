# Instalación y recuperación

Versión interna 0.1.0; schema 6. Base exacta:
60d2caf29edfa58e313b17cadaab76b6d4307742, posterior a v1.2.57. No es una nueva
versión oficial de Kanboard. Mantener LICENSE MIT y avisos de dependencias.

El paquete incluye PHP, vendor/libs de runtime y assets compilados. No necesita
Node ni Composer en hosting. Requiere PHP >=8.1 y las extensiones que verifica
app/check_setup.php: PDO del motor, GD, mbstring, OpenSSL, JSON, ctype, session,
DOM, SimpleXML, XML y las funciones hash/filter. PHP 8.3.27 + SQLite es el entorno
ensayado. MySQL/MariaDB requiere ensayo previo en su versión exacta. PostgreSQL
y SQL Server no están habilitados para este plugin.

## Antes de activar

1. Identificar destino y versión de PHP/servidor/motor. Comparar sus requisitos
   con el manifiesto del ZIP y verificar el SHA256 entregado. No desplegar el
   checkout ni archivos de desarrollo. El manifiesto de desarrollo declara
   productionAuthorized=false; no representa autorización de producción.
2. Preparar staging con datos sintéticos y directorios persistentes fuera del
   document root para DATA_DIR, FILES_DIR y CACHE_DIR. SQLite usa DB_FILENAME
   absoluto fuera del sitio. Para MySQL configurar DB_DRIVER=mysql, DB_HOSTNAME,
   DB_PORT, DB_NAME, DB_USERNAME y DB_PASSWORD mediante el entorno del proceso PHP.
   Nunca incluir secretos en el ZIP/Git ni exponer DATABASE_URL en diagnósticos.
   No mezclar DATABASE_URL con otra configuración sin revisar su precedencia.
3. Mantener PLUGIN_INSTALLER=false, DEBUG=false y logs fuera del sitio. Usar
   HTTPS y los mecanismos nativos de autenticación. No añadir unsafe-inline/eval
   a la CSP para resolver cambios visuales. Configurar cron nativo si se necesita.
4. Guardar juntos código compatible, base y adjuntos, con escrituras detenidas.
   SQLite en WAL requiere backup coherente (API backup de SQLite o procedimiento
   documentado), no copiar solo el archivo principal mientras está activo.
   MySQL requiere respaldo y restauración propios del motor ensayados en copia.
5. Migrar una copia aislada primero. El loader migra plugins al arrancar, incluso
   si la expectativa sobre DB_RUN_MIGRATIONS era otra. MySQL DDL puede hacer
   commits implícitos: no asumir rollback de una migración parcialmente fallida.

## Servidor web y verificación

Conservar reglas nativas .htaccess/web.config y asegurar que el servidor las
aplique realmente. Apache/LiteSpeed necesita reglas efectivas de acceso; Nginx
requiere configuración explícita equivalente. No usar el servidor PHP integrado
en producción. Denegar por HTTP data, app, libs, vendor, archivos de configuración,
backups, cli, archivos ocultos/Git y rutas de desarrollo; permitir los entrypoints
index.php/jsonrpc.php y los assets públicos necesarios, incluidos los del plugin.
Los directorios de datos fuera del sitio son una defensa adicional. Verificar
las reglas en el servidor exacto, también con URL codificada/variantes de ruta.

Smoke obligatorio de staging: PHP ejecutado sin descargar fuente, login, Dashboard,
Home/Proyectos, tareas/subtareas, timer único persistido, registro manual,
aprobación/consumo, rectificación/compensación, reporte/CSV y adjuntos por
controlador. Miembro no accede a horas ajenas/saldos; líder solo sus proyectos.
Probar URLs/API directas y revocación. Probar que config, base, dumps y Git no se
descargan. El ensayo local no sustituye esta comprobación del hosting.

## Restauración

Ante fallo detener escrituras y conservar diagnóstico depurado. Restaurar código,
base y archivos de la misma copia en una ubicación aislada; verificar login,
auditoría, saldos, reporte, hashes de adjuntos e integridad antes de servir tráfico.
Revertir solo código no revierte esquema. No eliminar tablas/sesiones/ledger ni
desactivar el plugin para intentar deshacer consumos. Sus FK restringen borrados
para conservar historia. Ensayar tiempos de recuperación antes de una migración
real; no hay recuperación productiva acreditada por una prueba sintética local.

Si una actualización upstream cambia firmas de servicios, ACL o plantillas,
comparar con esta base exacta y repetir suites/migración/restauración. La versión
upstream destino y la versión propia deben elegirse expresamente antes de release.
