# F-012 · Configuración y despliegue documentados

Fecha: 2026-11-14T08:16:10-05:00. Rama feat/012.

`app/common.php` procesa DATABASE_URL y luego data/config.php/config.php;
`app/constants.php` admite variables DB_* y PDO mysql. La plantilla incorpora
solo marcadores, y el README distingue su uso del servidor SQLite aislado.

`php -l config.mariadb.example.php`: sin errores. `git check-ignore data/config.php
config.php`: ambos ignorados. `git diff --check`: sin errores. El paquete se
comprueba con Build-Release.py; la guía del plugin acompaña al ejemplo en el ZIP.
No se ha verificado configuración ni recuperación en Hostinger; F-012 sigue blocked.
