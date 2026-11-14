<?php
// Copia este archivo a data/config.php y reemplaza los valores en el servidor.
// MariaDB utiliza el controlador mysql de Kanboard y la extensión PDO MySQL.
define('DB_DRIVER', 'mysql');
define('DB_HOSTNAME', 'localhost'); // Usa el host indicado en Hostinger.
define('DB_PORT', 3306);
define('DB_NAME', 'NOMBRE_DE_LA_BASE');
define('DB_USERNAME', 'USUARIO_DE_LA_BASE');
define('DB_PASSWORD', 'CONTRASENA_DE_LA_BASE');

define('DEBUG', false);
define('PLUGIN_INSTALLER', false);
