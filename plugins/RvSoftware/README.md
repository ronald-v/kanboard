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
Esquema inicial 1 sin DDL: versión en plugin_schema_versions nativo, sin tablas
de negocio prematuras. MySQL solo incluye baseline sin DDL; se verifica en motor
objetivo antes de una entrega que lo utilice. El loader migra antes de initialize.

Pruebas locales: powershell -NoProfile -File harness/scripts/Invoke-UnitTests.ps1
-Plugin -Filter RvSoftware. El harness es exclusivo de desarrollo y no se despliega.
Para deshabilitar, retirar la carpeta de plugins en una copia revisada y reiniciar
PHP; no elimina datos ni deshace migraciones. Mantener licencia MIT de raíz.
