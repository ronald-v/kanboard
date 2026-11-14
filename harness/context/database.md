# Persistencia, migraciones e integridad

## Existente comprobado
PicoDB/PDO: app/ServiceProvider/DatabaseProvider.php; libs/picodb.
SQLite predeterminado, archivo data/db.sqlite; no base local creada en esta entrega.
MySQL/MariaDB y PostgreSQL disponibles en código; SQL Server tiene rutas adicionales,
sin selección para este proyecto. Motor Hostinger pendiente.
Esquemas app/Schema/Sqlite.php (128), Mysql.php (139), Postgres.php (117),
Mssql.php (3); números independientes por motor, no versiones del producto.
app/Schema/Sql contiene SQL upstream legítimo: no ignorar todos los *.sql.

Núcleo: DatabaseProvider registra base y migra por defecto. Plugin:
app/Core/Plugin/SchemaHandler.php usa Schema/<Driver>.php, namespace del plugin,
VERSION y funciones version_N; plugin_schema_versions guarda avance.
Loader carga esquema antes de initialize. No se observó guarda DB_RUN_MIGRATIONS
en esa ruta. No cargar una versión nueva contra una base real para inspeccionarla.

El handler intenta transacción y controla claves foráneas; el efecto de DDL,
rollback y actualización del número de versión debe probarse en el motor objetivo.
No asumir que una transacción PHP garantiza rollback de todo DDL de MySQL.

## Unidades y relaciones
tasks/subtasks: time_estimated y time_spent en horas; tipos numéricos según motor.
subtask_time_tracking: id, user_id, subtask_id, start/end enteros y time_spent;
SQLite usa REAL para duración. end=0 indica abierto. Redondeo local: horas a 2
decimales. Ver [tiempo](time-billing.md).
En SQLite versión 126 las sesiones tienen FK con ON DELETE CASCADE para usuario
y subtarea. Un ledger comercial no debe desaparecer al borrar su origen: definir
restricciones y snapshots de trazabilidad antes de implementarlo.

## Diseño pendiente de implementación
Mantener capa nativa y tablas del plugin con nombres propios, claves y migraciones
versionadas. Preferir segundos enteros derivados de start/end para sesiones nuevas;
no convertir ni redondear históricos silenciosamente. Dinero en DECIMAL o unidad
menor entera con escala aprobada; CurrencyModel no garantiza ese contrato.
Idempotencia con clave única de operación/origen, transición condicional del estado
y transacción que cubra cierre, proyección y consumo. Probar doble clic, reintento
y concurrencia con conexiones independientes. Reconciliar saldo desde movimientos.

Consultas parametrizadas, índices por relaciones/filtros y auditoría de actor/motivo.
No imponer SP-only, DB/ o migraciones manuales fuera de la convención. Los scripts
auxiliares requieren utilidad y entorno comprobados.

## Pruebas y recuperación
Unitarias SQLite en memoria; motor productivo también requerido antes de release.
tests/units/Base.php recrea bases en MySQL/PostgreSQL: usar destinos aislados.
Backup/restauración conjunta de base y archivos con código compatible. Ningún
backup, dump o migración fue ejecutado aquí.
Fuente: [migraciones de plugins](https://docs.kanboard.org/v1/plugins/schema_migrations/).
