"""F-011: worked date, precise totals, real CSV authorization and formula safety."""
import csv
from datetime import datetime
import io
import time
import urllib.error
from zoneinfo import ZoneInfo
from rv_http import API, BASE, login

PAGE = '?controller=RvReportController&action=show&plugin=RvSoftware&from=2026-10-01&until=2026-10-01'


def main():
    root = API()
    users = {name: int(root.call('getUserByName', {'username':'review-' + name})['id']) for name in ('member','peer','leader')}
    project = root.call('createProject', {'name':'=Synthetic report ' + str(time.time_ns())})
    for name, user in users.items():
        assert root.call('addProjectUser', {'project_id':project,'user_id':user,'role':'project-manager' if name == 'leader' else 'project-member'})
    task = root.call('createTask', {'title':'+Synthetic report task','project_id':project})
    subtask = root.call('createSubtask', {'title':'@Synthetic report source','task_id':task})
    member = API('review-member','synthetic-review'); peer = API('review-peer','synthetic-review'); leader = API('review-leader','synthetic-review')
    end = int(datetime(2026,10,1,0,0,20,tzinfo=ZoneInfo('America/Bogota')).timestamp())
    first = member.call('rvCreateTime', {'subtask_id':subtask,'start':end-40,'end':end,'note':'=SUM(1,2)','operation_key':'report-midnight-' + str(time.time_ns())})
    peer.call('rvCreateTime', {'subtask_id':subtask,'start':end+3600,'end':end+5400,'note':'Private peer work','operation_key':'report-peer-' + str(time.time_ns())})
    leader.call('rvAssignClient', {'project_id':project,'name':'Report client','operation_key':'report-client-' + str(time.time_ns())})
    bag = leader.call('rvCreateBag', {'project_id':project,'name':'Report bag','seconds':3600,'operation_key':'report-bag-' + str(time.time_ns())})
    leader.call('rvReviewTime', {'session_id':first,'billable':True,'approve':True,'reason':'Report approval','operation_key':'report-approval-' + str(time.time_ns()),'bag_id':bag})
    params = {'from':'2026-10-01','until':'2026-10-01','filters':{'project_id':project}}
    report = leader.call('rvTimeReport',params)
    assert report['totals'] == {'worked_seconds':1820,'billable_seconds':20,'approved_seconds':20,'consumed_seconds':20,'unreconciled_sessions':0}
    assert member.call('rvTimeReport',params)['totals']['worked_seconds'] == 20
    browser = login('review-member','synthetic-review')
    url = BASE + PAGE + '&project_id=' + str(project)
    body = browser.open(url).read().decode()
    assert 'Private peer work' not in body and 'Report bag' not in body and '=SUM(1,2)' in body
    response = browser.open(url + '&format=csv')
    assert 'attachment' in response.headers['Content-Disposition']
    rows = list(csv.reader(io.StringIO(response.read().decode('utf-8-sig'))))
    assert len(rows) == 2 and rows[1][8:12] == ['20','20','20','20']
    assert rows[1][2].startswith("'=Synthetic report") and rows[1][3] == "'+Synthetic report task" and rows[1][4] == "'@Synthetic report source"
    assert rows[1][16] == "'=SUM(1,2)" and rows[1][14:16] == ['','']
    try:
        browser.open(url + '&format=csv&bag_id=' + str(bag))
        raise AssertionError('Member used commercial filter')
    except urllib.error.HTTPError as error:
        assert error.code == 400
    assert root.call('removeProjectUser', {'project_id':project,'user_id':users['member']})
    assert 'error' in member.call('rvTimeReport',params,True)
    # Forged project filter cannot restore revoked access in CSV.
    try:
        browser.open(url + '&format=csv')
        raise AssertionError('Revoked project CSV allowed')
    except urllib.error.HTTPError as error:
        assert error.code == 403  # Native project middleware rejects before controller.
    print('PASS F-011: Bogotá midnight clipping, API/UI/CSV identical exact totals, own scope, revoked filters denied, commercial data hidden, CSV formulas neutralized.')


if __name__ == '__main__':
    main()
