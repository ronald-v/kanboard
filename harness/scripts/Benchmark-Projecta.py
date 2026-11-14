"""Ten independent PHP processes, same loopback fixture. No real database access."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
def worker(number):
    command = ['powershell','-NoProfile','-File','harness/scripts/ProjectaEnvironment.ps1','-Script','harness/scripts/Projecta-Benchmark.php','-ScriptArgs',str(number)]
    process = subprocess.run(command,cwd=ROOT,capture_output=True,text=True,timeout=180)
    if process.returncode:
        (ROOT/'.runtime/projecta'/('benchmark-error-'+str(number)+'.txt')).write_text(process.stdout+process.stderr,encoding='utf-8')
        raise RuntimeError('Synthetic benchmark failed: '+(process.stdout+process.stderr)[:950])
    return json.loads(process.stdout.strip().splitlines()[-1])
with ThreadPoolExecutor(max_workers=10) as executor:
    samples=list(executor.map(worker,range(10)))
summary={}
for operation in samples[0]:
    values=sorted(s['ms'] for worker_samples in samples for s in worker_samples[operation])
    summary[operation]={'n':len(values),'median_ms':round(values[len(values)//2],2),'p95_ms':round(values[int(len(values)*.95)-1],2),'pico_queries_median':sorted(s['pico_queries'] for w in samples for s in w[operation])[len(values)//2]}
output=ROOT/'.runtime/projecta'/('benchmark-'+(sys.argv[1] if len(sys.argv)>1 else 'current')+'.json')
output.write_text(json.dumps(summary,indent=2)+'\n',encoding='utf-8')
print(json.dumps(summary,indent=2))
