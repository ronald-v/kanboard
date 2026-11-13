"""F-008: real HTTP/API role boundaries and native time projections."""
import html
import re
import time
import urllib.error
import urllib.parse
from rv_http import API, BASE, login, query

PAGE = '?controller=RvReviewController&action=show&plugin=RvSoftware'
SAVE = '?controller=RvReviewController&action=save&plugin=RvSoftware'


def main():
    root = API()
    users = {}
    for name in ('review-member', 'review-peer', 'review-leader'):
        user = root.call('getUserByName', {'username': name})
        users[name] = int(user['id']) if user else root.call('createUser', {'username': name, 'password': 'synthetic-review'})
    project = root.call('createProject', {'name': 'Synthetic review ' + str(time.time_ns())})
    for name, user in users.items():
        assert root.call('addProjectUser', {'project_id': project, 'user_id': user, 'role': 'project-manager' if name.endswith('leader') else 'project-member'})
    task = root.call('createTask', {'title': 'Review task', 'project_id': project})
    subtask = root.call('createSubtask', {'title': 'Review source', 'task_id': task})
    member, peer, leader = [API(name, 'synthetic-review') for name in users]
    end = int(time.time()) - 60
    first, second = [client.call('rvCreateTime', {'subtask_id': subtask, 'start': end - seconds, 'end': end, 'note': name, 'operation_key': 'review-' + str(time.time_ns())}) for client, seconds, name in ((member, 3600, 'Own note'), (peer, 1800, 'Hidden peer note'))]
    rows = member.call('rvGetVisibleTime')
    assert first in [int(r['id']) for r in rows] and second not in [int(r['id']) for r in rows]
    assert 'error' in member.call('rvGetTime', {'session_id': second}, True)
    assert 'error' in member.call('rvGetTimeAudit', {'session_id': second}, True)
    leader.call('rvAssignClient', {'project_id': project, 'name': 'Review client', 'operation_key': 'review-client'})
    bag = leader.call('rvCreateBag', {'project_id': project, 'name': 'Review bag', 'seconds': 36000, 'operation_key': 'review-bag'})
    review = {'bag_id': bag, 'session_id': first, 'billable': True, 'approve': True, 'reason': 'Synthetic approval', 'operation_key': 'approve-' + str(time.time_ns())}
    assert 'error' in member.call('rvReviewTime', review, True)
    assert leader.call('rvReviewTime', review) == first
    assert leader.call('rvReviewTime', review) == first
    assert 'error' in member.call('rvCorrectTime', {'session_id': first, 'start': end-60, 'end': end, 'note': '', 'reason': 'Denied', 'operation_key': 'denied-correction'}, True)
    for client, expected in ((member, 1), (peer, .5), (leader, 1.5)):
        assert float(client.call('getTask', {'task_id': task})['time_spent']) == expected
        assert float(client.call('getAllTasks', {'project_id': project, 'status_id': 1})[0]['time_spent']) == expected
        assert float(client.call('getAllSubtasks', {'task_id': task})[0]['time_spent']) == expected
    browser = login('review-member', 'synthetic-review')
    body = browser.open(BASE + PAGE).read().decode()
    assert 'Hidden peer note' not in body and 'Own note' in body
    try:
        browser.open(BASE + '?controller=ExportController&action=subtasks&project_id=' + str(project))
        raise AssertionError('Member could export team time')
    except urllib.error.HTTPError as error:
        assert error.code == 403
    admin = login()
    body = admin.open(BASE + PAGE).read().decode()
    token = html.unescape(re.search(r'name="csrf_token" value="([^"]+)"', body)[1])
    data = {'session_id': second, 'billable': 0, 'operation': 'approve', 'reason': 'UI approval', 'operation_key': 'ui-review-' + str(time.time_ns())}
    try:
        admin.open(BASE + SAVE, urllib.parse.urlencode(data).encode())
        raise AssertionError('Missing CSRF accepted')
    except urllib.error.HTTPError as error:
        assert error.code == 403
    assert admin.open(BASE + SAVE, urllib.parse.urlencode({**data, 'csrf_token': token}).encode()).status == 200
    assert query('SELECT status,billable FROM rv_time_meta WHERE session_id=?', (second,))[0] == {'status': 'approved', 'billable': 0}
    correction = {'session_id': first, 'start': end-2400, 'end': end, 'billable': False, 'note': 'Rectified', 'reason': 'Synthetic correction', 'operation_key': 'rectify-' + str(time.time_ns())}
    assert leader.call('rvRectifyTime', correction) == first
    assert leader.call('rvRectifyTime', correction) == first
    assert len(leader.call('rvGetTimeAudit', {'session_id': first})) == 3
    assert root.call('removeProjectUser', {'project_id': project, 'user_id': users['review-leader']})
    assert 'error' in leader.call('rvGetTime', {'session_id': first}, True)
    assert 'error' in leader.call('rvReviewTime', {**review, 'session_id': second, 'operation_key': 'foreign-review'}, True)
    print('PASS F-008: own/team/admin scope, native API projections, approval, rectification, audit, retries, revoked access, UI CSRF and member export denial.')


if __name__ == '__main__':
    main()
