"""Fault injection into copied HTTP entrypoints; never loads private config or DB."""
from pathlib import Path
import json
import hashlib
import os
import re
import shutil
import socket
import subprocess
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT / '.runtime/f020/errors'
PHP = ROOT / '.runtime/compatibility/php85/php.exe'
PORT = 18114
SECRET = 'F020_PRIVATE_SENTINEL_password_SQL_cookie'


def request(path, method='GET', headers=None):
    req = urllib.request.Request(f'http://127.0.0.1:{PORT}/{path}',
                                 data=b'{}' if method == 'POST' else None,
                                 method=method, headers=headers or {})
    try:
        response = urllib.request.urlopen(req, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.headers, response.read().decode()


def main():
    WORK.mkdir(parents=True, exist_ok=True)
    for name in ('index.php', 'jsonrpc.php', 'mcp/index.php', 'mcp/oauth.php',
                 'mcp/bootstrap.php', 'mcp/src/Http.php', 'app/Core/Http/ErrorHandler.php'):
        target = WORK / name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(ROOT / name, target)
    # Test-only bootstrap: failure is chosen here, not through any product hook.
    (WORK/'app/common.php').write_text('''<?php
$kind = $_GET['fault'] ?? '';
define('DEBUG', getenv('DEBUG') === 'true');
if ($kind === 'warning') { trigger_error('F020_PRIVATE_SENTINEL_password_SQL_cookie', E_USER_WARNING); echo 'OK'; exit; }
if ($kind === 'warn-flood') { for ($i=0; $i<100; $i++) { trigger_error('F020_PRIVATE_SENTINEL_password_SQL_cookie', E_USER_WARNING); } echo 'OK'; exit; }
if ($kind === 'bad-sink') { ini_set('error_log', __DIR__); }
if ($kind === 'fatal') { require __DIR__.'/fatal.php'; }
if ($kind === 'parse') { require __DIR__.'/parse.php'; }
if ($kind === 'type') { strlen([]); }
if ($kind === 'userfatal') { trigger_error('F020_PRIVATE_SENTINEL_password_SQL_cookie', E_USER_ERROR); }
if ($kind === 'stream') { \\Kanboard\\Core\\Http\\ErrorHandler::stream(); header('Content-Type: text/plain'); echo 'STREAM'; flush(); }
if ($kind === 'quota' || $kind === 'connection') {
    $error = new PDOException('F020_PRIVATE_SENTINEL_password_SQL_cookie');
    $error->errorInfo = ['HY000', $kind === 'quota' ? 1226 : 2002, 'F020_PRIVATE_SENTINEL_password_SQL_cookie'];
    throw $error;
}
if ($kind === 'partial') { echo 'F020_PRIVATE_SENTINEL_password_SQL_cookie'; header('Status: 201'); header('ETag: "private"'); header('Location: /secret'); }
throw new RuntimeException('F020_PRIVATE_SENTINEL_password_SQL_cookie');
''', encoding='utf-8')
    (WORK/'app/fatal.php').write_text('<?php function duplicate_f020() {} function duplicate_f020() {}')
    (WORK/'app/parse.php').write_text('<?php syntax error F020_PRIVATE_SENTINEL_password_SQL_cookie')
    count = 0
    for debug in ('false', 'true'):
        log = WORK/f'{debug}.log'
        log.write_text('')
        env = os.environ.copy()
        env['DEBUG'] = debug
        # No application/database is bootstrapped in this fault fixture.
        command = [str(PHP), '-n', '-d', 'display_errors=1', '-d', 'display_startup_errors=1',
                   '-d', 'error_log='+str(log), '-S', f'127.0.0.1:{PORT}', '-t', str(WORK)]
        with (WORK/f'{debug}-server.log').open('w') as server_log:
            process = subprocess.Popen(command, env=env, stdout=server_log, stderr=server_log)
            try:
                for _ in range(50):
                    try:
                        with socket.create_connection(('127.0.0.1', PORT), timeout=.2): break
                    except OSError: time.sleep(.1)
                surfaces = {'index.php': 'web', 'jsonrpc.php': 'jsonrpc', 'mcp/index.php': 'mcp', 'mcp/oauth.php': 'oauth'}
                for entry, surface in surfaces.items():
                    for fault in ('quota', 'connection', 'exception', 'type', 'fatal', 'parse', 'userfatal', 'partial'):
                        status, headers, body = request(entry+'?fault='+fault, 'POST' if surface == 'jsonrpc' else 'GET')
                        assert status == (503 if fault in ('quota', 'connection') else 500), (entry, fault, status)
                        assert SECRET not in body and 'SQLSTATE' not in body and str(ROOT) not in body
                        assert headers['Cache-Control'] == 'no-store' and headers['X-Content-Type-Options'] == 'nosniff'
                        assert 'Location' not in headers and 'Status' not in headers and 'ETag' not in headers
                        assert re.fullmatch('[a-f0-9]{24}', headers['X-Request-ID'])
                        reference = headers['X-Request-ID']
                        assert reference in body
                        if surface != 'web':
                            payload = json.loads(body)
                            if surface != 'oauth': assert payload['error']['code'] == -32603 and payload['id'] is None
                        logged = log.read_text(encoding='utf-8')
                        assert sum('"reference":"'+reference+'"' in line and '"status":' in line for line in logged.splitlines()) == 1
                        assert SECRET not in logged
                        count += 1
                    status, _, body = request(entry+'?fault=warning', 'POST' if surface == 'jsonrpc' else 'GET')
                    assert status == 200 and body == 'OK'
                    count += 1
                before_lines = len(log.read_text(encoding='utf-8').splitlines())
                status, headers, body = request('index.php?fault=warn-flood')
                assert status == 200 and body == 'OK'
                log_lines = log.read_text(encoding='utf-8').splitlines()
                assert len(log_lines[before_lines:]) == 1 and '"class":"PhpDiagnostic"' in log_lines[-1]
                status, headers, body = request('index.php?fault=bad-sink')
                assert status == 500 and SECRET not in body
                count += 2
                status, headers, body = request('index.php?fault=quota', 'HEAD')
                assert status == 503 and not body
                status, _, body = request('mcp/index.php?fault=stream')
                assert status == 200 and body == 'STREAM'
                count += 2
            finally:
                process.terminate()
                process.wait(timeout=10)
        assert SECRET not in (WORK/f'{debug}-server.log').read_text(encoding='utf-8')
    result = {'passed': True, 'checks': count, 'debug_modes': 2, 'scope': 'copied-entrypoints-no-db',
              'testedFiles': {name: hashlib.sha256((WORK/name).read_bytes()).hexdigest() for name in
                ('index.php', 'jsonrpc.php', 'mcp/index.php', 'mcp/oauth.php', 'mcp/bootstrap.php', 'mcp/src/Http.php', 'app/Core/Http/ErrorHandler.php')}}
    (WORK/'result.json').write_text(json.dumps(result, indent=2)+'\n')
    print(f'PASS {count} HTTP fault checks; four real entrypoints, DEBUG on/off, private sanitized records.')


if __name__ == '__main__':
    main()
