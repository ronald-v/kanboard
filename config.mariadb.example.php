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

// SMTP y seguridad de Projecta: secretos mediante variables de entorno.
require dirname(__DIR__).'/config/projecta.php'; // Este archivo se copia a data/config.php.
// PROJECTA_KEY: clave Base64 estable de 32 bytes; respáldala fuera del artefacto.
// PROJECTA_SMTP_HOST, PROJECTA_SMTP_PORT=587, PROJECTA_SMTP_ENCRYPTION=tls,
// PROJECTA_SMTP_USER, PROJECTA_SMTP_PASSWORD, PROJECTA_SMTP_FROM.
