"""F-010: atomic approval/ledger under independent process concurrency."""
from concurrent.futures import ThreadPoolExecutor
import time
from rv_http import API, BASE, login, query


def main():
    root = API()
    users = {name: int(root.call('getUserByName', {'username': 'review-' + name})['id']) for name in ('member','peer','leader')}
    project = root.call('createProject', {'name': 'Synthetic ledger ' + str(time.time_ns())})
    for name, user in users.items():
        assert root.call('addProjectUser', {'project_id': project, 'user_id': user, 'role': 'project-manager' if name == 'leader' else 'project-member'})
    leader = API('review-leader','synthetic-review')
    other = API('review-leader','synthetic-review','http://127.0.0.1:18007/')
    leader.call('rvAssignClient', {'project_id': project,'name': 'Ledger client','operation_key': 'ledger-client-' + str(time.time_ns())})
    bag = leader.call('rvCreateBag', {'project_id': project,'name':'Ledger bag','seconds':1800,'operation_key':'ledger-bag-' + str(time.time_ns())})
    end = int(time.time())-120
    ids = []
    for name, seconds in (('member',3600),('peer',900)):
        task = root.call('createTask', {'title':'Ledger ' + name,'project_id':project})
        subtask = root.call('createSubtask', {'title':'Ledger source','task_id':task})
        ids.append(API('review-' + name,'synthetic-review').call('rvCreateTime', {'subtask_id':subtask,'start':end-seconds,'end':end,'note':'Synthetic ledger','operation_key':'ledger-record-' + str(time.time_ns())}))
    params = {'session_id': ids[0], 'billable':True, 'approve':True, 'reason':'Synthetic approval', 'operation_key':'ledger-approve-' + str(time.time_ns()), 'bag_id':bag}
    assert 'error' in leader.call('rvReviewTime', {**params,'bag_id':None}, True)
    assert query('SELECT status FROM rv_time_meta WHERE session_id=?',(ids[0],))[0]['status'] == 'pending'
    browser = login('review-leader','synthetic-review')
    assert 'puede exceder el saldo' in browser.open(BASE + '?controller=RvReviewController&action=show&plugin=RvSoftware').read().decode()
    with ThreadPoolExecutor(2) as pool:
        assert list(pool.map(lambda client:client.call('rvReviewTime',params),(leader,other))) == [ids[0],ids[0]]
    assert len(query('SELECT id FROM rv_bag_entries WHERE session_id=?',(ids[0],))) == 1
    # Two owners/tasks, one project bag: approval and a compensation contend for its mutex.
    rectify = {'session_id':ids[0],'start':end-1200,'end':end,'billable':True,'note':'Shorter','reason':'Synthetic correction','operation_key':'ledger-correct-' + str(time.time_ns())}
    second = {**params,'session_id':ids[1],'operation_key':'ledger-second-' + str(time.time_ns())}
    with ThreadPoolExecutor(2) as pool:
        jobs = [pool.submit(leader.call,'rvRectifyTime',rectify),pool.submit(other.call,'rvReviewTime',second)]
        assert [job.result() for job in jobs] == ids
    report = leader.call('rvReconcile',{'project_id':project})
    assert report['differences'] == []
    assert int(report['bags'][0]['balance_seconds']) == -300
    assert leader.call('rvRectifyTime',rectify) == ids[0]
    assert len(query('SELECT id FROM rv_bag_entries WHERE session_id=?',(ids[0],))) == 2
    assert query('SELECT SUM(seconds) AS n FROM rv_bag_entries WHERE bag_id=?',(bag,))[0]['n'] == -300
    adjustment = {'bag_id':bag,'seconds':300,'reason':'Synthetic reconciliation adjustment','operation_key':'ledger-adjust-' + str(time.time_ns())}
    with ThreadPoolExecutor(2) as pool:
        results = list(pool.map(lambda client:client.call('rvAdjustBag',adjustment),(leader,other)))
    assert results[0] == results[1]
    assert int(leader.call('rvGetBags',{'project_id':project})[0]['balance_seconds']) == 0
    assert 'error' in API('review-member','synthetic-review').call('rvAdjustBag',adjustment,True)
    print('PASS F-010: missing bag rolls back; overage warning; concurrent approval/compensation/adjustment are unique, exact and reconciled across two PHP/PDO processes.')


if __name__ == '__main__':
    main()
