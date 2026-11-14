"""Separate DEBUG/opcode-cache experiment; fixed synthetic database, read-only flows."""
from pathlib import Path
import importlib.util
import json
import math
import os
import socket
import statistics
import subprocess
import time
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f019'


def module(name, file):
    spec = importlib.util.spec_from_file_location(name, ROOT/'harness/scripts'/file)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


e = module('f019_environment','F019-Environment.py')
b = module('f019_benchmark','F019-Benchmark.py')
results = {}
probe = e.sandbox('after')/'assets/f019-opcache-probe.php'
if probe.exists():
    raise SystemExit('Probe already exists; inspect it before rerunning this experiment.')
probe.write_text("<?php echo json_encode(['enabled'=>function_exists('opcache_get_status') && opcache_get_status(false)!==false, 'version'=>1]);")
os.utime(probe,(time.time()-10,time.time()-10))
for mode in ('base','debug','opcache'):
    e.database_snapshot(restore=True)
    env = e.environment('after',debug=mode=='debug')
    command = e.php_command()+['-d','opcache.enable='+str(int(mode=='opcache')),'-d','opcache.enable_cli='+str(int(mode=='opcache')),
                              '-d','opcache.validate_timestamps=1','-d','opcache.revalidate_freq=0','-d','opcache.file_cache=']
    command += ['-S','127.0.0.1:18110','-t',str(e.sandbox('after')),str(ROOT/'harness/scripts/F019-Router.php')]
    with (WORK/('options-'+mode+'.log')).open('wb') as log:
        process = subprocess.Popen(command,env=env,stdout=log,stderr=log)
        try:
            for attempt in range(50):
                try:
                    with socket.create_connection(('127.0.0.1',18110),timeout=.1): break
                except OSError:
                    time.sleep(.1)
            else: raise RuntimeError('Dedicated options server did not start')
            info = json.load(urllib.request.urlopen(b.BASE+'assets/f019-opcache-probe.php'))
            assert info['enabled'] == (mode=='opcache')
            client = b.Client(json.loads((WORK/'fixture.json').read_text())[0])
            values = {}
            for name in ('home','task','reports','users'):
                samples = []
                for iteration in range(102):
                    client.samples = []
                    client.get(b.flows(client.fixture)[name])
                    if iteration >= 2: samples.append(client.samples[-1]['ms'])
                values[name] = {'samples':len(samples),'median_ms':round(statistics.median(samples),2),'p95_ms':round(sorted(samples)[math.ceil(len(samples)*.95)-1],2)}
            results[mode] = {'opcache_enabled':info['enabled'],'flows':values}
            if mode == 'opcache':
                probe.write_text("<?php echo json_encode(['enabled'=>true,'version'=>2]);")
                assert json.load(urllib.request.urlopen(b.BASE+'assets/f019-opcache-probe.php'))['version']==2, 'Updated source was not revalidated'
            print('PASS options experiment '+mode,flush=True)
        finally:
            process.terminate()
            process.wait(timeout=10)
probe.unlink()
(WORK/'options.json').write_text(json.dumps(results,indent=2))
print(json.dumps(results,indent=2),flush=True)
