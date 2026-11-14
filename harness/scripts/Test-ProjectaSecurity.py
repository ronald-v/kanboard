"""Real HTTP MFA workflow on a guarded, disposable loopback application."""
from http.cookiejar import CookieJar
from pathlib import Path
import base64
import hashlib
import hmac
import json
import re
import struct
import time
import urllib.request
import urllib.parse
import urllib.error

BASE='http://127.0.0.1:18100/'
ROOT=Path(__file__).resolve().parents[2]
def totp(secret):
    key=base64.b32decode(secret+'='*((8-len(secret)%8)%8))
    digest=hmac.new(key,struct.pack('>Q',int(time.time()//30)),hashlib.sha1).digest()
    offset=digest[-1]&15
    return f'{(int.from_bytes(digest[offset:offset+4],"big")&0x7fffffff)%1000000:06d}'
def csrf(html):
    return re.search(r'name="csrf_token"[^>]*value="([^"]+)"',html)[1]
def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))
def request(opener,url='',values=None):
    data=urllib.parse.urlencode(values).encode() if values is not None else None
    response=opener.open(BASE+url,data,timeout=30)
    body=response.read().decode()
    assert 'Fatal error' not in body and 'Warning:' not in body
    return response,body

session=client()
_,login=request(session,'?controller=AuthController&action=login')
_,mfa=request(session,'?controller=AuthController&action=check',{'csrf_token':csrf(login),'username':'projecta-http','password':'synthetic-http-password'})
assert 'Protege tu cuenta' in mfa
_,blocked=request(session,'?controller=UserListController&action=show')
assert 'Protege tu cuenta' in blocked
secret=re.search(r'<code>([A-Z2-7]+)</code>',mfa)[1]
try:
    request(session,'?controller=RvAccessController&action=verify&plugin=RvSoftware',{'code':totp(secret)})
    raise AssertionError('Missing CSRF accepted')
except urllib.error.HTTPError as error: assert error.code==403
_,recovery=request(session,'?controller=RvAccessController&action=verify&plugin=RvSoftware',{'csrf_token':csrf(mfa),'code':totp(secret)})
codes=re.findall(r'<code>([a-f0-9]{32})</code>',recovery)
assert len(codes)==10
_,home=request(session,'?controller=RvHomeController&action=show&plugin=RvSoftware')
assert 'Protege tu cuenta' not in home and 'Verifica tu identidad' not in home
_,native=request(session,'?controller=TwoFactorController&action=deactivate')
assert 'Verifica tu identidad' in native

# Fresh password session must verify factor again; enrollment code is already consumed.
other=client()
_,login=request(other,'?controller=AuthController&action=login')
_,challenge=request(other,'?controller=AuthController&action=check',{'csrf_token':csrf(login),'username':'projecta-http','password':'synthetic-http-password'})
assert 'Verifica tu identidad' in challenge
_,used=request(other,'?controller=RvAccessController&action=verify&plugin=RvSoftware',{'csrf_token':csrf(challenge),'code':totp(secret)})
assert 'Código inválido' in used
_,home=request(other,'?controller=RvAccessController&action=verify&plugin=RvSoftware',{'csrf_token':csrf(used),'code':codes[0]})
assert 'Verifica tu identidad' not in home
fixture={'username':'projecta-http','password':'synthetic-http-password','secret':secret,'codes':codes[1:]}
(ROOT/'.runtime/projecta/http-security-fixture.json').write_text(json.dumps(fixture),encoding='utf-8')
print('PASS HTTP: mandatory enrollment, protected URL, CSRF, encrypted TOTP, replay denial, recovery, native deactivation blocked.')
