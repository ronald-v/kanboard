"""Run the security-related native and plugin regression in isolated SQLite."""
from pathlib import Path
import json
import hashlib
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f020'
command = [sys.executable, str(ROOT/'harness/scripts/F019-Environment.py'), 'test', '--variant', 'after', '--sqlite',
           '--filter', 'RvSoftware|RequestTest|FileViewerControllerTest|FileStorageTest|SessionManagerTest|AuthorizationTest|AuthenticationManagerTest|ProjectAuthorizationMiddlewareTest|ApplicationAuthorizationMiddlewareTest|TokenTest|SchemaHandlerTest']
app = ROOT/'.runtime/f019/after/app'
tested = {path.relative_to(app).as_posix(): hashlib.sha256(path.read_bytes()).hexdigest()
          for base in ('app', 'plugins/RvSoftware', 'tests') for path in (app/base).rglob('*.php')}
with (WORK/'sqlite-regression.log').open('w', encoding='utf-8') as log:
    result = subprocess.run(command, cwd=ROOT, stdout=log, stderr=subprocess.STDOUT, timeout=600)
if result.returncode: raise RuntimeError('SQLite regression failed; inspect private synthetic log')
output = (WORK/'sqlite-regression.log').read_text(encoding='utf-8')
summary = re.findall(r'(?:OK \([^\n]+|Tests: [^\n]+)', output)
if not summary: raise RuntimeError('No PHPUnit completion summary')
(WORK/'sqlite-result.json').write_text(json.dumps({'passed': True, 'summary': summary[-1], 'testedFiles': tested})+'\n')
print('PASS isolated SQLite regression:', summary[-1])
