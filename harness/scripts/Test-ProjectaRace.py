from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import subprocess
import json
ROOT=Path(__file__).resolve().parents[2]
def run(arg):
    command=['powershell','-NoProfile','-File','harness/scripts/ProjectaEnvironment.ps1','-Script','harness/scripts/Projecta-SecurityRace.php','-ScriptArgs',arg]
    result=subprocess.run(command,cwd=ROOT,capture_output=True,text=True,timeout=45)
    assert result.returncode==0, (result.stdout+result.stderr)[:800]
    return result.stdout
run('seed')
with ThreadPoolExecutor(max_workers=10) as executor:
    results=[json.loads(output) for output in executor.map(run,[str(i) for i in range(10)])]
assert sum(r['recovery_used'] for r in results)==1
assert sum(not r['limited'] for r in results)<=5
assert sum(r['limited'] for r in results)>=5
print('PASS 10 independent processes: exactly one recovery use; limiter admits at most 5 attempts.')
