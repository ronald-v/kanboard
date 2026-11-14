"""User-operated browser only: passive click-to-ready timings, no automated CRUD."""
from pathlib import Path
from datetime import datetime
from zoneinfo import ZoneInfo
import argparse
import asyncio
import json
import math
import os
import re
import statistics
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f019'
sys.path.insert(0, str(WORK/'python'))
os.environ['PLAYWRIGHT_BROWSERS_PATH'] = str(WORK/'browsers')
from playwright.async_api import async_playwright


async def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--variant', required=True, choices=('Before','Current'))
    args = parser.parse_args()
    directory = Path(tempfile.gettempdir())/'Projecta-F019'
    directory.mkdir(exist_ok=True)
    output = directory/(args.variant+'-browser-'+str(time.time_ns())+'.jsonl')
    rows = []
    navigation_status = {}

    def watch(page):
        def response_seen(response):
            if response.request.is_navigation_request() and response.frame == page.main_frame:
                navigation_status[page] = response.status
        page.on('response',response_seen)

    def record(source, data):
        if not isinstance(data, dict) or not re.fullmatch(r'[A-Za-z0-9_]+Controller/[A-Za-z0-9_]+', data.get('operation','')):
            return
        if data.get('interaction') not in ('navigation','form_open','save') or not isinstance(data.get('ms'), (int,float)) or not 0 < data['ms'] < 600000:
            return
        row = {'at':datetime.now(ZoneInfo('America/Bogota')).isoformat(timespec='seconds'), 'variant':args.variant,
               'operation':data['operation'], 'interaction':data['interaction'], 'ms':round(data['ms'],2),
               'success':data.get('success') is True and navigation_status.get(source['page'],0)<400}
        rows.append(row)
        with output.open('a', encoding='utf-8') as stream:
            stream.write(json.dumps(row)+'\n')

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=False)
        context = await browser.new_context()
        context.on('page',watch)
        await context.expose_binding('f019Record', record)
        await context.add_init_script(path=str(ROOT/'harness/scripts/F019-Observe.js'))
        page = await context.new_page()
        # Verify the profiler variant before asking the user to perform any work.
        response = await page.goto('http://127.0.0.1:18002/')
        if not response or 'server-timing' not in response.headers or response.headers.get('x-projecta-profile-variant') != args.variant:
            await browser.close()
            raise SystemExit('Start the opt-in profiler on 18002 first. No automated interaction performed.')
        print('Use the application normally, then close this browser. No clicks, logins or CRUD will be automated.',flush=True)
        print('Aggregate metrics only: '+str(output),flush=True)
        await context.wait_for_event('close', timeout=0)
        await browser.close()
    groups = {}
    for row in rows:
        groups.setdefault(row['operation']+' '+row['interaction'], []).append(row)
    summary = {}
    for name, samples in groups.items():
        values = sorted(row['ms'] for row in samples)
        summary[name] = {'samples':len(values), 'errors':sum(not row['success'] for row in samples),
                         'median_ms':statistics.median(values), 'p95_ms':values[math.ceil(.95*len(values))-1] if len(values)>=100 else None}
    output.with_suffix('.summary.json').write_text(json.dumps(summary,indent=2),encoding='utf-8')
    print('Observation finished. p95 remains null below 100 samples per operation.')


if __name__ == '__main__':
    asyncio.run(main())
