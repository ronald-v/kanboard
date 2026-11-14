from concurrent.futures import ThreadPoolExecutor
import json
import subprocess
from projecta_http import ROOT
def run(arg):
    process=subprocess.run(['powershell','-NoProfile','-File','harness/scripts/ProjectaEnvironment.ps1','-Script','harness/scripts/Projecta-BridgeRace.php','-ScriptArgs',arg],cwd=ROOT,capture_output=True,text=True,timeout=45)
    assert process.returncode==0,(process.stdout+process.stderr)[:500]
    return process.stdout
run('seed')
with ThreadPoolExecutor(max_workers=10) as executor:
    results=[json.loads(output) for output in executor.map(run,[str(i) for i in range(10)])]
assert json.loads(run('check'))['count']==1
ids={row['result'] for row in results if 'result' in row}
assert len(ids)==1
assert all('result' in row or row['error']['code']=='OPERATION_PENDING' for row in results),results
print('PASS MCP bridge concurrency: 10 independent processes and one operation key create exactly one project; pending requests cannot execute twice.')
