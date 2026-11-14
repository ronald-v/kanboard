"""Logical MariaDB + attachment restore using the extracted, verified artifact.

Only fixed local synthetic schemas are used. No external credentials or targets.
"""
import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import subprocess
import time
import urllib.error
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[2]
RUNTIME = ROOT/'.runtime/compatibility'
spec = importlib.util.spec_from_file_location('compat_http',ROOT/'harness/scripts/Test-CompatibilityHttp.py')
http = importlib.util.module_from_spec(spec); spec.loader.exec_module(http)


def main():
    parser = argparse.ArgumentParser(); parser.add_argument('artifact',type=Path); args = parser.parse_args()
    artifact = args.artifact.resolve()
    assert artifact.is_relative_to(ROOT/'.runtime/releases')
    assert os.environ.get('DB_HOSTNAME') == '127.0.0.1' and os.environ.get('DB_PORT') == '13318' and os.environ.get('DB_NAME') == 'kanboard_compat_http'
    scratch = RUNTIME/('restore-'+str(time.time_ns())); scratch.mkdir()
    with zipfile.ZipFile(artifact) as archive:
        assert all(name.startswith('kanboard/') and '..' not in PurePosixPath(name).parts for name in archive.namelist())
        manifest = json.loads(archive.read('kanboard/rvsoftware-manifest.json'))
        for name,digest in manifest['files'].items():
            assert hashlib.sha256(archive.read('kanboard/'+name)).hexdigest() == digest
        archive.extractall(scratch)
    app = scratch/'kanboard'; data = scratch/'data'; data.mkdir(); (data/'cache').mkdir()
    shutil.copytree(RUNTIME/'data/files',data/'files')
    fixture = json.loads((RUNTIME/'http-fixture.json').read_text(encoding='utf-8'))
    fixture['balance'] = int(http.API().call('rvGetBags',{'project_id':fixture['project']})[0]['balance_seconds'])
    fixture['totals'] = http.API().call('rvTimeReport',{'from':'2026-10-01','until':'2026-10-01','filters':{'project_id':fixture['project']}})['totals']
    fixture_path = scratch/'fixture.json'; fixture_path.write_text(json.dumps(fixture),encoding='utf-8')
    binaries = RUNTIME/'mariadb1189/mariadb-11.8.9-winx64/bin'
    environment = os.environ.copy(); environment['MYSQL_PWD'] = 'synthetic-local-only'
    native_options = ['--host=127.0.0.1','--port=13318','--user=compat_synthetic']
    dump = scratch/'synthetic.sql'
    result = subprocess.run([str(binaries/'mariadb-dump.exe'),*native_options,'--single-transaction','--skip-lock-tables','--hex-blob','--result-file='+str(dump),'kanboard_compat_http'],env=environment,capture_output=True)
    assert result.returncode == 0, 'Synthetic dump failed'
    sql = dump.read_text(encoding='utf-8')
    assert '\nUSE `kanboard_compat_http`' not in sql and '\nCREATE DATABASE' not in sql
    adjustment = http.API().call('rvAdjustBag',{'bag_id':fixture['bag'],'seconds':300,'reason':'After synthetic backup','operation_key':'backup-adjust-'+str(time.time_ns())})
    assert adjustment
    with dump.open('rb') as source:
        result = subprocess.run([str(binaries/'mariadb.exe'),*native_options,'kanboard_compat_restore'],env=environment,stdin=source,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    assert result.returncode == 0, 'Synthetic import failed'
    environment.pop('MYSQL_PWD',None)
    environment.update({'DB_NAME':'kanboard_compat_restore','DATA_DIR':str(data),'FILES_DIR':str(data/'files'),'CACHE_DIR':str(data/'cache'),'PLUGINS_DIR':str(app/'plugins'),'RV_COMPAT_APP':str(app),'RV_COMPAT_FIXTURE':str(fixture_path)})
    php = RUNTIME/'php85/php.exe'
    options = ['-n','-d','extension_dir='+str(RUNTIME/'php85/ext')]
    for extension in ('gd','mbstring','openssl','pdo_mysql','pdo_sqlite','zip','ldap'):
        options.extend(['-d','extension='+extension])
    result = subprocess.run([str(php),*options,str(ROOT/'harness/scripts/Test-CompatibilityRestore.php')],cwd=app,env=environment,capture_output=True)
    assert result.returncode == 0, 'Extracted PHP/MariaDB restore failed'
    actual = json.loads(result.stdout)
    assert actual['totals'] == fixture['totals'] and actual['balance'] == fixture['balance']
    assert actual['differences'] == [] and actual['file_hash'] == fixture['file_sha256']
    assert actual['schema'] == 6 and actual['engines'] == ['InnoDB']
    assert actual['php'].startswith('8.5.') and actual['maria'].startswith('11.8.9-MariaDB')
    # Verify real HTTP execution from the extracted code and restored database.
    base = 'http://127.0.0.1:18018/'
    with subprocess.Popen([str(php),*options,'-S','127.0.0.1:18018','-t',str(app)],cwd=app,env=environment,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0)) as server:
        try:
            for attempt in range(40):
                assert server.poll() is None, 'Restored HTTP process terminated'
                try: urllib.request.urlopen(base,timeout=1).close(); break
                except OSError: time.sleep(.1)
            else: raise AssertionError('Restored server not ready')
            browser = http.login(base=base)
            page = browser.open(base+'?controller=RvHomeController&action=show&plugin=RvSoftware',timeout=40).read().decode()
            assert 'rv-sidebar' in page and '<?php' not in page and 'Deprecated:' not in page
            page = browser.open(base+'?controller=RvReportController&action=show&plugin=RvSoftware&from=2026-10-01&until=2026-10-01&project_id='+str(fixture['project']),timeout=40).read().decode()
            assert 'Reporte de horas' in page and 'Fatal error' not in page
            for path in ('.git/config','harness/feature-list.json','AGENTS.md','config.php','data/config.php','data/synthetic.sql','.runtime/compatibility/http-fixture.json'):
                assert not (app/path).exists(), 'Sensitive path exists inside document root: '+path
                try:
                    response = urllib.request.urlopen(base+path,timeout=3)
                    body = response.read().decode('utf-8',errors='replace')
                    # Some PHP development-server routes fall back to the front
                    # controller and login instead of an HTTP 404. This is not
                    # proof of hosting deny rules; absence is verified above.
                    assert 'name="password"' in body, 'Unexpected response to absent sensitive path: '+path
                except urllib.error.HTTPError as error: assert error.code in (403,404)
        finally: server.terminate(); server.wait(timeout=10)
    print('PASS actual ZIP / PHP 8.5 / MariaDB 11.8.9 recovery: native logical dump/import, source changed after backup; restored exact report/balance, audit schema, InnoDB ledger and attachment hash; extracted HTTP login/Home/report and absent sensitive paths.')
    print('Synthetic recovery directory:',scratch.relative_to(ROOT).as_posix())


if __name__ == '__main__': main()
