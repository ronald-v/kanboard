# RvSoftware

Plugin MIT para Kanboard. Versión interna 0.1.0, comprobada sobre el commit
60d2caf29edfa58e313b17cadaab76b6d4307742 (posterior a v1.2.57).
El rango declarado >=1.2.57 permite al loader realizar su comprobación nativa;
no demuestra compatibilidad con toda versión futura. Probar antes de actualizar.

Copiar esta carpeta completa en plugins/RvSoftware. Requiere stack nativo PHP,
PDO y base de Kanboard; no añade servicios ni dependencias. No conectar una copia
nueva a datos reales sin revisar migraciones de núcleo y plugin.

Ruta sin rewrite: `?controller=RvHomeController&action=show&plugin=RvSoftware`.
Requiere sesión; conserva autenticación y autorización nativas. No altera CSP.
Desde F-007, Mi tiempo registra sesiones exactas con nota, fecha, duración,
temporizador único, correcciones auditadas y claves de reintento. API de usuario:
rvCreateTime, rvCorrectTime y rvGetMyTime. El API nativo de timers usa el mismo
servicio. Los acumulados no se editan directamente: registrar una sesión fechada.
Esquema actual 6: metadata/auditoría/FK restrict, snapshots históricos opacos,
clasificación/aprobación, clientes/bolsas, ledger y mutex por usuario/tarea/proyecto.
SQLite y MariaDB 11.8.9 probados localmente; PHP 8.5.12 incluido en el ensayo. El plugin
solo habilita sus servicios para SQLite/MySQL, no para otros motores.
Esquema inicial 1 sin DDL; las versiones 2..6 incorporan el dominio. MySQL tiene
migraciones ensayadas en MariaDB; verificar también las condiciones del hosting
antes de producción. Sus FLOAT nativos son una proyección aproximada en horas;
duraciones exactas y consumos se mantienen en segundos enteros.
El loader migra antes de initialize: revisar y respaldar antes de arrancar.

Revisión de tiempo permite al líder/administrador clasificar, aprobar y rectificar.
Miembro consulta sus propias horas; líder las de sus proyectos y administrador
todo. Aprobación facturable requiere bolsa del mismo proyecto. Se permite saldo
negativo con aviso. Rectificar compensa sin borrar movimientos. El historial de
bolsa/proyecto se conserva; no se traslada una tarea con sesiones aprobadas.

Clientes y bolsas ofrece asignación de cliente, capacidad, vigencia opcional,
ampliaciones, archivo, ajustes con motivo e historial. El saldo suma movimientos.
Reportes filtra por días trabajados en America/Bogota, recorta sesiones que cruzan
medianoche y exporta CSV con segundos exactos y protección de fórmulas. Históricos
opacos sin sesiones no se atribuyen a usuarios. No hay tarifas, facturas, cierres
mensuales ni renovaciones automáticas.

Instalación/recuperación: [INSTALL.md](INSTALL.md). Una entrega de desarrollo no
autoriza producción ni acredita compatibilidad con un hosting todavía no probado.

Pruebas locales: powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1
-Plugin -Filter RvSoftware. El harness es exclusivo de desarrollo y no se despliega.
Para deshabilitar, retirar la carpeta de plugins en una copia revisada y reiniciar
PHP; no elimina datos ni deshace migraciones. Mantener licencia MIT de raíz.
