"""Own only loopback synthetic MariaDB/Apache/CGI processes and run regression."""
from pathlib import Path
import argparse
import importlib.util
import json
import hashlib
import os
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT / '.runtime/f020'
APP = ROOT / '.runtime/projecta/app'
APACHE = WORK / 'apache/Apache24'
PHP = ROOT / '.runtime/compatibility/php85'
SCRIPTS = ROOT / 'harness/scripts'


def wait_port(port):
    for _ in range(100):
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.2): return
        except OSError: time.sleep(.1)
    raise RuntimeError('Synthetic service did not start on '+str(port))


def run(command, env, label):
    with (WORK/(label+'.log')).open('w', encoding='utf-8') as output:
        process = subprocess.Popen(command, env=env, cwd=ROOT, stdout=output, stderr=subprocess.STDOUT)
        try:
            code = process.wait(timeout=900)
        except subprocess.TimeoutExpired:
            # Only the child tree created above, never a discovered user process.
            subprocess.run(['taskkill', '/PID', str(process.pid), '/T', '/F'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            raise
    if code:
        raise RuntimeError(label+' failed; inspect its private synthetic log')
    print('PASS '+label, flush=True)


def rules():
    denied = ['data/config.php', 'config.php', '.git/config', '.env', 'harness/README.md',
              'app/common.php', 'app/Core/Http/ErrorHandler.php', 'vendor/autoload.php',
              'libs/picodb/lib/PicoDb/Database.php', 'mcp/bootstrap.php', 'mcp/src/Http.php',
              'mcp/vendor/autoload.php', 'mcp/composer.lock', 'config/projecta.php',
              'plugins/RvSoftware/Plugin.php', 'tests/units/Base.php', 'backup.sql',
              'backup.zip', 'error.log', 'cli', 'app/common.php/path-info',
              '%2egit/config', 'data%2fconfig.php', 'APP/common.php', 'config.php.bak']
    for name in denied:
        request = urllib.request.Request('http://127.0.0.1:18100/'+name)
        try: response = urllib.request.urlopen(request, timeout=15)
        except urllib.error.HTTPError as error: response = error
        assert response.status in (400, 403, 404), (name, response.status)
        assert 'F020_SECRET_FILE' not in response.read().decode(errors='replace')
    for name in ('assets/css/light.min.css', 'plugins/RvSoftware/Assets/projecta.css', '?controller=AuthController&action=login',
                 '.well-known/oauth-authorization-server', '.well-known/oauth-protected-resource'):
        response = urllib.request.urlopen('http://127.0.0.1:18100/'+name, timeout=30)
        assert response.status == 200, (name, response.status)
        assert '<?php' not in response.read().decode(errors='replace')
    print(f'PASS apache-rules ({len(denied)} denied, 5 allowed; real PHP execution)', flush=True)
    return len(denied)+5


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--browser-only', action='store_true', help='Development retest; never produces complete evidence.')
    parser.add_argument('--http-only', action='store_true', help='HTTP phase; requires separate matching MariaDB evidence to package.')
    args = parser.parse_args()
    assert (APACHE/'bin/httpd.exe').is_file(), 'Install the checksum-verified Apache test runtime first'
    subprocess.run([sys.executable, str(SCRIPTS/'Prepare-ProjectaSandbox.py')], cwd=ROOT, check=True)
    subprocess.run([sys.executable, str(SCRIPTS/'F019-Environment.py'), 'prepare', '--variant', 'after'], cwd=ROOT, check=True)
    assert not any((APP/p).exists() for p in ('config.php', 'data/config.php')), 'Private configuration forbidden'
    snapshot = {path.relative_to(APP).as_posix(): hashlib.sha256(path.read_bytes()).hexdigest()
                for base in ('app', 'plugins/RvSoftware', 'mcp', 'config') for path in (APP/base).rglob('*') if path.is_file()}
    for name in ('index.php', 'jsonrpc.php', '.htaccess', '.user.ini', 'web.config'):
        snapshot[name] = hashlib.sha256((APP/name).read_bytes()).hexdigest()
    for port in (13318, 18100, 18116):
        with socket.socket() as probe:
            assert probe.connect_ex(('127.0.0.1', port)) != 0, 'Reserved port is already owned: '+str(port)
    spec = importlib.util.spec_from_file_location('f019env', SCRIPTS/'F019-Environment.py')
    helper = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(helper)
    env = helper.environment('after')
    env.update(PLUGINS_DIR=str(APP/'plugins'), DATA_DIR=str(ROOT/'.runtime/projecta/data'),
               FILES_DIR=str(ROOT/'.runtime/projecta/data/files'), CACHE_DIR=str(ROOT/'.runtime/projecta/data/cache'),
               PROJECTA_PRIVATE_DIR=str(ROOT/'.runtime/projecta/private'), PROJECTA_BASE_URL='http://127.0.0.1:18100',
               PROJECTA_OAUTH_REDIRECT_URIS='["http://127.0.0.1:18119/callback"]')
    php = helper.php_command()
    conf = WORK/'httpd.conf'
    modules = ['version', 'authz_core', 'authz_host', 'authz_user', 'authn_core', 'access_compat',
               'rewrite', 'env', 'setenvif', 'mime', 'dir', 'log_config', 'proxy', 'proxy_fcgi']
    config = f'ServerRoot "{APACHE.as_posix()}"\nListen 127.0.0.1:18100\nServerName 127.0.0.1\n'
    config += '\n'.join(f'LoadModule {m}_module modules/mod_{m}.so' for m in modules)+'\n'
    config += f'''PidFile "{(WORK/'httpd.pid').as_posix()}"
ErrorLog "{(WORK/'apache-error.log').as_posix()}"
LogLevel warn
ServerTokens Prod
ServerSignature Off
AcceptFilter http none
TypesConfig conf/mime.types
DocumentRoot "{APP.as_posix()}"
DirectoryIndex index.php
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "{APP.as_posix()}">
    AllowOverride All
    Options -Indexes +FollowSymLinks
    Require local
</Directory>
<FilesMatch "\\.php$">
    SetHandler "proxy:fcgi://127.0.0.1:18116/"
</FilesMatch>
ProxyFCGIBackendType GENERIC
ProxyFCGISetEnvIf "true" SCRIPT_FILENAME "%{{reqenv:DOCUMENT_ROOT}}%{{reqenv:SCRIPT_NAME}}"
'''
    conf.write_text(config)
    run([str(APACHE/'bin/httpd.exe'), '-t', '-f', str(conf)], env, 'apache-syntax')
    binary = ROOT/'.runtime/compatibility/mariadb1189/mariadb-11.8.9-winx64'
    commands = [([str(binary/'bin/mariadbd.exe'), '--no-defaults', '--basedir='+str(binary),
                  '--datadir='+str(ROOT/'.runtime/compatibility/maria-data'), '--bind-address=127.0.0.1',
                  '--port=13318', '--skip-name-resolve', '--log-bin=compatibility-bin', '--server-id=1189',
                  '--innodb-flush-log-at-trx-commit=2', '--sync-binlog=0',
                  '--binlog-format=ROW', '--console'], 13318, 'maria'),
                ([str(PHP/'php-cgi.exe')]+php[1:]+['-d', 'display_errors=0', '-d', 'display_startup_errors=0',
                  '-d', 'memory_limit=64M',
                  '-d', 'error_log='+str(WORK/'http-errors.log'), '-b', '127.0.0.1:18116'], 18116, 'cgi'),
                ([str(APACHE/'bin/httpd.exe'), '-f', str(conf)], 18100, 'apache')]
    processes = []
    handles = []
    try:
        for command, port, name in commands:
            output = (WORK/(name+'-process.log')).open('w')
            handles.append(output)
            process = subprocess.Popen(command, env=env, cwd=ROOT, stdout=output, stderr=output)
            processes.append(process)
            wait_port(port)
        run(php+[str(SCRIPTS/'F019-Fixture.php')], dict(env, F019_GUARD_ONLY='true'), 'synthetic-db-identity')
        if args.browser_only:
            rules()
            run([sys.executable, str(SCRIPTS/'F020-Browser.py')], env, 'manual-board-browser')
            return
        for fixture, test in [('Projecta-SecurityFixture.php', 'Test-ProjectaSecurity.py'),
                              ('Projecta-ApiFixture.php', 'Test-ProjectaApi.py'),
                              ('Projecta-UiFixture.php', 'Test-ProjectaViews.py'),
                              ('Projecta-McpFixture.php', 'Test-ProjectaMcp.py')]:
            run(php+[str(SCRIPTS/fixture)], env, fixture[:-4])
            run([sys.executable, str(SCRIPTS/test)], env, test[:-3])
        run([sys.executable, str(SCRIPTS/'Test-ProjectaMcpNegative.py')], env, 'mcp-negative')
        run(php+[str(SCRIPTS/'F020-FileFixture.php')], env, 'private-file-fixture')
        checks = rules()
        run([sys.executable, str(SCRIPTS/'F020-Browser.py')], env, 'manual-board-browser')
        if not args.http_only:
            run([sys.executable, str(SCRIPTS/'F019-Environment.py'), 'test', '--variant', 'after', '--filter',
                 'RvSoftware|SchemaHandlerTest'], env, 'mariadb-regression')
            save_mariadb(snapshot)
        result = {'passed': True, 'server': 'Apache 2.4.71 / PHP FastCGI 8.5.12', 'http_rule_checks': checks, 'testedFiles': snapshot,
                  'suites': ['mfa', 'api', 'views', 'mcp', 'mcp-negative', 'browser'], 'mariadb_in_this_run': not args.http_only}
        (WORK/'integration-result.json').write_text(json.dumps(result, indent=2)+'\n')
    finally:
        # Graceful Apache shutdown also closes its child; never kill unrelated listeners.
        if processes and len(processes) == 3:
            subprocess.run([str(APACHE/'bin/httpd.exe'), '-k', 'shutdown', '-f', str(conf)],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=15)
        for process in reversed(processes):
            if process.poll() is None:
                process.terminate()
                process.wait(timeout=15)
        for output in handles: output.close()


def save_mariadb(snapshot):
    # The model/schema suite does not exercise board templates; HTTP covers them.
    subjects = {name: digest for name, digest in snapshot.items() if '/Template/' not in name}
    for path in (APP/'tests').rglob('*.php'):
        subjects[path.relative_to(APP).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    log = (WORK/'mariadb-regression.log').read_text(encoding='utf-8')
    import re
    match = re.search(r'OK \((\d+) tests, (\d+) assertions\)', log)
    if not match: raise ValueError('No passing MariaDB completion summary')
    (WORK/'mariadb-result.json').write_text(json.dumps({'passed': True, 'summary': match[0], 'testedFiles': subjects})+'\n')


if __name__ == '__main__':
    main()
