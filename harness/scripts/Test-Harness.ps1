param([string]$RootPath = (Join-Path $PSScriptRoot '../..'))

# Read-only static verification. Compatible with Windows PowerShell 5.1.
$ErrorActionPreference = 'Stop'
$script:issues = New-Object 'System.Collections.Generic.List[string]'
$script:root = [IO.Path]::GetFullPath($RootPath).TrimEnd([char[]]'\/')
$script:prefix = $script:root + [IO.Path]::DirectorySeparatorChar
$script:states = @('planned', 'in_progress', 'blocked', 'done', 'validated')

function Add-Issue([string]$Message) { $script:issues.Add($Message) }
function Has-Text($Value) { return -not [string]::IsNullOrWhiteSpace([string]$Value) }
function Check-Array($Value, [string]$Label, [bool]$Required = $false) {
    if ($Value -isnot [array]) { Add-Issue "$Label must be an array"; return }
    if ($Required -and $Value.Count -eq 0) { Add-Issue "$Label must not be empty" }
}
function Check-Fields($Object, [string[]]$Fields, [string]$Label) {
    foreach ($field in $Fields) {
        if ($null -eq $Object -or $Object.PSObject.Properties.Name -notcontains $field) {
            Add-Issue "$Label missing field: $field"
        }
    }
}
function Check-Path($Relative, [string]$Label) {
    if (-not (Has-Text $Relative)) { Add-Issue "$Label empty path"; return }
    if ([IO.Path]::IsPathRooted([string]$Relative)) { Add-Issue "$Label must be repository-relative: $Relative"; return }
    try { $full = [IO.Path]::GetFullPath((Join-Path $script:root $Relative)) }
    catch { Add-Issue "$Label invalid path: $Relative"; return }
    if (-not $full.StartsWith($script:prefix, [StringComparison]::OrdinalIgnoreCase)) {
        Add-Issue "$Label escapes repository: $Relative"; return
    }
    if (-not (Test-Path -LiteralPath $full)) { Add-Issue "$Label missing path: $Relative" }
}
function Read-Json([string]$Relative) {
    try { return (Get-Content -LiteralPath (Join-Path $script:root $Relative) -Raw -Encoding UTF8 | ConvertFrom-Json) }
    catch { Add-Issue "Invalid/unreadable JSON: $Relative"; return $null }
}
function Check-Evidence($Items, [string]$Label, [bool]$Required = $false) {
    Check-Array $Items "$Label evidence"
    if ($Required -and ($null -eq $Items -or @($Items).Count -eq 0)) { Add-Issue "$Label needs evidence" }
    foreach ($item in $Items) {
        Check-Path $item $Label
        if ([string]$item -notlike 'harness/evidence/*') { Add-Issue "$Label evidence must reference harness/evidence: $item" }
        $p = Join-Path $script:root $item
        if ((Test-Path -LiteralPath $p -PathType Leaf) -and (Get-Item -LiteralPath $p).Length -eq 0) {
            Add-Issue "$Label has empty evidence: $item"
        }
    }
}
function Check-State($Item, [string]$Label) {
    if ($script:states -notcontains $Item.status) { Add-Issue "$Label invalid status: $($Item.status)" }
    if (-not (Has-Text $Item.nextAction)) { Add-Issue "$Label needs nextAction" }
    Check-Array $Item.blockers "$Label blockers"
    if ($Item.status -eq 'blocked' -and ($null -eq $Item.blockers -or @($Item.blockers).Count -eq 0)) { Add-Issue "$Label blocked without cause" }
    foreach ($cause in $Item.blockers) { if (-not (Has-Text $cause)) { Add-Issue "$Label empty blocker" } }
    Check-Evidence $Item.evidence $Label ($Item.status -eq 'validated')
}

foreach ($path in @('composer.json','LICENSE','app/Model/TaskModel.php','app/Controller/DashboardController.php','tests/units.sqlite.xml')) {
    Check-Path $path 'Kanboard baseline'
}
$required = @('AGENTS.md','harness/README.md','harness/current-task.md','harness/feature-list.json',
    'harness/prompt-map.json','harness/test-cases/cases.json','harness/test-cases/harness-scenarios.json')
foreach ($path in $required) { Check-Path $path 'Required' }
foreach ($dir in @('prompts','context','feedback','test-cases','evidence','scripts')) { Check-Path "harness/$dir" 'Directory' }
foreach ($ctx in @('project','architecture','upstream','database','time-billing','ui-brand','security','testing','deployment')) {
    Check-Path "harness/context/$ctx.md" 'Context'
}
if (Test-Path -LiteralPath (Join-Path $script:root 'harness/features-list.json')) { Add-Issue 'Duplicate canonical state: features-list.json' }
$agentPath = Join-Path $script:root 'AGENTS.md'
if (Test-Path -LiteralPath $agentPath) {
    $agents = Get-Content -LiteralPath $agentPath -Raw -Encoding UTF8
    if ($agents.Length -gt 7600) { Add-Issue 'AGENTS.md exceeds 7600 characters' }
}
$roles = @('leader','architect','dev','db','ui-ux','security','testing','validator','release')
foreach ($role in $roles) {
    $relative = "harness/prompts/$role.md"
    Check-Path $relative 'Prompt'
    $p = Join-Path $script:root $relative
    if (Test-Path -LiteralPath $p) {
        $body = Get-Content -LiteralPath $p -Raw -Encoding UTF8
        foreach ($heading in @('Rol','Contexto m','Objetivos y responsabilidades','L','Entradas','Salidas','Checklist','Escalamiento','Una feature')) {
            if ($body -notmatch ('(?m)^## ' + [regex]::Escape($heading))) { Add-Issue "$relative missing section: $heading" }
        }
        if ($body -notmatch '\- \[ \]') { Add-Issue "$relative missing checklist items" }
    }
}

$fl = Read-Json 'harness/feature-list.json'
$pm = Read-Json 'harness/prompt-map.json'
$tc = Read-Json 'harness/test-cases/cases.json'
$hs = Read-Json 'harness/test-cases/harness-scenarios.json'
if ($null -ne $fl -and $null -ne $pm -and $null -ne $tc -and $null -ne $hs) {
    Check-Fields $fl @('schemaVersion','project','statusValues','updatedAt','activeFeatureId','features') 'Feature list'
    Check-Array $fl.features 'features' $true
    Check-Array $fl.statusValues 'statusValues' $true
    foreach ($doc in @($fl,$pm,$tc,$hs)) { if ($doc.schemaVersion -ne '1.0') { Add-Issue 'Unsupported schemaVersion' } }
    if ((@($fl.statusValues) -join ',') -cne ($script:states -join ',')) { Add-Issue 'statusValues must be the five canonical states in order' }
    $parsedDate = [DateTimeOffset]::MinValue
    if ($fl.updatedAt -notmatch '^\d{4}-\d{2}-\d{2}T.+(Z|[+-]\d{2}:\d{2})$' -or
        -not [DateTimeOffset]::TryParse([string]$fl.updatedAt, [ref]$parsedDate)) { Add-Issue 'updatedAt needs ISO 8601 with timezone' }
    if (@($fl.features).Count -eq 0) { Add-Issue 'Feature list is empty' }
    $script:features = @{}
    $allIds = @{}
    foreach ($f in $fl.features) {
        Check-Fields $f @('id','name','description','status','targetVersion','dependsOn','acceptanceCriteria','subtasks','relatedPrompts','relatedContext','relatedFiles','testCases','evidence','blockers','nextAction','notes','suspensionApproval') 'Feature'
        if ($f.id -notmatch '^F-\d{3}$') { Add-Issue "Invalid feature ID: $($f.id)"; continue }
        if ($allIds.ContainsKey($f.id)) { Add-Issue "Duplicate ID: $($f.id)" }
        $allIds[$f.id] = $true
        $script:features[$f.id] = $f
        Check-State $f $f.id
        foreach ($key in @('dependsOn','acceptanceCriteria','subtasks','relatedPrompts','relatedContext','relatedFiles','testCases')) {
            Check-Array $f.$key "$($f.id) $key"
        }
        if (-not (Has-Text $f.name) -or -not (Has-Text $f.description)) { Add-Issue "$($f.id) missing name/description" }
        if (@($f.acceptanceCriteria).Count -eq 0) { Add-Issue "$($f.id) needs acceptance criteria" }
        foreach ($criterion in $f.acceptanceCriteria) { if (-not (Has-Text $criterion)) { Add-Issue "$($f.id) empty criterion" } }
        foreach ($key in @('relatedPrompts','relatedContext','testCases','subtasks')) {
            if (@($f.$key).Count -eq 0) { Add-Issue "$($f.id) needs $key" }
        }
        foreach ($key in @('relatedPrompts','relatedContext','relatedFiles')) { foreach ($p in $f.$key) { Check-Path $p $f.id } }
        if ($null -ne $f.suspensionApproval) {
            Check-Path $f.suspensionApproval "$($f.id) suspensionApproval"
            if ($f.suspensionApproval -notlike 'harness/feedback/*') { Add-Issue "$($f.id) suspensionApproval must reference feedback" }
        }
        foreach ($s in $f.subtasks) {
            Check-Fields $s @('id','name','status','assignedAgent','agentNotes','evidence','blockers','nextAction') "$($f.id) subtask"
            if ($s.id -notmatch ('^' + $f.id + '-S\d+$')) { Add-Issue "Invalid subtask ID: $($s.id)" }
            if ($allIds.ContainsKey([string]$s.id)) { Add-Issue "Duplicate ID: $($s.id)" }
            $allIds[[string]$s.id] = $true
            if ($roles -notcontains $s.assignedAgent) { Add-Issue "$($s.id) unknown role" }
            Check-State $s $s.id
            if ($s.status -eq 'in_progress' -and $f.status -ne 'in_progress') { Add-Issue "$($s.id) active under non-active feature" }
            if ($f.status -eq 'validated' -and $s.status -ne 'validated') { Add-Issue "$($f.id) validated with unfinished subtask" }
        }
    }
    $active = @($fl.features | Where-Object { $_.status -eq 'in_progress' })
    if ($active.Count -gt 1) { Add-Issue 'More than one active feature' }
    if ($active.Count -eq 0 -and $null -ne $fl.activeFeatureId) { Add-Issue 'activeFeatureId must be null when none active' }
    if ($active.Count -eq 1 -and $fl.activeFeatureId -ne $active[0].id) { Add-Issue 'activeFeatureId mismatch' }
    foreach ($f in $fl.features) {
        foreach ($dep in $f.dependsOn) {
            if (-not $script:features.ContainsKey([string]$dep)) { Add-Issue "$($f.id) missing dependency: $dep" }
            elseif ($f.status -in @('in_progress','done','validated') -and $script:features[$dep].status -ne 'validated') { Add-Issue "$($f.id) unsatisfied dependency: $dep" }
        }
        if ($active.Count -gt 0 -and $f.status -eq 'done' -and -not (Has-Text $f.suspensionApproval)) { Add-Issue "$($f.id) done requires suspensionApproval before another feature" }
    }
    $script:visiting = @{}
    $script:visited = @{}
    function Visit-Feature([string]$Id) {
        if ($script:visiting.ContainsKey($Id)) { Add-Issue "Dependency cycle at $Id"; return }
        if ($script:visited.ContainsKey($Id) -or -not $script:features.ContainsKey($Id)) { return }
        $script:visiting[$Id] = $true
        foreach ($d in $script:features[$Id].dependsOn) { Visit-Feature $d }
        $script:visiting.Remove($Id)
        $script:visited[$Id] = $true
    }
    foreach ($id in @($script:features.Keys)) { Visit-Feature $id }

    Check-Fields $tc @('cases') 'Test cases'
    Check-Array $tc.cases 'cases' $true
    $caseIndex = @{}
    foreach ($c in $tc.cases) {
        Check-Fields $c @('id','featureId','objective','preconditions','syntheticData','steps','command','expectedResult','verificationType','status','observedResult','evidence','limitations') 'Case'
        if ($caseIndex.ContainsKey([string]$c.id)) { Add-Issue "Duplicate case: $($c.id)" }
        $caseIndex[[string]$c.id] = $c
        if (-not $script:features.ContainsKey([string]$c.featureId)) { Add-Issue "$($c.id) unknown feature" }
        elseif ($script:features[$c.featureId].testCases -notcontains $c.id) { Add-Issue "$($c.id) not linked from its feature" }
        if ($c.id -notmatch ('^TC-' + $c.featureId + '-\d{2}$')) { Add-Issue "Invalid case ID: $($c.id)" }
        if ($script:states -notcontains $c.status) { Add-Issue "$($c.id) invalid status" }
        foreach ($key in @('objective','syntheticData','expectedResult','verificationType','limitations')) {
            if (-not (Has-Text $c.$key)) { Add-Issue "$($c.id) empty $key" }
        }
        if (@($c.steps).Count -eq 0 -or @($c.preconditions).Count -eq 0) { Add-Issue "$($c.id) needs steps/preconditions" }
        Check-Array $c.steps "$($c.id) steps" $true
        Check-Array $c.preconditions "$($c.id) preconditions" $true
        Check-Evidence $c.evidence $c.id ($c.status -eq 'validated')
        if ($c.status -eq 'validated' -and -not (Has-Text $c.observedResult)) { Add-Issue "$($c.id) validated without observed result" }
    }
    foreach ($f in $fl.features) {
        foreach ($id in $f.testCases) {
            if (-not $caseIndex.ContainsKey([string]$id)) { Add-Issue "$($f.id) unknown case: $id" }
            elseif ($caseIndex[$id].featureId -ne $f.id) { Add-Issue "$($f.id) case belongs elsewhere: $id" }
            elseif ($f.status -eq 'validated' -and $caseIndex[$id].status -ne 'validated') { Add-Issue "$($f.id) has unverified case: $id" }
        }
    }
    Check-Fields $pm @('changes') 'Prompt map'
    Check-Array $pm.changes 'changes' $true
    $mapIds = @{}
    $mapped = @{}
    foreach ($m in $pm.changes) {
        Check-Fields $m @('id','changeType','responsible','prompts','context','features','codeZones','testCases') 'Map entry'
        foreach ($key in @('prompts','context','features','codeZones','testCases')) { Check-Array $m.$key "$($m.id) $key" $true }
        if ($mapIds.ContainsKey([string]$m.id)) { Add-Issue "Duplicate map ID: $($m.id)" }
        $mapIds[[string]$m.id] = $true
        if ($roles -notcontains $m.responsible) { Add-Issue "$($m.id) unknown responsible role" }
        if ($m.prompts -notcontains "harness/prompts/$($m.responsible).md") { Add-Issue "$($m.id) missing responsible prompt" }
        if ($m.PSObject.Properties.Name -contains 'status') { Add-Issue "$($m.id) map must not duplicate execution status" }
        foreach ($key in @('prompts','context','codeZones')) {
            if (@($m.$key).Count -eq 0) { Add-Issue "$($m.id) empty $key" }
            foreach ($p in $m.$key) { Check-Path $p $m.id }
        }
        foreach ($id in $m.features) {
            if (-not $script:features.ContainsKey([string]$id)) { Add-Issue "$($m.id) unknown feature: $id" }
            $mapped[[string]$id] = $true
        }
        foreach ($id in $m.testCases) {
            if (-not $caseIndex.ContainsKey([string]$id)) { Add-Issue "$($m.id) unknown case: $id" }
            elseif ($m.features -notcontains $caseIndex[$id].featureId) { Add-Issue "$($m.id) unrelated case: $id" }
        }
    }
    foreach ($id in $script:features.Keys) { if (-not $mapped.ContainsKey($id)) { Add-Issue "$id missing prompt map" } }
    Check-Fields $hs @('scenarios') 'Harness scenarios'
    Check-Array $hs.scenarios 'scenarios' $true
    $scenarioIds = @{}
    foreach ($s in $hs.scenarios) {
        Check-Fields $s @('id','scenario','preconditions','steps','expectedResult','observedResult','status','verificationType','evidence','limitations') 'Harness scenario'
        if ($scenarioIds.ContainsKey([string]$s.id)) { Add-Issue "Duplicate scenario: $($s.id)" }
        $scenarioIds[[string]$s.id] = $true
        if ($script:states -notcontains $s.status) { Add-Issue "$($s.id) invalid status" }
        Check-Evidence $s.evidence $s.id ($s.status -eq 'validated')
        if ($s.status -eq 'validated' -and -not (Has-Text $s.observedResult)) { Add-Issue "$($s.id) validated without observed result" }
    }
    foreach ($n in 1..10) { $id = 'HS-{0:D2}' -f $n; if (-not $scenarioIds.ContainsKey($id)) { Add-Issue "Missing scenario: $id" } }
}

# Validate relative Markdown links. Proposed paths in code spans are not treated as links.
$markdown = @()
if (Test-Path -LiteralPath $agentPath) { $markdown += Get-Item -LiteralPath $agentPath }
$harnessDir = Join-Path $script:root 'harness'
if (Test-Path -LiteralPath $harnessDir) { $markdown += Get-ChildItem -LiteralPath $harnessDir -Recurse -File -Filter '*.md' }
foreach ($file in $markdown) {
    $body = Get-Content -LiteralPath $file.FullName -Raw -Encoding UTF8
    foreach ($match in [regex]::Matches($body, '\]\(([^)]+)\)')) {
        $target = $match.Groups[1].Value.Trim('<','>')
        if ($target -match '^(https?://|mailto:|#)') { continue }
        $target = ($target -split '#')[0]
        $linkPath = [IO.Path]::GetFullPath((Join-Path $file.DirectoryName $target))
        if (-not $linkPath.StartsWith($script:prefix, [StringComparison]::OrdinalIgnoreCase) -or -not (Test-Path -LiteralPath $linkPath)) {
            Add-Issue "Broken/outside Markdown link in $($file.Name): $target"
        }
    }
}
if (-not (Test-Path -LiteralPath (Join-Path $script:root '.git'))) {
    Write-Output 'NOTE: Git metadata absent; provenance needs separate evidence. Static structure only.'
}
if ($script:issues.Count -gt 0) {
    foreach ($issue in $script:issues) { Write-Output "FAIL: $issue" }
    Write-Output ("Harness validation failed: {0} issue(s)." -f $script:issues.Count)
    exit 1
}
Write-Output ("PASS: {0} features, {1} cases, {2} prompts, 10 scenarios; static consistency only." -f @($fl.features).Count, @($tc.cases).Count, $roles.Count)
exit 0
