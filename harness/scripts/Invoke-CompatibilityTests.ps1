param([switch]$Native, [switch]$Sqlite, [string]$Filter = '')
. (Join-Path $PSScriptRoot 'CompatibilityEnvironment.ps1')
& $compatPhp @compatPhpArgs (Join-Path $PSScriptRoot 'Initialize-CompatibilityDb.php')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
$env:DB_NAME = 'kanboard_unit_test'
if ($Native) {
    $env:RVSOFTWARE_TEST = 'false'
    $env:PLUGINS_DIR = Join-Path $compatRuntime 'plugins-disabled'
    New-Item -ItemType Directory -Force -Path $env:PLUGINS_DIR | Out-Null
}
$compatXml = 'tests/units.mysql.xml'
if ($Sqlite) { $compatXml = 'tests/units.sqlite.xml'; $env:DB_DRIVER = 'sqlite'; $env:DB_FILENAME = ':memory:' }
$compatConfig = [xml](Get-Content (Join-Path $compatRoot $compatXml) -Raw)
$compatConfig.phpunit.SetAttribute('stopOnError','false')
$compatConfig.phpunit.SetAttribute('stopOnFailure','false')
$compatConfig.SelectSingleNode('/phpunit/testsuites/testsuite/directory').InnerText = Join-Path $compatRoot 'tests/units'
$compatConfigPath = Join-Path $compatRuntime ('units.' + $(if ($Sqlite) { 'sqlite' } else { 'mysql' }) + $(if ($Native) { '.native' } else { '.plugin' }) + '.xml')
$compatConfig.Save($compatConfigPath)
$compatTestArgs = @('-c',$compatConfigPath,'--bootstrap',(Join-Path $compatRoot '.runtime/vendor/autoload.php'),'--do-not-cache-result','--colors=never')
if (-not $Native -and -not $Filter) { $Filter = 'RvSoftware' }
if ($Filter) { $compatTestArgs += @('--filter',$Filter) }
& $compatPhp @compatPhpArgs (Join-Path $compatRoot '.runtime/vendor/bin/phpunit') @compatTestArgs
exit $LASTEXITCODE
