# Dedicated process only. Never use this helper for hosting or external databases.
$ErrorActionPreference = 'Stop'
$compatRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$compatRuntime = Join-Path $compatRoot '.runtime/compatibility'
$compatPhp = Join-Path $compatRuntime 'php85/php.exe'
$compatMaria = Join-Path $compatRuntime 'mariadb1189/mariadb-11.8.9-winx64/bin'
if (-not (Test-Path -LiteralPath $compatPhp)) { throw 'Run Initialize-Compatibility.py first' }
$compatPhpArgs = @('-n', '-d', ('extension_dir=' + (Join-Path $compatRuntime 'php85/ext')))
foreach ($extension in @('gd','mbstring','openssl','pdo_mysql','pdo_sqlite','zip','ldap')) {
    $compatPhpArgs += @('-d', "extension=$extension")
}
foreach ($config in @('config.php','data/config.php')) {
    if (Test-Path -LiteralPath (Join-Path $compatRoot $config)) { throw 'Unreviewed local config blocks isolated compatibility test' }
}
Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue
$env:DB_DRIVER = 'mysql'
$env:DB_HOSTNAME = '127.0.0.1'
$env:DB_PORT = '13318'
$env:DB_USERNAME = 'compat_synthetic'
$env:DB_PASSWORD = 'synthetic-local-only'
$env:DB_NAME = 'kanboard_compat_http'
$env:DEBUG = 'false'
$env:LOG_DRIVER = 'stderr'
$env:PLUGIN_INSTALLER = 'false'
$env:DB_RUN_MIGRATIONS = 'true'
$env:RVSOFTWARE_TEST = 'true'
$env:DATA_DIR = Join-Path $compatRuntime 'data'
$env:FILES_DIR = Join-Path $env:DATA_DIR 'files'
$env:CACHE_DIR = Join-Path $env:DATA_DIR 'cache'
foreach ($path in @($env:DATA_DIR,$env:FILES_DIR,$env:CACHE_DIR,(Join-Path $compatRuntime 'plugins'))) {
    New-Item -ItemType Directory -Force -Path $path | Out-Null
}
Copy-Item -LiteralPath (Join-Path $compatRoot 'plugins/RvSoftware') -Destination (Join-Path $compatRuntime 'plugins') -Recurse -Force
$env:PLUGINS_DIR = Join-Path $compatRuntime 'plugins'
Set-Location -LiteralPath $compatRoot
