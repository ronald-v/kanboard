param([string]$ComposerPhar = '')
. (Join-Path $PSScriptRoot 'TestEnvironment.ps1')
if (-not $ComposerPhar) {
    $ComposerPhar = Join-Path (Split-Path (Get-Command composer -ErrorAction Stop).Source) 'composer.phar'
}
if (-not (Test-Path -LiteralPath $ComposerPhar -PathType Leaf)) { throw 'Specify -ComposerPhar' }
& $testPhp @testPhpArgs $ComposerPhar --no-plugins --no-scripts validate --no-check-publish
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
& $testPhp @testPhpArgs $ComposerPhar --no-plugins --no-scripts install --prefer-dist --no-interaction --no-progress
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
& $testPhp @testPhpArgs $ComposerPhar --no-plugins --no-scripts check-platform-reqs --lock
exit $LASTEXITCODE
