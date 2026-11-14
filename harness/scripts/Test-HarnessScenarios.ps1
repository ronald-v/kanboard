param([string]$RootPath = (Join-Path $PSScriptRoot '../..'))

# Synthetic fixtures of the static validator, not Kanboard or agent simulations.
$ErrorActionPreference = 'Stop'
$source = [IO.Path]::GetFullPath($RootPath).TrimEnd([char[]]'\/')
$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd([char[]]'\/')
$scratch = Join-Path $tempRoot ('kanboard-harness-' + [guid]::NewGuid().ToString('N'))
$scratch = [IO.Path]::GetFullPath($scratch)
$utf8 = New-Object System.Text.UTF8Encoding($false)
$results = New-Object 'System.Collections.Generic.List[object]'

function Write-JsonFile([string]$Path, $Value) {
    [IO.File]::WriteAllText($Path, ($Value | ConvertTo-Json -Depth 30), $script:utf8)
}
function Read-Features([string]$Fixture) {
    return (Get-Content -LiteralPath (Join-Path $Fixture 'harness/feature-list.json') -Raw -Encoding UTF8 | ConvertFrom-Json)
}
function Save-Features([string]$Fixture, $Value) {
    Write-JsonFile (Join-Path $Fixture 'harness/feature-list.json') $Value
}
function New-Fixture([string]$Name) {
    $fixture = Join-Path $script:scratch $Name
    New-Item -ItemType Directory -Path $fixture | Out-Null
    Copy-Item -LiteralPath (Join-Path $script:source 'harness') -Destination $fixture -Recurse
    Copy-Item -LiteralPath (Join-Path $script:source 'AGENTS.md') -Destination $fixture
    $fl = Read-Features $fixture
    # Each scenario starts from a known synthetic state, independent of progress
    # in the real backlog (e.g. F-001 may already be validated there).
    $fl.activeFeatureId = $null
    foreach ($feature in $fl.features) {
        $feature.status = 'planned'
        $feature.blockers = @()
        $feature.suspensionApproval = $null
        foreach ($subtask in $feature.subtasks) {
            $subtask.status = 'planned'
            $subtask.blockers = @()
        }
    }
    $fl.features[0].status = 'blocked'
    $fl.features[0].blockers = @('Synthetic missing test environment')
    $fl.features[0].subtasks[0].status = 'validated'
    $fl.features[0].subtasks[1].status = 'blocked'
    $fl.features[0].subtasks[1].blockers = @('Synthetic missing test environment')
    Save-Features $fixture $fl
    $pm = Get-Content -LiteralPath (Join-Path $fixture 'harness/prompt-map.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    $refs = @('composer.json','LICENSE','app/Model/TaskModel.php','app/Controller/DashboardController.php','tests/units.sqlite.xml')
    foreach ($f in $fl.features) { $refs += @($f.relatedFiles) }
    foreach ($m in $pm.changes) { $refs += @($m.codeZones) }
    foreach ($file in Get-ChildItem -LiteralPath (Join-Path $script:source 'harness') -Recurse -File -Filter '*.md') {
        $body = Get-Content -LiteralPath $file.FullName -Raw -Encoding UTF8
        foreach ($match in [regex]::Matches($body, '\]\(([^)]+)\)')) {
            $target = $match.Groups[1].Value.Trim('<','>')
            if ($target -match '^(https?://|mailto:|#)') { continue }
            $full = [IO.Path]::GetFullPath((Join-Path $file.DirectoryName (($target -split '#')[0])))
            if ($full.StartsWith($script:source + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
                $refs += $full.Substring($script:source.Length + 1)
            }
        }
    }
    foreach ($relative in ($refs | Sort-Object -Unique)) {
        $destination = [IO.Path]::GetFullPath((Join-Path $fixture $relative))
        if (-not $destination.StartsWith($fixture + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
            throw 'Fixture reference escaped its directory.'
        }
        if (Test-Path -LiteralPath $destination) { continue }
        $original = Join-Path $script:source $relative
        if (Test-Path -LiteralPath $original -PathType Container) {
            New-Item -ItemType Directory -Path $destination -Force | Out-Null
        } else {
            New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force | Out-Null
            [IO.File]::WriteAllText($destination, '# Synthetic path marker; not application code.', $script:utf8)
        }
    }
    return $fixture
}
function Run-Check([string]$Fixture) {
    $output = & powershell -NoProfile -File (Join-Path $script:source 'harness/scripts/Test-Harness.ps1') -RootPath $Fixture 2>&1
    return [pscustomobject]@{ Code = $LASTEXITCODE; Output = ($output -join "`n") }
}
function Test-Fixture([string]$Name, [scriptblock]$Mutate, [int]$Expected, [string]$Message) {
    $fixture = New-Fixture $Name
    & $Mutate $fixture
    $check = Run-Check $fixture
    $ok = $check.Code -eq $Expected -and $check.Output.Contains($Message)
    $script:results.Add([pscustomobject]@{ Name=$Name; Passed=$ok; Expected=$Expected; Actual=$check.Code })
    if (-not $ok) { Write-Output $check.Output }
}

try {
    New-Item -ItemType Directory -Path $scratch | Out-Null
    Test-Fixture 'zip-without-git' { param($f) } 0 'Git metadata absent'
    Test-Fixture 'repository-absent' { param($f)
        Remove-Item -LiteralPath (Join-Path $f 'app/Model/TaskModel.php')
    } 1 'Kanboard baseline missing path'
    Test-Fixture 'invalid-status' { param($f)
        $j=Read-Features $f; $j.features[0].status='complete'; Save-Features $f $j
    } 1 'invalid status'
    Test-Fixture 'duplicate-id' { param($f)
        $j=Read-Features $f; $j.features[1].id=$j.features[0].id; Save-Features $f $j
    } 1 'Duplicate ID'
    Test-Fixture 'two-active-features' { param($f)
        $j=Read-Features $f; $j.features[2].status='in_progress'; $j.features[3].status='in_progress'
        $j.activeFeatureId='F-003'; Save-Features $f $j
    } 1 'More than one active feature'
    Test-Fixture 'dependency-cycle' { param($f)
        $j=Read-Features $f; $j.features[0].dependsOn=@('F-002'); Save-Features $f $j
    } 1 'Dependency cycle'
    Test-Fixture 'missing-path' { param($f)
        $j=Read-Features $f; $j.features[0].relatedFiles=@('app/does-not-exist.php'); Save-Features $f $j
    } 1 'missing path'
    Test-Fixture 'validated-no-evidence' { param($f)
        $j=Read-Features $f; $j.features[0].subtasks[0].evidence=@(); Save-Features $f $j
    } 1 'needs evidence'
    Test-Fixture 'validated-null-evidence' { param($f)
        $j=Read-Features $f; $j.features[0].subtasks[0].evidence=$null; Save-Features $f $j
    } 1 'needs evidence'
    Test-Fixture 'validated-no-observed' { param($f)
        $p=Join-Path $f 'harness/test-cases/cases.json'
        $j=Get-Content -LiteralPath $p -Raw -Encoding UTF8 | ConvertFrom-Json
        $j.cases[0].observedResult=$null; Write-JsonFile $p $j
    } 1 'validated without observed result'
    Test-Fixture 'blocked-no-cause' { param($f)
        $j=Read-Features $f; $j.features[0].blockers=@(); Save-Features $f $j
    } 1 'blocked without cause'
    Test-Fixture 'unsatisfied-dependency' { param($f)
        $j=Read-Features $f; $j.features[1].status='in_progress'; $j.activeFeatureId='F-002'; Save-Features $f $j
    } 1 'unsatisfied dependency'
    Test-Fixture 'done-without-suspension' { param($f)
        $j=Read-Features $f; $j.features[2].status='done'; $j.features[3].status='in_progress'
        $j.activeFeatureId='F-004'; Save-Features $f $j
    } 1 'requires suspensionApproval'
    Test-Fixture 'malformed-json' { param($f)
        [IO.File]::WriteAllText((Join-Path $f 'harness/feature-list.json'), '{invalid', $script:utf8)
    } 1 'Invalid/unreadable JSON'
    Test-Fixture 'outside-reference' { param($f)
        $j=Read-Features $f; $j.features[0].relatedFiles=@('../../../outside.txt'); Save-Features $f $j
    } 1 'escapes repository'
    Test-Fixture 'parallel-state-file' { param($f)
        [IO.File]::WriteAllText((Join-Path $f 'harness/features-list.json'), '{}', $script:utf8)
    } 1 'Duplicate canonical state'
    Test-Fixture 'recorded-failure-stays-blocked' { param($f)
        $p=Join-Path $f 'harness/test-cases/cases.json'
        $j=Get-Content -LiteralPath $p -Raw -Encoding UTF8 | ConvertFrom-Json
        $j.cases[1].status='blocked'; $j.cases[1].observedResult='Synthetic failed check, not a real PHPUnit result.'
        Write-JsonFile $p $j
    } 0 'PASS:'

    # Real temporary Git repository with one synthetic tracked user change.
    $dirty = New-Fixture 'preserve-dirty-git'
    & git -C $dirty init --quiet
    if ($LASTEXITCODE -ne 0) { throw 'Cannot initialize isolated Git fixture.' }
    $userFile = Join-Path $dirty 'synthetic-user-work.txt'
    [IO.File]::WriteAllText($userFile, 'before', $utf8)
    & git -C $dirty add -- synthetic-user-work.txt
    if ($LASTEXITCODE -ne 0) { throw 'Cannot stage synthetic fixture.' }
    & git -C $dirty -c user.name=HarnessFixture -c user.email=fixture@example.invalid commit --quiet -m 'Synthetic fixture'
    if ($LASTEXITCODE -ne 0) { throw 'Cannot commit isolated fixture.' }
    [IO.File]::WriteAllText($userFile, 'user change to preserve', $utf8)
    $beforeHash = (Get-FileHash -LiteralPath $userFile -Algorithm SHA256).Hash
    $check = Run-Check $dirty
    $afterHash = (Get-FileHash -LiteralPath $userFile -Algorithm SHA256).Hash
    $dirtyStatus = & git -C $dirty status --porcelain=v1 -- synthetic-user-work.txt
    $ok = $check.Code -eq 0 -and $beforeHash -eq $afterHash -and ($dirtyStatus -join '') -match '^ M '
    $results.Add([pscustomobject]@{Name='preserve-dirty-git';Passed=$ok;Expected=0;Actual=$check.Code})
    foreach ($result in $results) {
        $label = if ($result.Passed) { 'PASS' } else { 'FAIL' }
        Write-Output ("{0}: {1} (expected {2}, observed {3})" -f $label,$result.Name,$result.Expected,$result.Actual)
    }
    $failed = @($results | Where-Object { -not $_.Passed }).Count
    Write-Output ("Fixtures: {0} checked, {1} failed. Static validator only." -f $results.Count,$failed)
} finally {
    # Resolve and constrain the exact owned directory before recursive deletion.
    if (Test-Path -LiteralPath $scratch) {
        $resolved = (Resolve-Path -LiteralPath $scratch).Path
        $parent = [IO.Path]::GetFullPath((Split-Path -Parent $resolved)).TrimEnd([char[]]'\/')
        if ($parent -ne $tempRoot -or (Split-Path -Leaf $resolved) -notmatch '^kanboard-harness-[a-f0-9]{32}$') {
            throw 'Refusing cleanup outside the owned fixture directory.'
        }
        Remove-Item -LiteralPath $resolved -Recurse -Force
    }
}
if ($failed -gt 0) { exit 1 }
exit 0
