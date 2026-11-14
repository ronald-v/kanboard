"""Extract, restore and run the exact preview artifact against a reserved synthetic schema."""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path,PurePosixPath
import shutil
import socket
import subprocess
import time
import urllib.request
import zipfile
from projecta_http import ROOT

parser=argparse.ArgumentParser(); parser.add_argument('artifact',type=Path); args=parser.parse_args()
artifact=args.artifact.resolve(); assert artifact.is_relative_to(ROOT/'.runtime/releases')
scratch=ROOT/'.runtime'/('projecta-restore-'+str(time.time_ns())); scratch.mkdir()
with zipfile.ZipFile(artifact) as archive:
    for name in archive.namelist():
        path=PurePosixPath(name)
        assert name.startswith('kanboard/') and '..' not in path.parts and not path.is_absolute()
    manifest=json.loads(archive.read('kanboard/rvsoftware-manifest.json'))
    assert manifest['kind']=='working-tree-preview' and manifest['pluginVersion']=='0.2.0'
    for name,digest in manifest['files'].items(): assert hashlib.sha256(archive.read('kanboard/'+name)).hexdigest()==digest
    assert 'kanboard/mcp/vendor/autoload.php' in archive.namelist()
    assert not any(name.endswith('/config.php') or '/harness/' in name or '/.git/' in name for name in archive.namelist())
    archive.extractall(scratch)
app=scratch/'kanboard'
private=scratch/'private'; shutil.copytree(ROOT/'.runtime/projecta/private',private)
data=scratch/'data'; shutil.copytree(ROOT/'.runtime/projecta/data',data)
php=ROOT/'.runtime/compatibility/php85/php.exe'
command=[str(php),'-n','-d','extension_dir='+str(php.parent/'ext')]
for extension in ['gd','mbstring','openssl','pdo_mysql','pdo_sqlite','zip','sodium']: command+=['-d','extension='+extension]
environment=os.environ.copy(); environment.pop('DATABASE_URL',None)
environment.update(DB_DRIVER='mysql',DB_HOSTNAME='127.0.0.1',DB_PORT='13318',DB_USERNAME='compat_synthetic',DB_PASSWORD='synthetic-local-only',DB_NAME='kanboard_compat_http',
    DATA_DIR=str(ROOT/'.runtime/projecta/data'),FILES_DIR=str(ROOT/'.runtime/projecta/data/files'),CACHE_DIR=str(ROOT/'.runtime/projecta/data/cache'),PLUGINS_DIR=str(ROOT/'.runtime/projecta/app/plugins'),
    PROJECTA_KEY='c3ludGhldGljLW9ubHktMzItYnl0ZS1rZXktMDAwMDA=',PROJECTA_BASE_URL='http://127.0.0.1:18100',PROJECTA_PRIVATE_DIR=str(ROOT/'.runtime/projecta/private'),
    PROJECTA_OAUTH_REDIRECT_URIS='["http://127.0.0.1:18119/callback"]',PROJECTA_RECOVERY_ROOT=str(ROOT/'.runtime/projecta/app'),DEBUG='false',LOG_DRIVER='stderr',MYSQL_PWD='synthetic-local-only')
def inspect():
    result=subprocess.run(command+[str(ROOT/'harness/scripts/Projecta-ArtifactInspect.php')],env=environment,capture_output=True,cwd=app,timeout=40)
    if result.returncode: (scratch/'inspect-error.txt').write_bytes(result.stdout+result.stderr)
    assert result.returncode==0,'Restored runtime failed; inspect isolated diagnostics locally'
    return json.loads(result.stdout)
before=inspect()
maria=ROOT/'.runtime/compatibility/mariadb1189/mariadb-11.8.9-winx64/bin'
connection=['--no-defaults','--host=127.0.0.1','--port=13318','--user=compat_synthetic','--skip-ssl']
dump=subprocess.run([str(maria/'mariadb-dump.exe')]+connection+['--single-transaction','--skip-comments','kanboard_compat_http'],env=environment,capture_output=True,timeout=60)
assert dump.returncode==0,'Synthetic dump failed'
(scratch/'synthetic-backup.sql').write_bytes(dump.stdout)
restored=subprocess.run([str(maria/'mariadb.exe')]+connection+['kanboard_compat_restore'],env=environment,input=dump.stdout,capture_output=True,timeout=60)
assert restored.returncode==0,'Reserved schema restore failed'
environment.update(DB_NAME='kanboard_compat_restore',DATA_DIR=str(data),FILES_DIR=str(data/'files'),CACHE_DIR=str(data/'cache'),PLUGINS_DIR=str(app/'plugins'),PROJECTA_PRIVATE_DIR=str(private),PROJECTA_RECOVERY_ROOT=str(app))
after=inspect()
assert before==after,'Restored state differs'
assert after['schema']==9 and after['mcp_tools']==15 and after['ledger']['differences']==[]
log=(scratch/'http.log').open('wb')
server=subprocess.Popen(command+['-d','auto_prepend_file='+str(app/'config/projecta.php'),'-S','127.0.0.1:18111','-t',str(app)],env=environment,cwd=app,stdout=log,stderr=log)
try:
    for _ in range(50):
        try:
            with socket.create_connection(('127.0.0.1',18111),timeout=.1): break
        except OSError: time.sleep(.1)
    base='http://127.0.0.1:18111/'
    body=urllib.request.urlopen(base+'?controller=AuthController&action=login',timeout=15).read().decode()
    assert 'Bienvenido a Projecta' in body and 'rv-auth-card' in body
    fixture=json.loads((ROOT/'.runtime/projecta/api-fixture.json').read_text()); actor=fixture['users']['manager']
    authorization=base64.b64encode((actor['username']+':'+actor['token']).encode()).decode()
    request=urllib.request.Request(base+'jsonrpc.php',json.dumps({'jsonrpc':'2.0','id':1,'method':'getSubtask','params':{'subtask_id':fixture['subtask_id']}}).encode(),{'Authorization':'Basic '+authorization,'Content-Type':'application/json'})
    actual=json.load(urllib.request.urlopen(request,timeout=15))
    assert 'result' in actual and actual['result']['title']==before['subtask']
finally:
    server.terminate(); server.wait(timeout=10); log.close()
print('PASS exact preview: all hashes, schema9, table counts, approved hour/report/ledger, attachment, encrypted factor/key, individual API and MCP/OAuth dependencies restored; extracted HTTP login and API work. No hosting or real data touched.')
