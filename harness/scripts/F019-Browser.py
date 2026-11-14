"""Isolated Chromium: document navigation/paint and actual native form submissions."""
from pathlib import Path
import argparse
import asyncio
import importlib.util
import json
import math
import os
import statistics
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f019'
sys.path.insert(0, str(WORK/'python'))
os.environ['PLAYWRIGHT_BROWSERS_PATH'] = str(WORK/'browsers')
from playwright.async_api import async_playwright

spec = importlib.util.spec_from_file_location('f019_http', ROOT/'harness/scripts/F019-Benchmark.py')
http = importlib.util.module_from_spec(spec)
spec.loader.exec_module(http)


async def ready(page):
    await page.wait_for_load_state('load')
    await page.evaluate('async () => { await document.fonts.ready; await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))); }')
    assert not await page.locator('.form-errors:visible,.alert-error:visible').count(), 'Native form rejected the operation'
    assert not await page.get_by_text('Internal Error:', exact=False).count(), 'Application failure'


async def navigate(page, path):
    # An ordinary link click exercises document loading, assets, JS and paint.
    # This harness anchor is not a claim that native modal/AJAX paths were timed.
    await page.evaluate('url => { const old=document.getElementById("f019-measure"); if(old) old.remove(); const a=document.createElement("a"); a.id="f019-measure"; a.href=url; a.textContent="Synthetic navigation"; a.style="position:fixed;top:0;left:0;z-index:2147483647;background:white"; document.body.append(a); }', http.BASE+path)
    start = time.perf_counter()
    async with page.expect_navigation(wait_until='load'):
        await page.locator('#f019-measure').click()
    await ready(page)
    return (time.perf_counter()-start)*1000


async def open_form(page, path):
    if await page.locator('#modal-close-button').count():
        await page.locator('#modal-close-button').click()
    await page.evaluate('url => { const old=document.getElementById("f019-modal"); if(old) old.remove(); const a=document.createElement("a"); a.id="f019-modal"; a.className="js-modal-large"; a.href=url; a.textContent="Synthetic form"; a.style="position:fixed;top:0;left:0;z-index:2147483647;background:white"; document.body.append(a); }', http.BASE+path)
    start = time.perf_counter()
    await page.locator('#f019-modal').click()
    await page.locator('#modal-content form input[name=csrf_token]').wait_for(state='attached')
    await page.locator('#modal-content button[type=submit]').first.wait_for(state='visible')
    await ready(page)
    return (time.perf_counter()-start)*1000


async def worker(browser, fixture, samples, timeout_ms):
    context = await browser.new_context(viewport={'width':1440,'height':1000}, reduced_motion='reduce')
    context.set_default_timeout(timeout_ms)
    context.set_default_navigation_timeout(timeout_ms)
    # Do not leak the synthetic browser's traffic to external services.
    await context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(http.BASE) else route.abort())
    page = await context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    await page.goto(http.BASE+http.route('AuthController','login'))
    await page.locator('input[name=username]').fill(fixture['username'])
    await page.locator('input[name=password]').fill(fixture['password'])
    async with page.expect_navigation():
        await page.locator('button[type=submit]').click()
    await page.locator('input[name=code]').fill(fixture['recovery'])
    async with page.expect_navigation():
        await page.locator('button[type=submit]').click()
    await ready(page)
    assert await page.locator('.rv-sidebar').count(), 'Browser MFA login failed'
    output = {}
    for name, path in http.flows(fixture).items():
        values = []
        for iteration in range(samples+2):
            elapsed = await open_form(page, path) if name.endswith('_form') else await navigate(page, path)
            if iteration >= 2:
                values.append(elapsed)
        output[name] = values
        if fixture['username'].endswith('-0'):
            print('Browser measured '+name, flush=True)
    definitions = {
        'create_project': ('project_create_form', {'name':'F019 browser project'}),
        'edit_project': ('project_edit_form', {'name':'F019 browser edited project'}),
        'create_task': ('task_create_form', {'title':'F019 browser task'}),
        'edit_task': ('task_edit_form', {'title':'F019 browser edited task'}),
        'create_subtask': ('subtask_create_form', {'title':'F019 browser subtask'}),
        'edit_subtask': ('subtask_edit_form', {'title':'F019 browser edited subtask'}),
        'create_user': ('user_create_form', {'username':'','password':'synthetic-password','confirmation':'synthetic-password'}),
        'edit_user': ('user_edit_form', {'name':'F019 browser edited user'}),
    }
    for name, (form_name, fields) in definitions.items():
        values = []
        for iteration in range(samples+2):
            # Opening/filling is outside save-to-confirmation timing; form opening
            # is independently measured above.
            await open_form(page, http.flows(fixture)[form_name])
            for key, value in fields.items():
                if key == 'username':
                    value = 'f019-browser-'+fixture['username']+'-'+str(iteration)
                await page.locator('#modal-content [name="'+key+'"]').fill(value)
            start = time.perf_counter()
            async with page.expect_navigation(wait_until='load'):
                await page.locator('#modal-content button[type=submit]').first.click()
            await ready(page)
            assert 'action=save' not in page.url and 'action=update' not in page.url, 'Save did not redirect'
            if iteration >= 2:
                values.append((time.perf_counter()-start)*1000)
        output[name] = values
        if fixture['username'].endswith('-0'):
            print('Browser measured '+name,flush=True)
    await context.close()
    assert not errors, 'Browser JS errors: '+str(len(errors))
    return output


async def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--label',required=True)
    parser.add_argument('--clients',type=int,choices=(1,10),default=1)
    parser.add_argument('--samples',type=int,default=100)
    parser.add_argument('--timeout-ms',type=int,default=30000,help='Measurement deadline only; does not change app timeouts or count as a speed improvement')
    args = parser.parse_args()
    fixtures = json.loads((WORK/'fixture.json').read_text())[:args.clients]
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        try:
            results = await asyncio.gather(*(worker(browser,f,math.ceil(args.samples/args.clients),args.timeout_ms) for f in fixtures))
        finally:
            await browser.close()
    summary = {'clients':args.clients, 'server_processes':1, 'deadline_ms':args.timeout_ms, 'browser':'Chromium', 'measures':'document navigation, native AJAX modal opening or native modal submit through ready controls/load, fonts and two animation frames', 'flows':{}}
    for name in results[0]:
        values = sorted(value for result in results for value in result[name])
        summary['flows'][name] = {'samples':len(values),'median_ms':round(statistics.median(values),2),'p95_ms':round(values[math.ceil(.95*len(values))-1],2)}
    (WORK/(args.label+'.json')).write_text(json.dumps(summary,indent=2))
    print(json.dumps(summary,indent=2),flush=True)


if __name__ == '__main__':
    asyncio.run(main())
