"""Copy working sources into a disposable root without reading private config/data."""
from pathlib import Path
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[2]
DEST = ROOT / '.runtime/projecta/app'
DEST.mkdir(parents=True, exist_ok=True)
paths = subprocess.check_output(['git','ls-files','-z'],cwd=ROOT).decode().split('\0')
paths += [str(p.relative_to(ROOT)).replace('\\','/') for base in ['plugins/RvSoftware','app','tests','config','mcp'] if (ROOT/base).exists() for p in (ROOT/base).rglob('*') if p.is_file() and 'vendor' not in p.parts]
for name in dict.fromkeys(paths):
    if not name or name in ('config.php','data/config.php') or name.startswith(('data/','harness/','.git','.runtime/')):
        continue
    source = ROOT/name
    if source.is_file():
        target = DEST/name
        target.parent.mkdir(parents=True,exist_ok=True)
        if not target.exists() or target.stat().st_size!=source.stat().st_size or target.stat().st_mtime_ns!=source.stat().st_mtime_ns:
            shutil.copy2(source,target)
if (ROOT/'mcp/vendor/autoload.php').exists():
    shutil.copytree(ROOT/'mcp/vendor',DEST/'mcp/vendor',dirs_exist_ok=True,copy_function=shutil.copy2)
print('Synthetic app copy ready; private configuration/data excluded.')
