"""Exercise profiling/observation tools on synthetic data and intercepted HTML only."""
from pathlib import Path
import asyncio
import importlib.util
import json
import os
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f019'
spec = importlib.util.spec_from_file_location('environment', ROOT/'harness/scripts/F019-Environment.py')
env_module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(env_module)
app = env_module.sandbox('after')
target = app/'harness/scripts'
target.mkdir(parents=True,exist_ok=True)
for name in ('Measure-LocalDatabase.php','Profile-LocalRequest.php'):
    shutil.copy2(ROOT/'harness/scripts'/name,target/name)
env = env_module.environment('after')
preflight = subprocess.run(env_module.php_command()+[str(target/'Measure-LocalDatabase.php')], env=env,capture_output=True,text=True,check=True)
assert json.loads(preflight.stdout)['application_boot_allowed'] is True
bad_env = dict(env,DB_DRIVER='sqlite')
blocked = subprocess.run(env_module.php_command()+[str(target/'Measure-LocalDatabase.php')],env=bad_env,capture_output=True,text=True)
assert blocked.returncode == 2 and 'details withheld' in blocked.stderr

log_path = WORK/('tool-profile-'+str(time.time_ns())+'.jsonl')
env.update(PROJECTA_LOCAL_PROFILE_FILE=str(log_path),PROJECTA_PROFILE_APP_ROOT=str(app),PROJECTA_PROFILE_VARIANT='Current')
with (WORK/'tool-server.log').open('wb') as stream:
    process = subprocess.Popen(env_module.php_command()+['-S','127.0.0.1:18111','-t',str(app),str(target/'Profile-LocalRequest.php')],env=env,stdout=stream,stderr=stream)
    try:
        for attempt in range(50):
            try:
                with socket.create_connection(('127.0.0.1',18111),timeout=.1): break
            except OSError:
                time.sleep(.1)
        else: raise RuntimeError('Synthetic profiler did not start')
        with urllib.request.urlopen('http://127.0.0.1:18111/') as response:
            response.read()
            assert response.status == 200 and response.headers['X-Projecta-Profile-Variant'] == 'Current'
            assert 'Server-Timing' in response.headers
        for path in ('/data/config.php','/.git/config','/harness/scripts/Measure-LocalDatabase.php','/.runtime/f019/fixture.json','/assets/../data/config.php'):
            try:
                urllib.request.urlopen('http://127.0.0.1:18111'+path)
                raise AssertionError('Private path was served')
            except urllib.error.HTTPError as error:
                assert error.code == 404
    finally:
        process.terminate()
        process.wait(timeout=10)
rows = [json.loads(line) for line in log_path.read_text().splitlines()]
allowed = {'at','variant','operation','status','php_ms','bootstrap_ms','pico_queries'}
assert rows and all(set(row)==allowed and row['pico_queries']>=0 for row in rows)
assert all(row['at'].endswith('-05:00') for row in rows)

sys.path.insert(0,str(WORK/'python'))
os.environ['PLAYWRIGHT_BROWSERS_PATH'] = str(WORK/'browsers')
from playwright.async_api import async_playwright


async def observer():
    records = []
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        context = await browser.new_context()
        await context.expose_binding('f019Record',lambda source,data: records.append(data))
        await context.add_init_script(path=str(ROOT/'harness/scripts/F019-Observe.js'))
        html = '''<html><body>
        <a id="next" href="/?controller=TaskViewController&action=show&task_id=98765">Go</a>
        <form action="/?controller=TaskModificationController&action=update&task_id=98765" method="post">
        <input name="title" value="synthetic-sensitive-marker"><button type="submit">Save</button></form>
        </body></html>'''
        await context.route('**/*',lambda route: route.fulfill(status=200,content_type='text/html',body=html))
        page = await context.new_page()
        await page.goto('http://127.0.0.1:18111/')
        async with page.expect_navigation(): await page.locator('#next').click()
        await page.wait_for_function('sessionStorage.getItem("f019-pending-click") === null')
        async with page.expect_navigation(): await page.locator('button[type=submit]').click()
        await page.wait_for_function('sessionStorage.getItem("f019-pending-click") === null')
        # Round trip through the binding before closing the context.
        await page.evaluate('async () => { await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))); }')
        await browser.close()
    assert len(records)==2 and [r['interaction'] for r in records]==['navigation','save']
    assert all(set(row)=={'operation','interaction','ms','success'} and row['success'] and row['ms']>0 for row in records)
    assert '98765' not in json.dumps(records) and 'synthetic-sensitive-marker' not in json.dumps(records)


asyncio.run(observer())
print('PASS synthetic preflight, refusal, profiler aggregate headers/private paths and passive browser redaction. No real application/data used.')
