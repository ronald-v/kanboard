"""Synthetic Projecta HTTP clients. Fixed loopback target; no user configuration."""
from pathlib import Path
from http.cookiejar import CookieJar
import json
import re
import urllib.request
import urllib.parse

ROOT=Path(__file__).resolve().parents[2]
BASE='http://127.0.0.1:18100/'
def request(session,path='',values=None,headers=None):
    data=urllib.parse.urlencode(values).encode() if values is not None else None
    response=session.open(urllib.request.Request(BASE+path,data,headers or {}),timeout=30)
    body=response.read().decode()
    assert 'Fatal error' not in body and 'Warning:' not in body, 'PHP error in '+path
    return response,body
def csrf(body): return re.search(r'name="csrf_token"[^>]*value="([^"]+)"',body)[1]
def authenticated():
    path=ROOT/'.runtime/projecta/http-security-fixture.json'
    fixture=json.loads(path.read_text())
    assert fixture['codes'], 'Recreate HTTP security fixture to obtain synthetic recovery codes'
    session=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))
    _,body=request(session,'?controller=AuthController&action=login')
    _,body=request(session,'?controller=AuthController&action=check',{'csrf_token':csrf(body),'username':fixture['username'],'password':fixture['password']})
    code=fixture['codes'].pop(0)
    _,body=request(session,'?controller=RvAccessController&action=verify&plugin=RvSoftware',{'csrf_token':csrf(body),'code':code})
    assert 'Verifica tu identidad' not in body and 'Protege tu cuenta' not in body
    path.write_text(json.dumps(fixture),encoding='utf-8')
    return session
