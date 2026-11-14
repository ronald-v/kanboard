import json
import secrets
import subprocess
import urllib.request
import urllib.error
from projecta_http import ROOT,BASE

tokens=json.loads((ROOT/'.runtime/projecta/mcp-negative-fixture.json').read_text())
fixture=json.loads((ROOT/'.runtime/projecta/api-fixture.json').read_text())
def rpc(token,method,params=None,sid=None,extra=None):
    headers={'Authorization':'Bearer '+token,'Content-Type':'application/json','Accept':'application/json, text/event-stream','MCP-Protocol-Version':'2025-11-25'}
    if sid: headers['Mcp-Session-Id']=sid
    if extra: headers.update(extra)
    request=urllib.request.Request(BASE+'mcp/',json.dumps({'jsonrpc':'2.0','id':1,'method':method,'params':params or {}}).encode(),headers)
    try: response=urllib.request.urlopen(request,timeout=30)
    except urllib.error.HTTPError as error: response=error
    body=response.read().decode()
    if body.startswith('event:') or body.startswith('data:'): body=next(line[5:].strip() for line in body.splitlines() if line.startswith('data:'))
    try: parsed=json.loads(body) if body else None
    except json.JSONDecodeError: parsed={'error':body}
    return response,parsed
def initialize(role):
    response,body=rpc(tokens[role],'initialize',{'protocolVersion':'2025-11-25','capabilities':{},'clientInfo':{'name':'Projecta negative tests','version':'1'}})
    assert response.status==200 and 'result' in body
    return response.headers['Mcp-Session-Id']
manager=initialize('manager'); viewer=initialize('viewer')
def denied(role,sid,name,arguments,code):
    response,body=rpc(tokens[role],'tools/call',{'name':name,'arguments':arguments},sid)
    result=body.get('result',{})
    assert result.get('isError') and result['structuredContent']['error']['code']==code,(role,name,body)
denied('viewer',viewer,'tasks_create',{'fields':{'title':'Denied','project_id':fixture['project_id']},'operation_key':secrets.token_hex(16)},'FORBIDDEN')
denied('manager',manager,'tasks_get',{'task_id':fixture['foreign_task_id']},'FORBIDDEN')
denied('reader',manager,'tasks_create',{'fields':{'title':'Denied by current scope','project_id':fixture['project_id']},'operation_key':secrets.token_hex(16)},'FORBIDDEN')
for extra in [{'Origin':'https://invalid.example'},{'Host':'invalid.example'}]:
    response,_=rpc(tokens['manager'],'tools/list',sid=manager,extra=extra)
    assert response.status==403
response,body=rpc(tokens['manager'],'tools/call',{'name':'tasks_create','arguments':{'fields':{'title':'Invalid','project_id':fixture['project_id'],'is_admin':True},'operation_key':secrets.token_hex(16)}},manager)
assert 'error' in body or body.get('result',{}).get('isError')
fresh=json.loads((ROOT/'.runtime/projecta/mcp-fixture.json').read_text())['tokens']['access_token']
result=subprocess.run(['powershell','-NoProfile','-File','harness/scripts/ProjectaEnvironment.ps1','-Script','harness/scripts/Projecta-RevokeMcpFixture.php'],cwd=ROOT,capture_output=True,text=True,timeout=30)
assert result.returncode==0
assert rpc(fresh,'tools/list')[0].status==401
prefix,signature=fresh.rsplit('.',1)
position=len(signature)//2
forged=prefix+'.'+signature[:position]+('A' if signature[position]!='A' else 'B')+signature[position+1:]
assert rpc(forged,'tools/list')[0].status==401
print('PASS MCP negatives: viewer writes, foreign objects, narrowed scope on existing session, Host/Origin, closed schema, explicit revocation and forged token denied.')
