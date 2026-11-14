param([string]$Filter = '', [switch]$ContinueOnFailure)
. (Join-Path $PSScriptRoot 'TestEnvironment.ps1')
$testRunner = Join-Path $testRuntime 'vendor/bin/phpunit'
if (-not (Test-Path -LiteralPath $testRunner) -or
    -not (Test-Path -LiteralPath (Join-Path $testRuntime 'vendor/autoload.php'))) {
    throw 'Wait for Initialize-TestEnvironment.ps1 to finish successfully first'
}
$testArgs = @('-c', 'tests/units.sqlite.xml', '--bootstrap', (Join-Path $testRuntime 'vendor/autoload.php'), '--do-not-cache-result', '--colors=never')
if ($ContinueOnFailure) {
    # Inventory all baseline failures without changing or weakening upstream XML.
    $testConfig = [xml](Get-Content (Join-Path $testRoot 'tests/units.sqlite.xml') -Raw)
    $testConfig.phpunit.SetAttribute('stopOnError', 'false')
    $testConfig.phpunit.SetAttribute('stopOnFailure', 'false')
    $testConfig.SelectSingleNode('/phpunit/testsuites/testsuite/directory').InnerText = (Join-Path $testRoot 'tests/units')
    $testConfigPath = Join-Path $testRuntime 'units.sqlite.xml'
    $testConfig.Save($testConfigPath)
    $testArgs[1] = $testConfigPath
}
if ($Filter) { $testArgs += @('--filter', $Filter) }
& $testPhp @testPhpArgs $testRunner @testArgs
exit $LASTEXITCODE
