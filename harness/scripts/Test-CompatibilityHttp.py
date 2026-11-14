"""PHP 8.5 + MariaDB 11.8.9, two independent HTTP/PDO processes.

No SQLite inspection or external endpoints; all data via native/user JSON-RPC.
"""
from concurrent.futures import ThreadPoolExecutor
import base64
import csv
from datetime import datetime
import html
import io
import json
import math
from pathlib import Path
import re
import runpy
import time
import urllib.error
import urllib.parse
import urllib.request
from zoneinfo import ZoneInfo

ROOT = Path(__file__).resolve().parents[2]
BASE = 'http://127.0.0.1:18015/'
PEER = 'http://127.0.0.1:18017/'


class API:
    def __init__(self,user='admin',password='admin',base=BASE):
        assert base in (BASE,PEER)
        self.base = base
        self.header = 'Basic '+base64.b64encode((user+':'+password).encode()).decode()

    def call(self,method,params=None,denied=False):
        request = urllib.request.Request(self.base+'jsonrpc.php',json.dumps({'jsonrpc':'2.0','id':1,'method':method,'params':params or {}}).encode(),headers={'Content-Type':'application/json','Authorization':self.header})
        try:
            with urllib.request.urlopen(request,timeout=90) as response:
                result = json.load(response)
        except urllib.error.HTTPError as error:
            if denied and error.code in (400,403): return True
            raise AssertionError('HTTP failure in '+method+' status '+str(error.code)) from None
        if denied:
            return 'error' in result
        if 'error' in result:
            # Do not print response data/credentials; expose only procedure and
            # native error code. Detailed diagnosis stays local and synthetic.
            raise AssertionError('JSON-RPC failure in '+method+' code '+str(result['error'].get('code')))
        return result['result']


def login(username='admin',password='admin',base=BASE):
    common = runpy.run_path(str(ROOT/'harness/scripts/Test-IsolatedHttp.py'))
    function = common['session']; function.__globals__['BASE'] = base
    return function(username,password)


def main():
    admin = API(); admin_peer = API(base=PEER)
    stamp = str(time.time_ns())
    users = {}
    for name in ('member','peer','leader','outside'):
        username = 'compat-'+name
        row = admin.call('getUserByName',{'username':username})
        users[name] = int(row['id']) if row else admin.call('createUser',{'username':username,'password':'synthetic-compatibility'})
        assert users[name]
    project = admin.call('createProject',{'name':'=Compatibility '+stamp,'owner_id':1})
    assert project
    for name in ('member','peer','leader'):
        assert admin.call('addProjectUser',{'project_id':project,'user_id':users[name],'role':'project-manager' if name == 'leader' else 'project-member'})
    task = admin.call('createTask',{'title':'+Compatibility task','project_id':project})
    source = admin.call('createSubtask',{'title':'@Compatibility source','task_id':task,'user_id':users['member']})
    second = admin.call('createSubtask',{'title':'Timer source','task_id':task,'user_id':users['member']})
    member = API('compat-member','synthetic-compatibility')
    member_peer = API('compat-member','synthetic-compatibility',PEER)
    peer = API('compat-peer','synthetic-compatibility')
    leader = API('compat-leader','synthetic-compatibility')
    leader_peer = API('compat-leader','synthetic-compatibility',PEER)
    outside = API('compat-outside','synthetic-compatibility')
    end = int(datetime(2026,10,1,0,0,20,tzinfo=ZoneInfo('America/Bogota')).timestamp())
    params = {'subtask_id':source,'start':end-40,'end':end,'note':'=SUM(1,2)','operation_key':'manual-'+stamp}
    with ThreadPoolExecutor(2) as pool:
        ids = list(pool.map(lambda api:api.call('rvCreateTime',params),(member,member_peer)))
    assert ids[0] == ids[1]; session = ids[0]
    assert member.call('rvCreateTime',{**params,'end':end-1},True)
    another = peer.call('rvCreateTime',{**params,'start':end-1800,'note':'Peer private note','operation_key':'peer-'+stamp})
    assert another != session
    assert member.call('rvGetTime',{'session_id':another},True)
    assert outside.call('rvGetTime',{'session_id':session},True)
    # Root writes the projection; native member API exposes only own duration.
    assert math.isclose(float(member.call('getTask',{'task_id':task})['time_spent']),.01,abs_tol=1e-6)
    assert math.isclose(float(leader.call('getTask',{'task_id':task})['time_spent']),.51,abs_tol=1e-6)
    assert not member.call('updateSubtask',{'id':source,'task_id':task,'time_spent':99})
    admin.call('rvAssignClient',{'project_id':project,'name':'Compatibility client','operation_key':'client-'+stamp})
    bag = admin.call('rvCreateBag',{'project_id':project,'name':'Compatibility bag','seconds':30,'operation_key':'bag-'+stamp})
    approval = {'session_id':session,'billable':True,'approve':True,'reason':'Compatibility approval','operation_key':'approve-'+stamp,'bag_id':bag}
    assert member.call('rvReviewTime',approval,True)
    assert leader.call('rvReviewTime',{**approval,'bag_id':None},True)
    with ThreadPoolExecutor(2) as pool:
        assert list(pool.map(lambda api:api.call('rvReviewTime',approval),(leader,leader_peer))) == [session,session]
    assert len(leader.call('rvGetBagEntries',{'bag_id':bag})) == 2
    assert int(leader.call('rvGetBags',{'project_id':project})[0]['balance_seconds']) == -10
    correction = {'session_id':session,'start':end-20,'end':end,'billable':True,'note':'Rectified','reason':'Duration correction','operation_key':'rectify-'+stamp}
    approval_peer = {**approval,'session_id':another,'operation_key':'approve-peer-'+stamp}
    with ThreadPoolExecutor(2) as pool:
        jobs = [pool.submit(leader.call,'rvRectifyTime',correction),pool.submit(leader_peer.call,'rvReviewTime',approval_peer)]
        assert [job.result() for job in jobs] == [session,another]
    assert leader.call('rvRectifyTime',correction) == session
    reconciliation = leader.call('rvReconcile',{'project_id':project})
    assert reconciliation['differences'] == []
    assert int(reconciliation['bags'][0]['balance_seconds']) == -1790
    # Native timers serialize across independent processes; browser reload
    # recovers the same open session, then stop receipts remain unique.
    timer = {'subtask_id':second,'user_id':users['member']}
    with ThreadPoolExecutor(2) as pool:
        assert list(pool.map(lambda api:api.call('setSubtaskStartTime',timer),(member,member_peer))) == [True,True]
    active = [row for row in member.call('rvGetMyTime') if int(row['end']) == 0]
    assert len(active) == 1
    assert not member.call('setSubtaskStartTime',{'subtask_id':source,'user_id':users['member']})
    with ThreadPoolExecutor(2) as pool:
        assert list(pool.map(lambda api:api.call('setSubtaskEndTime',timer),(member,member_peer))) == [True,True]
    assert not [row for row in member.call('rvGetMyTime') if int(row['end']) == 0]
    report_params = {'from':'2026-10-01','until':'2026-10-01','filters':{'project_id':project}}
    report = leader.call('rvTimeReport',report_params)
    # Both sessions end 20 s after midnight: 20 s each fall into this day.
    assert report['totals']['worked_seconds'] == 40
    assert report['totals']['consumed_seconds'] == 40
    browser = login('compat-member','synthetic-compatibility')
    for route in ('?controller=DashboardController&action=show','?controller=RvHomeController&action=show&plugin=RvSoftware','?controller=RvHomeController&action=projects&plugin=RvSoftware','?controller=RvTimeController&action=show&plugin=RvSoftware','?controller=RvReviewController&action=show&plugin=RvSoftware'):
        body = browser.open(BASE+route,timeout=40).read().decode()
        assert 'Fatal error' not in body and 'Warning:' not in body and 'Deprecated:' not in body
        assert 'name="password"' not in body
    report_url = BASE+'?controller=RvReportController&action=show&plugin=RvSoftware&from=2026-10-01&until=2026-10-01&project_id='+str(project)
    download = browser.open(report_url+'&format=csv',timeout=40)
    assert 'attachment' in download.headers['Content-Disposition']
    rows = list(csv.reader(io.StringIO(download.read().decode('utf-8-sig'))))
    assert len(rows) == 2 and rows[1][8] == '20' and rows[1][2].startswith("'=Compatibility")
    assert rows[1][14:16] == ['',''] and 'Peer private note' not in str(rows)
    page = browser.open(BASE+'?controller=RvTimeController&action=show&plugin=RvSoftware',timeout=40).read().decode()
    csrf = html.unescape(re.search(r'name="csrf_token" value="([^"]+)"',page)[1])
    payload = {'operation':'manual','subtask_id':source,'end_at':'2026-10-01T12:00:00','duration_seconds':3600,'note':'UI compatibility','operation_key':'ui-'+stamp}
    target = BASE+'?controller=RvTimeController&action=save&plugin=RvSoftware'
    try:
        browser.open(target,urllib.parse.urlencode(payload).encode(),timeout=40)
        raise AssertionError('Missing CSRF accepted')
    except urllib.error.HTTPError as error:
        assert error.code == 403
    assert browser.open(target,urllib.parse.urlencode({**payload,'csrf_token':csrf}).encode(),timeout=40).status == 200
    assert member.call('rvTimeReport',report_params)['totals']['worked_seconds'] == 3620
    assert member.call('rvGetBags',{'project_id':project},True)
    blob = b'Synthetic MariaDB recovery attachment.\n'
    file_id = admin.call('createTaskFile',{'project_id':project,'task_id':task,'filename':'compatibility.txt','blob':base64.b64encode(blob).decode(),'creator_id':1})
    assert file_id and base64.b64decode(member.call('downloadTaskFile',{'file_id':file_id})) == blob
    assert outside.call('downloadTaskFile',{'file_id':file_id},True)
    # Save only synthetic object IDs and expected metrics, never auth material.
    fixture = {'project':project,'bag':bag,'file_id':file_id,'file_sha256':__import__('hashlib').sha256(blob).hexdigest(),'totals':admin.call('rvTimeReport',report_params)['totals'],'balance':int(admin.call('rvGetBags',{'project_id':project})[0]['balance_seconds'])}
    (ROOT/'.runtime/compatibility/http-fixture.json').write_text(json.dumps(fixture),encoding='utf-8')
    print('PASS PHP 8.5 / MariaDB 11.8.9 HTTP: roles, native projections, UI/CSRF, CSV, exact worked dates, attachment authorization; independent-PDO manual/timer/approval/correction concurrency and ledger reconciliation.')


if __name__ == '__main__':
    main()
