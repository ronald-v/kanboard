<?php
// Load definitions only: no application bootstrap, database connection or migration.
$root = $argv[1] ?? '';
if (! $root || ! is_dir($root)) { exit(2); }
ob_start();
try {
    foreach (array($root.'/data/config.php',$root.'/config.php') as $config) {
        if (is_file($config)) { require $config; }
    }
    $valid = defined('DB_DRIVER') && DB_DRIVER === 'mysql';
    foreach (array('DB_HOSTNAME','DB_NAME','DB_USERNAME') as $name) {
        $valid = $valid && defined($name) && is_string(constant($name)) && constant($name) !== '';
    }
    $valid = $valid && defined('DB_PASSWORD') && is_string(DB_PASSWORD);
    $placeholders = array('NOMBRE_DE_LA_BASE','USUARIO_DE_LA_BASE','CONTRASENA_DE_LA_BASE');
    if ($valid) {
        foreach (array('DB_NAME','DB_USERNAME','DB_PASSWORD') as $name) {
            if (in_array(constant($name),$placeholders,true)) { $valid = false; }
        }
    }
    ob_end_clean();
    if (! $valid) { fwrite(STDERR,"Completa la configuracion MariaDB (DB_DRIVER=mysql y DB_*), sin marcadores de ejemplo.\n"); exit(2); }
    echo "Configuracion MariaDB presente; valores y credenciales no mostrados.\n";
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR,"No se pudo cargar la configuracion local. Revisa su sintaxis y las constantes duplicadas.\n");
    exit(2);
}
