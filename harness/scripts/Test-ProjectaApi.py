import base64
import json
import urllib.request
import urllib.error
from projecta_http import ROOT,BASE

fixture=json.loads((ROOT/'.runtime/projecta/api-fixture.json').read_text())
def call(role,method,params=None,token=None,error=False):
    user=fixture['users'][role]
    authorization=base64.b64encode((user['username']+':'+(token or user['token'])).encode()).decode()
    body=json.dumps({'jsonrpc':'2.0','id':1,'method':method,'params':params or {}}).encode()
    request=urllib.request.Request(BASE+'jsonrpc.php',body,{'Content-Type':'application/json','Authorization':'Basic '+authorization})
    try: response=urllib.request.urlopen(request,timeout=30)
    except urllib.error.HTTPError as exc: response=exc
    result=json.load(response)
    if error:
        assert 'error' in result,(method,'Expected rejection')
        return result['error']
    assert 'error' not in result,(method,result.get('error'))
    return result['result']

assert 'credentials_fingerprint' not in call('manager','getMe')
for role in ['manager','member','viewer']:
    assert call(role,'getProjectById',{'project_id':fixture['project_id']})['id']==str(fixture['project_id']) or int(call(role,'getProjectById',{'project_id':fixture['project_id']})['id'])==fixture['project_id']
    assert call(role,'getTask',{'task_id':fixture['foreign_task_id']},error=True)['code']==403
assert call('viewer','createTask',{'title':'Denied','project_id':fixture['project_id']},error=True)['code']==403
assert call('manager','createProject',{'name':'Denied by scope'},token=fixture['read_token'],error=True)['code']==403
assert call('manager','getMe',token='synthetic-api-password',error=True)['code']==401
project=call('manager','createProject',{'name':'CRUD complete project'})
assert project
assert call('manager','updateProject',{'project_id':project,'name':'Updated CRUD project'})
task=call('manager','createTask',{'title':'CRUD task','project_id':project})
assert task
assert call('manager','updateTask',{'id':task,'title':'Updated CRUD task'})
subtask=call('manager','createSubtask',{'task_id':task,'title':'CRUD subtask'})
assert subtask
assert call('manager','updateSubtask',{'id':subtask,'task_id':task,'title':'Updated CRUD subtask'})
assert call('manager','getSubtask',{'subtask_id':subtask})['title']=='Updated CRUD subtask'
assert call('manager','updateSubtask',{'id':fixture['subtask_id'],'task_id':fixture['foreign_task_id'],'title':'Spoof'},error=True)['code']==403
assert call('member','createSubtask',{'task_id':fixture['task_id'],'title':'Foreign assignment','user_id':fixture['users']['foreign']['id']}) is False
assert call('manager','projectaListTasks',{'project_id':project,'offset':0,'limit':1})['limit']==1
assert call('manager','projectaListTasks',{'project_id':project,'offset':0,'limit':1000},error=True)['code']==-32602
assert call('manager','removeSubtask',{'subtask_id':subtask})
assert call('manager','removeTask',{'task_id':task})
assert call('manager','removeProject',{'project_id':project})
for method,params in [('removeProject',{'project_id':fixture['project_id']}),('removeTask',{'task_id':fixture['task_id']}),('removeSubtask',{'subtask_id':fixture['subtask_id']})]:
    assert call('manager',method,params,error=True)['code']==-32009
assert call('manager','getTask',{'task_id':fixture['task_id']})
assert call('manager','getSubtask',{'subtask_id':fixture['subtask_id']})
assert call('manager','enableProjectPublicAccess',{'project_id':fixture['project_id']},error=True)['code']==403
fixture['users']['global']={'username':'jsonrpc','token':fixture['global_token']}
assert call('global','getVersion',error=True)['code']==401
for body in [b'{' ,json.dumps([{'jsonrpc':'2.0','id':i,'method':'getMe'} for i in range(21)]).encode(),b'x'*262145]:
    req=urllib.request.Request(BASE+'jsonrpc.php',body,{'Content-Type':'application/json'})
    try: response=urllib.request.urlopen(req,timeout=30)
    except urllib.error.HTTPError as exc: response=exc
    result=json.load(response)
    assert 'error' in result
    assert 'SQLSTATE' not in json.dumps(result)
print('PASS JSON-RPC: CRUD projects/tasks/subtasks, per-object roles/scopes, password denial, forged parents, assignment, pagination, protected deletion and redacted identity.')
