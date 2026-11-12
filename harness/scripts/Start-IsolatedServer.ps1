param([ValidateRange(1024, 65535)][int]$Port = 18001, [switch]$Plugin)
. (Join-Path $PSScriptRoot 'TestEnvironment.ps1')
if ($Plugin) { Enable-RvSoftwareTestPlugin }
Write-Output "Synthetic SQLite only. Listening on http://127.0.0.1:$Port; stop with Ctrl+C."
& $testPhp @testPhpArgs -S "127.0.0.1:$Port" -t $testRoot
exit $LASTEXITCODE
