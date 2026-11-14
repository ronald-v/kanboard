param([ValidateRange(1024,65535)][int]$Port = 18001)
$ErrorActionPreference = 'Stop'
$localRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$localPhp = (Get-Command php -ErrorAction Stop).Source
if (-not ((Test-Path -LiteralPath (Join-Path $localRoot 'data/config.php')) -or (Test-Path -LiteralPath (Join-Path $localRoot 'config.php')))) {
    throw 'Create data/config.php from config.mariadb.example.php first.'
}
# Remote development databases can take longer during the first schema setup.
# This limit applies only to this loopback development server process.
$localPhpArgs = @('-d', 'max_execution_time=0')
foreach ($extension in @('gd','mbstring','openssl','pdo_mysql')) {
    $loaded = & $localPhp -r "echo extension_loaded('$extension') ? 'yes' : 'no';"
    if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect PHP extensions' }
    if ($loaded -eq 'no') { $localPhpArgs += @('-d', "extension=$extension") }
}
# The user explicitly selected the local config files, rather than DATABASE_URL.
Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue
& $localPhp @localPhpArgs (Join-Path $PSScriptRoot 'Validate-LocalConfig.php') $localRoot
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
Write-Output "Local MariaDB config; application startup can create/migrate tables. http://127.0.0.1:$Port"
Write-Output 'DEBUG and PLUGIN_INSTALLER are taken from your config. Stop with Ctrl+C.'
& $localPhp @localPhpArgs -S "127.0.0.1:$Port" -t $localRoot
exit $LASTEXITCODE
