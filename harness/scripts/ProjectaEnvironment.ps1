# Dedicated child process. This profile never loads the user's configuration.
param([string]$Script = '', [string[]]$ScriptArgs = @(), [int]$Port = 0, [switch]$Native)
$ErrorActionPreference = 'Stop'
$projectaRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$projectaSandbox = Join-Path $projectaRoot '.runtime/projecta/app'
$projectaPhp = Join-Path $projectaRoot '.runtime/compatibility/php85/php.exe'
if (-not (Test-Path (Join-Path $projectaSandbox 'app/common.php'))) { throw 'Prepare-ProjectaSandbox.py first' }
foreach ($name in @('config.php','data/config.php')) {
    if (Test-Path (Join-Path $projectaSandbox $name)) { throw 'Private config forbidden in sandbox' }
}
Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue
$env:DB_DRIVER = 'mysql'
$env:DB_HOSTNAME = '127.0.0.1'
$env:DB_PORT = '13318'
$env:DB_NAME = 'kanboard_compat_http'
$env:DB_USERNAME = 'compat_synthetic'
$env:DB_PASSWORD = 'synthetic-local-only'
$env:DATA_DIR = Join-Path $projectaRoot '.runtime/projecta/data'
$env:PLUGINS_DIR = Join-Path $projectaSandbox 'plugins'
$env:DEBUG = 'false'
$env:LOG_DRIVER = 'stderr'
$env:PLUGIN_INSTALLER = 'false'
$env:RVSOFTWARE_TEST = 'true'
$env:PROJECTA_KEY = 'c3ludGhldGljLW9ubHktMzItYnl0ZS1rZXktMDAwMDA='
$env:PROJECTA_BASE_URL = 'http://127.0.0.1:18100'
$env:PROJECTA_PRIVATE_DIR = Join-Path $projectaRoot '.runtime/projecta/private'
$env:PROJECTA_OAUTH_REDIRECT_URIS = '["http://127.0.0.1:18119/callback"]'
if ($Native) {
    $env:RVSOFTWARE_TEST='false'
    $env:PLUGINS_DIR=Join-Path $projectaRoot '.runtime/plugins-disabled'
}
foreach ($directory in @($env:DATA_DIR,(Join-Path $env:DATA_DIR 'files'),(Join-Path $env:DATA_DIR 'cache'))) {
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
}
$projectaArgs = @('-n','-d',('extension_dir='+ (Join-Path $projectaRoot '.runtime/compatibility/php85/ext')))
if (-not $Native) { $projectaArgs += @('-d',('auto_prepend_file='+(Join-Path $projectaRoot 'config/projecta.php'))) }
foreach ($extension in @('gd','mbstring','openssl','pdo_mysql','pdo_sqlite','zip','sodium')) { $projectaArgs += @('-d',"extension=$extension") }
if ($Native) { $projectaArgs += @('-d','extension=ldap') }
if ($env:PROJECTA_TEST_CA_FILE) { $projectaArgs += @('-d',('openssl.cafile='+$env:PROJECTA_TEST_CA_FILE)) }
if ($Port) {
    if ($Port -lt 18100 -or $Port -gt 18119) { throw 'Reserved synthetic ports only' }
    & $projectaPhp @projectaArgs -S "127.0.0.1:$Port" -t $projectaSandbox
} else {
    & $projectaPhp @projectaArgs $Script @ScriptArgs
}
exit $LASTEXITCODE
