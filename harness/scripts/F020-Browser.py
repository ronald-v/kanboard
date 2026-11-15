"""Real Chromium checks of manual board refresh on the synthetic Apache app."""
from pathlib import Path
import asyncio
import json
import os
import sys
import urllib.parse

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT/'.runtime/f019/python'))
os.environ['PLAYWRIGHT_BROWSERS_PATH'] = str(ROOT/'.runtime/f019/browsers')
from playwright.async_api import async_playwright
from projecta_http import authenticated, BASE


async def main():
    session = authenticated()
    jar = next(h.cookiejar for h in session.handlers if hasattr(h, 'cookiejar'))
    cookies = [{'name': c.name, 'value': c.value, 'domain': c.domain, 'path': c.path} for c in jar]
    fixture = json.loads((ROOT/'.runtime/projecta/ui-fixture.json').read_text())
    api = json.loads((ROOT/'.runtime/projecta/api-fixture.json').read_text())
    file_url = BASE+'?'+urllib.parse.urlencode({'controller': 'FileViewerController', 'action': 'download',
        'project_id': api['project_id'], 'file_id': api['f020_file_id']})
    response = session.open(file_url, timeout=15)
    assert response.headers['Cache-Control'] == 'private, no-store'
    assert response.read() == b'F020 synthetic authorized attachment'
    large_url = file_url.replace('file_id='+str(api['f020_file_id']), 'file_id='+str(api['f020_large_id']))
    response = session.open(large_url, timeout=30)
    assert response.headers['Cache-Control'] == 'private, no-store'
    total = 0
    while chunk := response.read(65536): total += len(chunk)
    assert total == 80*1024*1024, 'Large attachment truncated under the 64M PHP limit'
    route = '?'+urllib.parse.urlencode({'controller': 'BoardViewController', 'action': 'show',
                                        'project_id': fixture['project_id'], 'search': 'Synthetic UI task'})
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        context = await browser.new_context()
        await context.add_cookies(cookies)
        page = await context.new_page()
        checks = []
        page.on('request', lambda request: checks.append(request.url) if 'controller=BoardAjaxController' in request.url and 'action=check' in request.url else None)
        await page.clock.install()
        response = await page.goto(BASE+route)
        assert response.status == 200
        assert await page.locator('#board').get_attribute('data-check-interval') == '0'
        assert await page.locator('meta[http-equiv="refresh"]').count() == 0
        await page.clock.fast_forward(120000)
        assert not checks, 'Idle board generated periodic checks'
        before = await page.locator('#board .board-task-list').count()
        assert before > 1
        await page.get_by_role('link', name='Actualizar', exact=True).click()
        await page.wait_for_load_state('networkidle')
        assert await page.locator('#board').get_attribute('data-check-interval') == '0'
        assert await page.locator('input[name="search"]').input_value() == 'Synthetic UI task'
        # JQuery sortable uses pointer movement; verify the actual save response.
        task = page.locator('div.draggable-item[data-task-id="'+str(fixture['task_id'])+'"]')
        source = await task.bounding_box()
        lane = await task.get_attribute('data-swimlane-id')
        target = await page.locator('.board-task-list:visible[data-swimlane-id="'+lane+'"]').nth(1).bounding_box()
        if not source or not target:
            await page.screenshot(path=str(ROOT/'.runtime/f020/board-debug.png'), full_page=True)
            raise AssertionError('Missing visible drag geometry: '+str({'source': source, 'target': target}))
        async with page.expect_response(lambda response: 'controller=BoardAjaxController' in response.url and 'action=save' in response.url) as saved:
            await page.mouse.move(source['x']+source['width']/2, source['y']+20)
            await page.mouse.down()
            await page.mouse.move(target['x']+target['width']/2, target['y']+30, steps=20)
            await page.mouse.up()
        assert (await saved.value).status == 201
        await page.wait_for_load_state('networkidle')
        assert await page.get_by_role('link', name='Actualizar', exact=True).count() == 1
        assert await page.locator('#board').get_attribute('data-check-interval') == '0'
        await page.goto(BASE+'?controller=ConfigController&action=board')
        assert await page.locator('input[name="board_private_refresh_interval"],input[name="board_public_refresh_interval"]').count() == 0
        assert 'manualmente' in await page.locator('body').inner_text()
        await browser.close()
    (ROOT/'.runtime/f020/browser-result.json').write_text(json.dumps({'passed': True, 'idle_simulated_seconds': 120,
        'periodic_board_requests': len(checks), 'manual_refresh': True, 'filter_preserved': True, 'drag_save_status': 201,
        'streamed_attachment_bytes': total, 'php_memory_limit_mb': 64})+'\n')
    print('PASS Chromium: no board polling, manual refresh preserves filter, drag saves and settings explain policy.')


if __name__ == '__main__':
    asyncio.run(main())
