. (Join-Path $PSScriptRoot 'CompatibilityEnvironment.ps1')
$compatData = Join-Path $compatRuntime 'maria-data'
if (-not (Test-Path -LiteralPath (Join-Path $compatData 'my.ini'))) { throw 'Initialize the dedicated portable database directory first' }
& (Join-Path $compatMaria 'mariadbd.exe') --no-defaults "--basedir=$([IO.Path]::GetFullPath((Join-Path $compatMaria '..')))" "--datadir=$compatData" --bind-address=127.0.0.1 --port=13318 --skip-name-resolve --log-bin=compatibility-bin --server-id=1189 --binlog-format=ROW --console
exit $LASTEXITCODE
