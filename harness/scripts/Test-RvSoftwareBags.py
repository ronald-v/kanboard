"""F-009: clients/bags through UI and API, scoped access, concurrent extensions."""
from concurrent.futures import ThreadPoolExecutor
import html
import re
import time
import urllib.error
import urllib.parse
from rv_http import API, BASE, login

PAGE = '?controller=RvBagController&action=show&plugin=RvSoftware'
SAVE = '?controller=RvBagController&action=save&plugin=RvSoftware'


def main():
    root = API()
    leader = root.call('getUserByName', {'username': 'review-leader'})
    member = root.call('getUserByName', {'username': 'review-member'})
    project = root.call('createProject', {'name': 'Synthetic bags ' + str(time.time_ns())})
    for user, role in ((leader, 'project-manager'), (member, 'project-member')):
        assert root.call('addProjectUser', {'project_id': project, 'user_id': int(user['id']), 'role': role})
    api = API('review-leader', 'synthetic-review')
    other = API('review-leader', 'synthetic-review', 'http://127.0.0.1:18007/')
    outsider = API('review-member', 'synthetic-review')
    assert 'error' in outsider.call('rvGetBags', {'project_id': project}, True)
    browser = login('review-leader', 'synthetic-review')
    page = browser.open(BASE + PAGE + '&project_id=' + str(project)).read().decode()
    token = html.unescape(re.search(r'name="csrf_token" value="([^"]+)"', page)[1])
    stamp = str(time.time_ns())
    data = {'operation': 'client', 'name': '<img src=x onerror=alert(1)>', 'project_id': project, 'operation_key': 'client-' + stamp}
    try:
        browser.open(BASE + SAVE, urllib.parse.urlencode(data).encode())
        raise AssertionError('Missing CSRF accepted')
    except urllib.error.HTTPError as error:
        assert error.code == 403
    page = browser.open(BASE + SAVE, urllib.parse.urlencode({**data, 'csrf_token': token}).encode()).read().decode()
    assert '&lt;img src=x onerror=alert(1)&gt;' in page and '<img src=x onerror=alert(1)>' not in page
    bag_params = {'project_id': project, 'name': 'Synthetic contract', 'seconds': 36000, 'operation_key': 'bag-' + stamp}
    bag = api.call('rvCreateBag', bag_params)
    assert api.call('rvCreateBag', bag_params) == bag
    extension = {'bag_id': bag, 'seconds': 7200, 'reason': 'Synthetic extension', 'operation_key': 'extend-' + stamp}
    with ThreadPoolExecutor(2) as pool:
        assert list(pool.map(lambda client: client.call('rvExtendBag', extension), (api, other))) == [bag, bag]
    assert len(api.call('rvGetBagEntries', {'bag_id': bag})) == 2
    assert int(api.call('rvGetBags', {'project_id': project})[0]['balance_seconds']) == 43200
    assert 'error' in outsider.call('rvGetBagEntries', {'bag_id': bag}, True)
    member_browser = login('review-member', 'synthetic-review')
    try:
        member_browser.open(BASE + PAGE + '&project_id=' + str(project))
        raise AssertionError('Member read balances by URL')
    except urllib.error.HTTPError as error:
        assert error.code == 403
    archive = {'bag_id': bag, 'reason': 'Synthetic closure', 'operation_key': 'archive-' + stamp}
    assert api.call('rvArchiveBag', archive) == bag
    assert 'error' in api.call('rvExtendBag', {**extension, 'operation_key': 'after-' + stamp}, True)
    assert len(api.call('rvGetBagEntries', {'bag_id': bag})) == 2
    print('PASS F-009: client UI CSRF/escape, project scope UI/API, fixed bag, two-process extension retry, balance, archive/history.')


if __name__ == '__main__':
    main()
