"""Synthetic end-to-end backup/restore using the actual extracted ZIP runtime.

Never restore over the source DB: every run creates a new .runtime directory.
"""
import argparse
import base64
from datetime import datetime
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import sqlite3
import subprocess
import time
import urllib.error
import urllib.request
import zipfile
from zoneinfo import ZoneInfo
from rv_http import API, ROOT, login


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('artifact',type=Path)
    args = parser.parse_args()
    artifact = args.artifact.resolve()
    assert artifact.is_relative_to(ROOT / '.runtime/releases')
    scratch = ROOT / '.runtime' / ('recovery-' + str(time.time_ns()))
    scratch.mkdir()
    with zipfile.ZipFile(artifact) as archive:
        assert all(not PurePosixPath(name).is_absolute() and '..' not in PurePosixPath(name).parts and name.startswith('kanboard/') for name in archive.namelist())
        manifest = json.loads(archive.read('kanboard/rvsoftware-manifest.json'))
        for name,digest in manifest['files'].items():
            assert hashlib.sha256(archive.read('kanboard/'+name)).hexdigest() == digest
        archive.extractall(scratch)
    app = scratch / 'kanboard'
    admin = API('admin','admin'); root = API()
    user = root.call('getUserByName',{'username':'review-member'})
    project = root.call('createProject',{'name':'Synthetic recovery ' + str(time.time_ns())})
    assert root.call('addProjectUser',{'project_id':project,'user_id':int(user['id']),'role':'project-member'})
    task = root.call('createTask',{'title':'Recovery task','project_id':project})
    subtask = root.call('createSubtask',{'title':'Recovery source','task_id':task})
    start = int(datetime(2026,10,1,8,tzinfo=ZoneInfo('America/Bogota')).timestamp())
    session = API('review-member','synthetic-review').call('rvCreateTime',{'subtask_id':subtask,'start':start,'end':start+3600,'note':'Synthetic recovery work','operation_key':'recovery-work-' + str(time.time_ns())})
    admin.call('rvAssignClient',{'project_id':project,'name':'Recovery client','operation_key':'recovery-client-' + str(time.time_ns())})
    bag = admin.call('rvCreateBag',{'project_id':project,'name':'Recovery bag','seconds':7200,'operation_key':'recovery-bag-' + str(time.time_ns())})
    admin.call('rvReviewTime',{'session_id':session,'billable':True,'approve':True,'reason':'Recovery approval','operation_key':'recovery-approval-' + str(time.time_ns()),'bag_id':bag})
    blob = b'Synthetic attachment for coordinated restore.\n'
    file_id = root.call('createTaskFile',{'project_id':project,'task_id':task,'filename':'recovery.txt','blob':base64.b64encode(blob).decode(),'creator_id':int(user['id'])})
    assert file_id
    assert base64.b64decode(API('review-member','synthetic-review').call('downloadTaskFile',{'file_id':file_id})) == blob
    outsider = API('review-peer','synthetic-review')
    assert 'error' in outsider.call('downloadTaskFile',{'file_id':file_id},True)
    expected_report = admin.call('rvTimeReport',{'from':'2026-10-01','until':'2026-10-01','filters':{'project_id':project}})
    expected_ledger = admin.call('rvReconcile',{'project_id':project})
    assert expected_report['totals']['worked_seconds'] == 3600
    assert expected_report['totals']['consumed_seconds'] == 3600
    assert int(expected_ledger['bags'][0]['balance_seconds']) == 3600
    data = scratch / 'restored-data'; data.mkdir()
    with sqlite3.connect((ROOT / '.runtime/data/db.sqlite').as_uri()+'?mode=ro',uri=True) as source, sqlite3.connect(data/'db.sqlite') as destination:
        source.backup(destination)
    shutil.copytree(ROOT/'.runtime/data/files',data/'files')
    (data/'cache').mkdir()
    # Mutate only the live synthetic source after backup. The restored image
    # must retain the prior 3600 s balance and attachment, not current state.
    admin.call('rvAdjustBag',{'bag_id':bag,'seconds':300,'reason':'After backup mutation','operation_key':'after-backup-' + str(time.time_ns())})
    environment = os.environ.copy()
    environment.pop('DATABASE_URL',None)
    for name in ('DB_USERNAME','DB_PASSWORD','DB_HOSTNAME','DB_NAME','DB_PORT'):
        environment.pop(name,None)
    environment.update({'RV_RECOVERY_TEST':'true','RV_RECOVERY_ROOT':str(app),'RV_RECOVERY_PROJECT':str(project),'RV_RECOVERY_FILE':str(file_id),'DB_DRIVER':'sqlite','DB_FILENAME':str(data/'db.sqlite'),'DATA_DIR':str(data),'FILES_DIR':str(data/'files'),'CACHE_DIR':str(data/'cache'),'PLUGINS_DIR':str(app/'plugins'),'DB_RUN_MIGRATIONS':'true','PLUGIN_INSTALLER':'false','DEBUG':'false','LOG_DRIVER':'stderr'})
    command = ['php','-d','extension=gd','-d','extension=pdo_sqlite',str(ROOT/'harness/scripts/Test-Recovery.php')]
    result = subprocess.run(command,cwd=app,env=environment,capture_output=True,check=False)
    if result.returncode:
        raise AssertionError('Restored PHP runtime failed; inspect isolated child stderr locally')
    actual = json.loads(result.stdout)
    assert actual['totals'] == expected_report['totals']
    assert actual['bags'] == expected_ledger['bags']
    assert actual['differences'] == []
    assert actual['file_hash'] == hashlib.sha256(blob).hexdigest()
    assert int(actual['schema']) == 6
    with sqlite3.connect(data/'db.sqlite') as connection:
        assert connection.execute('PRAGMA integrity_check').fetchone()[0] == 'ok'
        assert connection.execute('PRAGMA foreign_key_check').fetchall() == []
    base = 'http://127.0.0.1:18008/'
    with subprocess.Popen(['php','-d','extension=gd','-d','extension=pdo_sqlite','-S','127.0.0.1:18008','-t',str(app)],cwd=app,env=environment,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0)) as server:
        try:
            ready = False
            for attempt in range(30):
                if server.poll() is not None: raise AssertionError('Extracted HTTP server failed to start')
                try:
                    urllib.request.urlopen(base,timeout=1).close(); ready = True; break
                except (OSError,urllib.error.URLError):
                    time.sleep(.1)
            assert ready
            browser = login(base=base)
            page = browser.open(base+'?controller=RvHomeController&action=show&plugin=RvSoftware').read().decode()
            assert 'rv-sidebar' in page and '<?php' not in page
            restored_api = API('admin','admin',base)
            assert restored_api.call('rvTimeReport',{'from':'2026-10-01','until':'2026-10-01','filters':{'project_id':project}})['totals'] == expected_report['totals']
            restored_member = API('review-member','synthetic-review',base)
            assert base64.b64decode(restored_member.call('downloadTaskFile',{'file_id':file_id})) == blob
            for path in ('.git/config','AGENTS.md','harness/feature-list.json','.runtime/data/db.sqlite','data/db.sqlite','data/config.php','config.php','.env','data/backup.sql'):
                try:
                    urllib.request.urlopen(base+path,timeout=3)
                    raise AssertionError('Sensitive artifact path served: '+path)
                except urllib.error.HTTPError as error:
                    assert error.code in (403,404)
        finally:
            server.terminate(); server.wait(timeout=10)
    print('PASS extracted artifact PHP + synthetic restore: login API, project/task/session/approval/bag/report/attachment; foreign download denied; restored exact report, ledger, file hash, schema 6 and FK integrity after source mutation.')
    print('Restore directory:',scratch.relative_to(ROOT).as_posix())
    print('PASS extracted artifact HTTP: PHP executes, login/Home/report/download restored; sensitive paths absent/denied with data stored outside document root. Hosting rules remain unverified.')


if __name__ == '__main__':
    main()
