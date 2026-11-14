"""HTTP benchmark: 10 independent server processes, 200 samples per operation."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import base64
import json
import os
import secrets
import re
from http.cookiejar import CookieJar
import socket
import subprocess
import time
import urllib.request
import urllib.error
import urllib.parse

ROOT=Path(__file__).resolve().parents[2]
fixture=json.loads((ROOT/'.runtime/projecta/http-benchmark-fixture.json').read_text())
def worker(index):
    user=fixture[index]
    port=18101+index
    log=(ROOT/'.runtime/projecta'/f'http-benchmark-{index}.log').open('wb')
    env=os.environ.copy()
    env.pop('DATABASE_URL',None)
    env.update(DB_DRIVER='mysql',DB_HOSTNAME='127.0.0.1',DB_PORT='13318',DB_NAME='kanboard_compat_http',DB_USERNAME='compat_synthetic',DB_PASSWORD='synthetic-local-only',
        DATA_DIR=str(ROOT/'.runtime/projecta/data'),PLUGINS_DIR=str(ROOT/'.runtime/projecta/app/plugins'),PROJECTA_KEY='c3ludGhldGljLW9ubHktMzItYnl0ZS1rZXktMDAwMDA=',DEBUG='false',LOG_DRIVER='stderr')
    php=ROOT/'.runtime/compatibility/php85/php.exe'
    command=[str(php),'-n','-d','extension_dir='+str(php.parent/'ext'),'-d','auto_prepend_file='+str(ROOT/'config/projecta.php')]
    for extension in ['gd','mbstring','openssl','pdo_mysql','pdo_sqlite','zip','sodium']: command+=['-d','extension='+extension]
    command+=['-S',f'127.0.0.1:{port}','-t',str(ROOT/'.runtime/projecta/app')]
    process=subprocess.Popen(command,cwd=ROOT,stdout=log,stderr=log,env=env)
    def call(method,params=None,admin=False):
        actor=user['admin_username'] if admin else user['username']
        token=user['admin_token'] if admin else user['token']
        auth=base64.b64encode((actor+':'+token).encode()).decode()
        body=json.dumps({'jsonrpc':'2.0','id':1,'method':method,'params':params or {}}).encode()
        request=urllib.request.Request(f'http://127.0.0.1:{port}/jsonrpc.php',body,{'Authorization':'Basic '+auth,'Content-Type':'application/json'})
        try: response=urllib.request.urlopen(request,timeout=30)
        except urllib.error.HTTPError as error: response=error
        result=json.load(response)
        assert 'error' not in result,(method,result.get('error'))
        return result['result']
    browser=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))
    base=f'http://127.0.0.1:{port}/'
    def page(path,fields=None):
        response=browser.open(base+path,urllib.parse.urlencode(fields).encode() if fields is not None else None,timeout=30)
        body=response.read().decode()
        assert response.status==200 and 'Fatal error' not in body
        return body
    def csrf(body): return re.search(r'name="csrf_token"[^>]*value="([^"]+)"',body)[1]
    values={name:[] for name in ['read_navigation','read_projects','read_tasks','create_project','create_task','create_user','create_group']}
    try:
        for attempt in range(100):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.1): break
            except OSError: time.sleep(.1)
        else: raise RuntimeError('Synthetic PHP server did not start')
        login=page('?controller=AuthController&action=login')
        challenge=page('?controller=AuthController&action=check',{'csrf_token':csrf(login),'username':user['username'],'password':'synthetic-password'})
        home=page('?controller=RvAccessController&action=verify&plugin=RvSoftware',{'csrf_token':csrf(challenge),'code':user['recovery']})
        assert 'rv-sidebar' in home
        for iteration in range(22):
            for name in values:
                nonce=secrets.token_hex(6)
                start=time.perf_counter()
                if name=='read_navigation': result=page('?controller=RvHomeController&action=show&plugin=RvSoftware')
                elif name=='read_projects': result=call('projectaListProjects',{'limit':50})
                elif name=='read_tasks': result=call('projectaListTasks',{'project_id':user['project_id'],'limit':50})
                elif name=='create_project': result=call('createProject',{'name':'HTTP benchmark '+nonce})
                elif name=='create_task': result=call('createTask',{'title':'HTTP benchmark '+nonce,'project_id':user['project_id']})
                elif name=='create_user': result=call('createUser',{'username':'http-perf-'+nonce,'password':'synthetic-password'},True)
                else: result=call('createGroup',{'name':'HTTP benchmark '+nonce},True)
                elapsed=(time.perf_counter()-start)*1000
                assert result
                if iteration>=2: values[name].append(elapsed)
                if name.startswith('create_'):
                    method,parameter={'create_project':('removeProject','project_id'),'create_task':('removeTask','task_id'),'create_user':('removeUser','user_id'),'create_group':('removeGroup','group_id')}[name]
                    assert call(method,{parameter:result},name in ('create_user','create_group'))
        return values
    finally:
        # Stop this dedicated child tree only. Never enumerate or stop other PHP servers.
        process.terminate()
        process.wait(timeout=10)
        log.close()

with ThreadPoolExecutor(max_workers=10) as executor:
    workers=list(executor.map(worker,range(10)))
summary={}
for name in workers[0]:
    samples=sorted(sample for worker_values in workers for sample in worker_values[name])
    p95=samples[int(len(samples)*.95)-1]
    budget=800 if name.startswith('read') else 1500
    summary[name]={'samples':len(samples),'median_ms':round(samples[len(samples)//2],2),'p95_ms':round(p95,2),'budget_ms':budget,'pass':p95<=budget}
(ROOT/'.runtime/projecta/http-benchmark.json').write_text(json.dumps(summary,indent=2),encoding='utf-8')
print(json.dumps(summary,indent=2))
assert all(item['pass'] for item in summary.values()), 'Latency budget exceeded'
