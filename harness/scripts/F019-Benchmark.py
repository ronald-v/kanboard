"""Real web forms + redirects, one fixed synthetic PHP server (not an API benchmark)."""
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from http.cookiejar import CookieJar
from pathlib import Path
import argparse
import json
import math
import re
import statistics
import time
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f019'
BASE = 'http://127.0.0.1:18110/'


class Form(HTMLParser):
    def __init__(self, body):
        super().__init__(convert_charrefs=True)
        self.fields = {}
        self.action = None
        self.in_form = False
        self.forms = []
        self.current = None
        self.select = None
        self.textarea = None
        self.feed(body)
        candidates = [f for f in self.forms if 'csrf_token' in f['fields']]
        if candidates:
            self.action, self.fields = candidates[0]['action'], candidates[0]['fields']

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'form':
            self.current = {'action':attrs.get('action',''), 'fields':{}}
            self.in_form = True
        if self.in_form and tag == 'input' and attrs.get('name'):
            if attrs.get('type') not in ('checkbox', 'radio', 'submit', 'button', 'file') or 'checked' in attrs:
                self.current['fields'][attrs['name']] = attrs.get('value', '')
        if self.in_form and tag == 'select':
            self.select = attrs.get('name')
        if self.in_form and tag == 'option' and self.select:
            if self.select not in self.current['fields'] or 'selected' in attrs:
                self.current['fields'][self.select] = attrs.get('value','')
        if self.in_form and tag == 'textarea':
            self.textarea = attrs.get('name')
            if self.textarea:
                self.current['fields'][self.textarea] = ''

    def handle_data(self, data):
        if self.in_form and self.textarea:
            self.current['fields'][self.textarea] += data

    def handle_endtag(self, tag):
        if tag == 'form':
            if self.current:
                self.forms.append(self.current)
            self.in_form = False
            self.current = None
        if tag == 'select':
            self.select = None
        if tag == 'textarea':
            self.textarea = None


class Client:
    def __init__(self, fixture):
        self.fixture = fixture
        self.samples = []
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))
        form = Form(self.get(route('AuthController', 'login')))
        challenge = self.post(route('AuthController', 'check'), dict(form.fields, username=fixture['username'], password=fixture['password']))
        form = Form(challenge)
        home = self.post(route('RvAccessController', 'verify', plugin='RvSoftware'), dict(form.fields, code=fixture['recovery']))
        assert 'rv-sidebar' in home, 'MFA login did not finish'

    def request(self, url, values=None):
        if url.startswith('/'):
            url = BASE.rstrip('/')+url
        elif not url.startswith('http'):
            url = BASE+url
        assert urllib.parse.urlsplit(url).netloc == '127.0.0.1:18110', 'Unexpected redirect/form target'
        data = urllib.parse.urlencode(values).encode() if values is not None else None
        start = time.perf_counter()
        response = self.opener.open(urllib.request.Request(url, data), timeout=60)
        body = response.read().decode()
        self.last_url = response.url
        elapsed = (time.perf_counter()-start)*1000
        assert response.status == 200 and not any(x in body for x in ('Fatal error', 'Internal Error:', 'Warning:')), 'HTTP/PHP failure'
        self.samples.append(dict(ms=elapsed, db_requests=int(response.headers.get('X-F019-Db-Requests', -1)),
                                 pico_queries=int(response.headers.get('X-F019-Pico-Queries', -1)),
                                 db_writes=int(response.headers.get('X-F019-Db-Writes', -1))))
        return body

    def get(self, path):
        return self.request(path)

    def post(self, path, fields):
        return self.request(path, fields)


def route(controller, action, **params):
    return '?'+urllib.parse.urlencode(dict(controller=controller, action=action, **params))


def flows(f):
    p, t, s, u = (f[key] for key in ('project_id', 'task_id', 'subtask_id', 'user_id'))
    return {
        'home': route('RvHomeController','show',plugin='RvSoftware'),
        'projects': route('RvHomeController','projects',plugin='RvSoftware'),
        'time': route('RvTimeController','show',plugin='RvSoftware'),
        'bags': route('RvBagController','show',plugin='RvSoftware',project_id=p),
        'reports': route('RvReportController','show',plugin='RvSoftware'),
        'dashboard': route('DashboardController','show'),
        'board': route('BoardViewController','show',project_id=p),
        'task': route('TaskViewController','show',task_id=t),
        'users': route('UserListController','show'),
        'administration': route('ConfigController','application'),
        'project_create_form': route('ProjectCreationController','create'),
        'project_edit_form': route('ProjectEditController','show',project_id=p),
        'task_create_form': route('TaskCreationController','show',project_id=p),
        'task_edit_form': route('TaskModificationController','edit',task_id=t),
        'subtask_create_form': route('SubtaskController','create',task_id=t),
        'subtask_edit_form': route('SubtaskController','edit',task_id=t,subtask_id=s),
        'user_create_form': route('UserCreationController','show'),
        'user_edit_form': route('UserModificationController','show',user_id=u),
    }


def write(client, kind, iteration):
    f = client.fixture
    p, t, s, u = (f[key] for key in ('project_id','task_id','subtask_id','user_id'))
    nonce = f['username']+'-'+str(iteration)
    definitions = {
        'create_project': ('project_create_form', {'name':'F019 '+nonce}, None),
        'edit_project': ('project_edit_form', {'name':'F019 fixture '+f['username'].split('-')[-1], 'identifier':'f019'+str(u)+'x'+str(iteration), 'owner_id':u, 'priority_default':0, 'priority_start':0, 'priority_end':3}, 'ProjectViewController'),
        'create_task': ('task_create_form', {'title':'F019 '+nonce,'project_id':p}, None),
        'edit_task': ('task_edit_form', {'title':'F019 fixture task','description':'Synthetic revision '+str(iteration)}, 'TaskViewController'),
        'create_subtask': ('subtask_create_form', {'title':'F019 '+nonce,'task_id':t,'user_id':u}, 'TaskViewController'),
        'edit_subtask': ('subtask_edit_form', {'title':'F019 fixture subtask','user_id':u,'status':0,'time_estimated':0}, 'TaskViewController'),
        'create_user': ('user_create_form', {'username':'f019-write-'+nonce,'password':'synthetic-password','confirmation':'synthetic-password','role':'app-user'}, None),
        'edit_user': ('user_edit_form', {'id':u,'username':f['username'],'name':'F019 revision '+str(iteration),'role':'app-admin'}, 'UserViewController'),
    }
    form_name, changes, target = definitions[kind]
    form = Form(client.get(flows(f)[form_name]))
    assert form.action and 'csrf_token' in form.fields, 'Missing native form/CSRF: '+kind
    values = dict(form.fields, **changes)
    if kind == 'create_task':
        values.update(column_id=f['column_id'],swimlane_id=f['swimlane_id'],owner_id=u)
    body = client.post(form.action, values)
    assert 'class="form-errors"' not in body and 'alert-error' not in body, 'Native form rejected '+kind
    assert urllib.parse.parse_qs(urllib.parse.urlsplit(client.last_url).query).get('action') not in (['save'],['update']), 'Native form did not redirect: '+kind
    return body


def worker(fixture, samples):
    client = Client(fixture)
    results = {}
    operations = list(flows(fixture))+['create_project','edit_project','create_task','edit_task','create_subtask','edit_subtask','create_user','edit_user']
    for name in operations:
        values = []
        for iteration in range(samples+2):
            start = time.perf_counter()
            client.samples = []
            if name in flows(fixture):
                body = client.get(flows(fixture)[name])
            else:
                body = write(client, name, iteration)
            elapsed = (time.perf_counter()-start)*1000
            if iteration >= 2:
                values.append(dict(ms=elapsed, responses=client.samples))
        results[name] = values
        if fixture['username'].endswith('-0'):
            print('Measured '+name,flush=True)
    return results


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--label', required=True)
    parser.add_argument('--clients', type=int, choices=(1,10), default=1)
    parser.add_argument('--samples', type=int, default=100)
    parser.add_argument('--diagnostic', action='store_true')
    args = parser.parse_args()
    fixture = json.loads((WORK/'fixture.json').read_text())
    if args.diagnostic:
        client = Client(fixture[0])
        for name, path in flows(fixture[0]).items():
            client.samples = []
            client.get(path)
            print(name, json.dumps(client.samples[-1]), flush=True)
        return
    per_client = math.ceil(args.samples/args.clients)
    with ThreadPoolExecutor(max_workers=args.clients) as pool:
        results = list(pool.map(lambda f: worker(f, per_client), fixture[:args.clients]))
    output = {'clients':args.clients, 'server_processes':1, 'measures':'HTTP form open + save + followed redirects; excludes browser render', 'flows':{}}
    for name in results[0]:
        rows = [row for result in results for row in result[name]]
        times = sorted(row['ms'] for row in rows)
        output['flows'][name] = {'samples':len(rows),'median_ms':round(statistics.median(times),2), 'p95_ms':round(times[math.ceil(.95*len(times))-1],2),
                                 'final_response_db_requests':round(statistics.median(row['responses'][-1]['db_requests'] for row in rows),2)}
    (WORK/(args.label+'.json')).write_text(json.dumps(output,indent=2))
    print(json.dumps(output,indent=2),flush=True)


if __name__ == '__main__':
    main()
