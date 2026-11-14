param([ValidateSet(18015,18017)][int]$Port = 18015)
. (Join-Path $PSScriptRoot 'CompatibilityEnvironment.ps1')
Write-Output "Synthetic MariaDB only, PHP 8.5: http://127.0.0.1:$Port"
& $compatPhp @compatPhpArgs -S "127.0.0.1:$Port" -t $compatRoot
exit $LASTEXITCODE
