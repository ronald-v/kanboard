# Dot-source only from a dedicated PowerShell process: environment is process-local.
$ErrorActionPreference = 'Stop'
$testRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$testRuntime = Join-Path $testRoot '.runtime'
$testPhp = (Get-Command php -ErrorAction Stop).Source
$testPhpArgs = @()
foreach ($extension in @('gd', 'pdo_sqlite', 'zip', 'ldap')) {
    $loaded = & $testPhp -r "echo extension_loaded('$extension') ? 'yes' : 'no';"
    if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect PHP extensions' }
    if ($loaded -eq 'no') { $testPhpArgs += @('-d', "extension=$extension") }
}
foreach ($config in @('config.php', 'data/config.php', '.runtime/data/config.php')) {
    if (Test-Path -LiteralPath (Join-Path $testRoot $config)) {
        throw "Refusing to boot with an unreviewed config: $config"
    }
}
foreach ($directory in @('data', 'data/files', 'data/cache', 'plugins')) {
    New-Item -ItemType Directory -Force -Path (Join-Path $testRuntime $directory) | Out-Null
}
# DATABASE_URL takes precedence over native constants. Never inherit a real target.
Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue
$env:DB_DRIVER = 'sqlite'
$env:DB_FILENAME = Join-Path $testRuntime 'data/db.sqlite'
$env:DATA_DIR = Join-Path $testRuntime 'data'
$env:FILES_DIR = Join-Path $testRuntime 'data/files'
$env:CACHE_DIR = Join-Path $testRuntime 'data/cache'
$env:PLUGINS_DIR = Join-Path $testRuntime 'plugins'
$env:PLUGIN_INSTALLER = 'false'
$env:DB_RUN_MIGRATIONS = 'true'
$env:LOG_DRIVER = 'stderr'
$env:LOG_FILE = Join-Path $testRuntime 'debug.log'
$env:DEBUG = 'false'
$env:COMPOSER_VENDOR_DIR = Join-Path $testRuntime 'vendor'
Set-Location -LiteralPath $testRoot
