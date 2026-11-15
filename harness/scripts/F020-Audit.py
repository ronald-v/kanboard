"""Audit both production lockfiles and bind results to their exact hashes."""
from pathlib import Path
import hashlib
import json
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f020'

for directory, name in ((ROOT, 'root'), (ROOT/'mcp', 'mcp')):
    lock = directory/'composer.lock'
    digest = hashlib.sha256(lock.read_bytes()).hexdigest()
    result = subprocess.run([shutil.which('composer'), '--no-plugins', '--no-scripts', 'audit', '--locked',
                             '--no-dev', '--format=json'], cwd=directory, capture_output=True, text=True, timeout=90)
    if result.returncode: raise RuntimeError('Composer production audit failed: '+name)
    data = json.loads(result.stdout)
    if data.get('advisories') or data.get('abandoned'): raise RuntimeError('Review dependency findings: '+name)
    if hashlib.sha256(lock.read_bytes()).hexdigest() != digest: raise RuntimeError('Lockfile changed during audit')
    (WORK/('audit-'+name+'.json')).write_text(json.dumps({'lockSha256': digest, 'result': data}, indent=2)+'\n')
    print('PASS production dependency audit: '+name)
