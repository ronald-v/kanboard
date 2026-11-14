"""OAuth PKCE + actual MCP HTTP protocol and CRUD in the synthetic sandbox."""
import base64
import hashlib
import json
import secrets
import urllib.request
import urllib.parse
import urllib.error
from projecta_http import BASE,ROOT,authenticated,request,csrf

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None

def raw(path,data=None,headers=None,opener=None):
    req=urllib.request.Request(BASE+path,data,headers or {})
    try: response=(opener or urllib.request.build_opener()).open(req,timeout=30)
    except urllib.error.HTTPError as error: response=error
    body=response.read().decode()
    assert 'Fatal error' not in body and 'Warning:' not in body
    return response,body

response,body=raw('mcp/')
assert response.status==401 and 'resource_metadata=' in response.headers['WWW-Authenticate']
resource=json.loads(raw('mcp/oauth.php?action=resource')[1])
metadata=json.loads(raw('mcp/oauth.php?action=metadata')[1])
assert resource['resource']==BASE+'mcp/'
assert metadata['code_challenge_methods_supported']==['S256']
session=authenticated()
verifier=secrets.token_urlsafe(48)
challenge=base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).decode().rstrip('=')
params={'client_id':'projecta-chatgpt','response_type':'code','redirect_uri':'http://127.0.0.1:18119/callback','scope':'read write delete','state':secrets.token_urlsafe(16),'resource':BASE+'mcp/','code_challenge':challenge,'code_challenge_method':'S256'}
url='?controller=RvOAuthController&action=authorize&plugin=RvSoftware&'+urllib.parse.urlencode(params)
_,consent=request(session,url)
assert 'Autorizar conexión' in consent
session=urllib.request.build_opener(NoRedirect(),next(handler for handler in session.handlers if isinstance(handler,urllib.request.HTTPCookieProcessor)))
response,body=raw('?controller=RvOAuthController&action=authorize&plugin=RvSoftware',urllib.parse.urlencode(params|{'csrf_token':csrf(consent),'decision':'allow'}).encode(),opener=session)
assert response.status==302,('Consent',response.status,body[:200])
location=urllib.parse.urlparse(response.headers['Location'])
assert location.hostname=='127.0.0.1' and location.port==18119
returned=urllib.parse.parse_qs(location.query)
assert returned['state']==[params['state']]
code=returned['code'][0]
exchange={'grant_type':'authorization_code','client_id':params['client_id'],'redirect_uri':params['redirect_uri'],'code':code,'code_verifier':verifier,'resource':params['resource']}
assert raw('mcp/oauth.php?action=token',urllib.parse.urlencode(exchange|{'code_verifier':'x'*64}).encode())[0].status==400
assert raw('mcp/oauth.php?action=token',urllib.parse.urlencode(exchange|{'resource':'https://invalid.example/mcp/'}).encode())[0].status==400
response,body=raw('mcp/oauth.php?action=token',urllib.parse.urlencode(exchange).encode())
assert response.status==200,('Token',response.status,body[:200])
tokens=json.loads(body)
token=tokens['access_token']
assert raw('mcp/oauth.php?action=token',urllib.parse.urlencode(exchange).encode())[0].status==400

sid=None
def mcp(method,params=None,authorization=token,identifier=1):
    global sid
    message={'jsonrpc':'2.0','method':method,'params':params or {}}
    if identifier is not None: message['id']=identifier
    headers={'Content-Type':'application/json','Accept':'application/json, text/event-stream','Authorization':'Bearer '+authorization,'MCP-Protocol-Version':'2025-11-25'}
    if sid: headers['Mcp-Session-Id']=sid
    response,body=raw('mcp/',json.dumps(message).encode(),headers)
    if response.headers.get('Mcp-Session-Id'): sid=response.headers['Mcp-Session-Id']
    assert response.status in (200,202),(method,response.status,body[:300])
    if not body: return None
    if body.startswith('event:') or body.startswith('data:'):
        body=next(line[5:].strip() for line in body.splitlines() if line.startswith('data:'))
    result=json.loads(body)
    assert 'error' not in result,(method,result.get('error'))
    return result.get('result')
initialized=mcp('initialize',{'protocolVersion':'2025-11-25','capabilities':{},'clientInfo':{'name':'Projecta synthetic test','version':'1.0'}})
assert initialized['serverInfo']['name']=='Projecta' and sid
mcp('notifications/initialized',identifier=None)
tools=mcp('tools/list')['tools']
assert len(tools)==15
assert next(tool for tool in tools if tool['name']=='projects_delete')['annotations']['destructiveHint']
negative=json.loads((ROOT/'.runtime/projecta/mcp-negative-fixture.json').read_text())
foreign_headers={'Authorization':'Bearer '+negative['manager'],'Mcp-Session-Id':sid,'MCP-Protocol-Version':'2025-11-25','Accept':'application/json, text/event-stream','Content-Type':'application/json'}
foreign_response,foreign_body=raw('mcp/',json.dumps({'jsonrpc':'2.0','id':1,'method':'tools/list'}).encode(),foreign_headers)
assert foreign_response.status in (403,404) or ('error' in json.loads(foreign_body) and 'result' not in json.loads(foreign_body)),('MCP session isolation',foreign_response.status,foreign_body[:150])
def tool(name,arguments,expect_error=None):
    result=mcp('tools/call',{'name':name,'arguments':arguments})
    structured=result.get('structuredContent')
    if expect_error:
        assert result.get('isError') and structured['error']['code']==expect_error,(name,structured)
        return structured
    assert not result.get('isError'),(name,structured)
    return structured['result']
key='mcp-create-'+secrets.token_hex(8)
project=tool('projects_create',{'fields':{'name':'MCP full CRUD'},'operation_key':key})
assert project==tool('projects_create',{'fields':{'name':'MCP full CRUD'},'operation_key':key})
tool('projects_create',{'fields':{'name':'Changed payload'},'operation_key':key},'KEY_CONFLICT')
assert tool('projects_get',{'project_id':project})
assert tool('projects_update',{'project_id':project,'fields':{'description':'Updated through MCP'},'operation_key':secrets.token_hex(16)})
assert tool('projects_list',{'offset':0,'limit':5})['limit']==5
task=tool('tasks_create',{'fields':{'title':'MCP task','project_id':project},'operation_key':secrets.token_hex(16)})
assert tool('tasks_get',{'task_id':task})
assert tool('tasks_update',{'task_id':task,'fields':{'title':'Updated MCP task'},'operation_key':secrets.token_hex(16)})
assert tool('tasks_list',{'project_id':project,'limit':5})['items']
subtask=tool('subtasks_create',{'fields':{'title':'MCP subtask','task_id':task},'operation_key':secrets.token_hex(16)})
assert tool('subtasks_get',{'subtask_id':subtask})
assert tool('subtasks_update',{'subtask_id':subtask,'fields':{'title':'Updated MCP subtask'},'operation_key':secrets.token_hex(16)})
assert tool('subtasks_list',{'task_id':task,'limit':5})['items']
assert tool('subtasks_delete',{'subtask_id':subtask,'operation_key':secrets.token_hex(16)})
assert tool('tasks_delete',{'task_id':task,'operation_key':secrets.token_hex(16)})
assert tool('projects_delete',{'project_id':project,'operation_key':secrets.token_hex(16)})
fixture=json.loads((ROOT/'.runtime/projecta/api-fixture.json').read_text())
tool('tasks_delete',{'task_id':fixture['task_id'],'operation_key':secrets.token_hex(16)},'HISTORY_CONFLICT')
# Refresh is rotated. Old access and reused refresh are rejected.
refresh={'grant_type':'refresh_token','client_id':params['client_id'],'refresh_token':tokens['refresh_token'],'resource':params['resource']}
response,body=raw('mcp/oauth.php?action=token',urllib.parse.urlencode(refresh).encode())
assert response.status==200,('Refresh',response.status,body[:150])
fresh=json.loads(body)
assert raw('mcp/oauth.php?action=token',urllib.parse.urlencode(refresh).encode())[0].status==400
assert raw('mcp/',json.dumps({'jsonrpc':'2.0','id':1,'method':'tools/list'}).encode(),{'Authorization':'Bearer '+token,'Content-Type':'application/json','Accept':'application/json, text/event-stream'})[0].status==401
(ROOT/'.runtime/projecta/mcp-fixture.json').write_text(json.dumps({'tokens':fresh,'sid':sid}),encoding='utf-8')
print('PASS OAuth PKCE+consent+code replay denial+refresh rotation; MCP initialize/discovery, all 15 CRUD tools, idempotency/key conflict and protected history. ChatGPT/hosting not tested.')
