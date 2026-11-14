param([ValidateRange(1024,65535)][int]$Port = 18001, [switch]$Profile,
    [ValidateSet('Current','Before')][string]$ProfileVariant = 'Current')
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
$localDocumentRoot = $localRoot
if ($ProfileVariant -eq 'Before') {
    if (-not $Profile) { throw 'Before is available only in the opt-in profiler.' }
    $localDocumentRoot = Join-Path $localRoot '.runtime/f019/before/app'
    if (-not (Test-Path -LiteralPath (Join-Path $localDocumentRoot 'index.php'))) { throw 'F-019 baseline copy is missing. Do not reconstruct it from modified working sources.' }
    foreach ($config in @('config.php','data/config.php')) {
        if (Test-Path -LiteralPath (Join-Path $localDocumentRoot $config)) { throw 'Private configuration is forbidden in the baseline copy.' }
    }
    $profileManifestPath = Join-Path $localRoot '.runtime/f019/baseline-manifest.json'
    if (-not (Test-Path -LiteralPath $profileManifestPath)) { throw 'Baseline provenance manifest missing.' }
    $profileManifest = Get-Content -LiteralPath $profileManifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($entry in $profileManifest.product_files.PSObject.Properties) {
        $profileFile = [IO.Path]::GetFullPath((Join-Path $localDocumentRoot $entry.Name))
        $profilePrefix = [IO.Path]::GetFullPath($localDocumentRoot).TrimEnd('\') + '\'
        if (-not $profileFile.StartsWith($profilePrefix, [StringComparison]::OrdinalIgnoreCase)) { throw 'Baseline manifest path is outside its root.' }
        if ((Get-FileHash -LiteralPath $profileFile -Algorithm SHA256).Hash.ToLowerInvariant() -ne $entry.Value) { throw 'Baseline source differs from its preserved manifest.' }
    }
}
$localServerArgs = @('-S', "127.0.0.1:$Port", '-t', $localDocumentRoot)
if ($Profile) {
    $localPreflightArgs = @((Join-Path $PSScriptRoot 'Measure-LocalDatabase.php'))
    if ($ProfileVariant -eq 'Before') { $localPreflightArgs += '--baseline' }
    $localPreflight = & $localPhp @localPhpArgs @localPreflightArgs
    if ($LASTEXITCODE -ne 0) { throw 'Read-only schema preflight failed; profiler not started.' }
    $localSchema = $localPreflight | ConvertFrom-Json
    if (-not $localSchema.application_boot_allowed) { throw 'Schema mismatch: profiler must not trigger real migrations.' }
    $profileDirectory = Join-Path ([IO.Path]::GetTempPath()) 'Projecta-F019'
    New-Item -ItemType Directory -Force -Path $profileDirectory | Out-Null
    $env:PROJECTA_LOCAL_PROFILE_FILE = Join-Path $profileDirectory ('requests-' + $PID + '-' + [DateTimeOffset]::UtcNow.ToUnixTimeSeconds() + '.jsonl')
    $env:PROJECTA_PROFILE_APP_ROOT = $localDocumentRoot
    $env:PROJECTA_PROFILE_VARIANT = $ProfileVariant
    Write-Output ('Opt-in aggregate profiler; normal user activity only. Metrics outside document root: ' + $env:PROJECTA_LOCAL_PROFILE_FILE)
    $localServerArgs += (Join-Path $PSScriptRoot 'Profile-LocalRequest.php')
}
& $localPhp @localPhpArgs @localServerArgs
exit $LASTEXITCODE
