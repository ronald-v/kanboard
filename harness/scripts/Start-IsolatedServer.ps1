param([ValidateRange(1024, 65535)][int]$Port = 18001)
. (Join-Path $PSScriptRoot 'TestEnvironment.ps1')
Write-Output "Synthetic SQLite only. Listening on http://127.0.0.1:$Port; stop with Ctrl+C."
& $testPhp @testPhpArgs -S "127.0.0.1:$Port" -t $testRoot
exit $LASTEXITCODE
