"""Dedicated synthetic copy, process environment and tests. Never loads private config."""
from pathlib import Path
import argparse
import json
import os
import shutil
import subprocess
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT / '.runtime/f019'
PHP = ROOT / '.runtime/compatibility/php85/php.exe'
SCRIPTS = ROOT / 'harness/scripts'


def sandbox(variant):
    assert variant in ('before', 'after')
    return WORK / variant / 'app'


def environment(variant, debug=False):
    app = sandbox(variant)
    assert not any((app / name).exists() for name in ('config.php', 'data/config.php'))
    env = os.environ.copy()
    for key in list(env):
        if key.startswith(('DB_', 'PROJECTA_', 'MAIL_', 'SMTP_')) or key == 'DATABASE_URL':
            env.pop(key)
    data = WORK / 'data'
    for path in (data, data/'files', data/'cache', WORK/'private'):
        path.mkdir(parents=True, exist_ok=True)
    env.update(DB_DRIVER='mysql', DB_HOSTNAME='127.0.0.1', DB_PORT='13318',
               DB_NAME='kanboard_compat_http', DB_USERNAME='compat_synthetic',
               DB_PASSWORD='synthetic-local-only', DB_RUN_MIGRATIONS='true',
               DATA_DIR=str(data), FILES_DIR=str(data/'files'), CACHE_DIR=str(data/'cache'),
               PLUGINS_DIR=str(app/'plugins'), DEBUG=str(debug).lower(), LOG_DRIVER='stderr',
               PLUGIN_INSTALLER='false', RVSOFTWARE_TEST='true',
               PROJECTA_KEY='c3ludGhldGljLW9ubHktMzItYnl0ZS1rZXktMDAwMDA=',
               PROJECTA_PRIVATE_DIR=str(WORK/'private'), PROJECTA_BASE_URL='http://127.0.0.1:18110',
               F019_APP=str(app), F019_PROFILE='true')
    return env


def database_snapshot(restore=False):
    env = environment('before')
    env['F019_GUARD_ONLY'] = 'true'
    subprocess.run(php_command()+[str(SCRIPTS/'F019-Fixture.php')], env=env, check=True, stdout=subprocess.DEVNULL)
    binary = ROOT/'.runtime/compatibility/mariadb1189/mariadb-11.8.9-winx64/bin'
    target = WORK/'synthetic-snapshot.sql'
    args = ['--no-defaults', '--host=127.0.0.1', '--port=13318', '--user=compat_synthetic', '--password=synthetic-local-only']
    if restore:
        with target.open('rb') as stream:
            subprocess.run([str(binary/'mariadb.exe')]+args, stdin=stream, check=True, stdout=subprocess.DEVNULL)
    else:
        with target.open('wb') as stream:
            subprocess.run([str(binary/'mariadb-dump.exe')]+args+['--single-transaction','--skip-comments','--skip-dump-date','--add-drop-database','--databases','kanboard_compat_http'], stdout=stream, check=True)
    print('Restored fixed synthetic snapshot.' if restore else 'Saved fixed synthetic snapshot (not printed).', flush=True)


def php_command(native=False):
    args = [str(PHP), '-n', '-d', 'extension_dir='+str(PHP.parent/'ext')]
    if not native:
        args += ['-d', 'auto_prepend_file='+str(ROOT/'config/projecta.php')]
    for name in ('gd', 'mbstring', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'zip', 'sodium', 'ldap'):
        args += ['-d', 'extension='+name]
    return args


def prepare(variant):
    app = sandbox(variant)
    app.mkdir(parents=True, exist_ok=True)
    names = subprocess.check_output(['git', 'ls-files', '-z'], cwd=ROOT).decode().split('\0')
    for base in ('app', 'plugins/RvSoftware', 'tests', 'config'):
        names += [str(p.relative_to(ROOT)).replace('\\', '/') for p in (ROOT/base).rglob('*') if p.is_file()]
    for name in dict.fromkeys(names):
        if not name or name in ('config.php', 'data/config.php') or name.startswith(('data/', 'harness/', '.runtime/', '.git')):
            continue
        source = ROOT/name
        if source.is_file():
            target = app/name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(source, target)
    # Before is an immutable snapshot: never prepare it again after implementation.
    bootstrap = '''<?php
$loader = require %s;
$app = getenv('F019_APP');
$map = [];
foreach ($loader->getClassMap() as $class=>$path) {
    if (str_starts_with($class, 'Kanboard\\\\')) { $map[$class] = $app.'/app/'.str_replace('\\\\','/',substr($class,9)).'.php'; }
    if (str_starts_with($class, 'KanboardTests\\\\')) { $map[$class] = $app.'/tests/'.str_replace('\\\\','/',substr($class,14)).'.php'; }
}
$loader->addClassMap($map);
$loader->addPsr4('Kanboard\\\\', $app.'/app', true);
$loader->addPsr4('KanboardTests\\\\', $app.'/tests', true);
'''
    (WORK/'test-bootstrap.php').write_text(bootstrap % json.dumps(str(ROOT/'.runtime/vendor/autoload.php').replace('\\','/')))
    print('Prepared isolated '+variant+' copy (private data excluded).', flush=True)


def test(variant, sqlite, filter_name, native=False):
    env = environment(variant)
    if not sqlite:
        guard = dict(env,F019_GUARD_ONLY='true')
        subprocess.run(php_command()+[str(SCRIPTS/'F019-Fixture.php')],env=guard,check=True,stdout=subprocess.DEVNULL)
    env.update(DB_NAME='kanboard_unit_test')
    if sqlite:
        env.update(DB_DRIVER='sqlite', DB_FILENAME=':memory:')
    if native:
        disabled = WORK/'plugins-disabled'
        disabled.mkdir(exist_ok=True)
        assert not list(disabled.iterdir()), 'Native plugin directory must be empty'
        env.update(RVSOFTWARE_TEST='false',PLUGINS_DIR=str(disabled))
    config = ET.parse(ROOT / ('tests/units.sqlite.xml' if sqlite else 'tests/units.mysql.xml'))
    config.getroot().set('stopOnError', 'false')
    config.getroot().set('stopOnFailure', 'false')
    config.find('./testsuites/testsuite/directory').text = str(sandbox(variant)/'tests/units')
    target = WORK / ('units.'+('sqlite' if sqlite else 'mysql')+'.xml')
    config.write(target, encoding='utf-8', xml_declaration=True)
    command = php_command(native)+[str(ROOT/'.runtime/vendor/bin/phpunit'), '-c', str(target),
                            '--bootstrap', str(WORK/'test-bootstrap.php'), '--do-not-cache-result', '--colors=never']
    if filter_name:
        command += ['--filter', filter_name]
    return subprocess.call(command, env=env, cwd=ROOT)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=('prepare', 'seed', 'test', 'serve', 'snapshot', 'restore'))
    parser.add_argument('--variant', choices=('before', 'after'), default='after')
    parser.add_argument('--sqlite', action='store_true')
    parser.add_argument('--native', action='store_true')
    parser.add_argument('--filter', default='RvSoftware|SchemaHandler')
    parser.add_argument('--debug', action='store_true')
    parser.add_argument('--reset', action='store_true', help='Recreate only kanboard_compat_http after validating its server/datadir')
    args = parser.parse_args()
    if args.action == 'prepare':
        if args.variant == 'before' and (sandbox('before')/'index.php').exists():
            parser.error('Baseline already exists; refusing to overwrite it.')
        prepare(args.variant)
    elif args.action == 'test':
        raise SystemExit(test(args.variant, args.sqlite, args.filter, args.native))
    elif args.action == 'seed':
        env = environment(args.variant)
        if args.reset:
            env['F019_RESET'] = 'true'
        raise SystemExit(subprocess.call(php_command()+[str(SCRIPTS/'F019-Fixture.php')], env=env, cwd=ROOT))
    elif args.action in ('snapshot', 'restore'):
        database_snapshot(args.action == 'restore')
    elif args.action == 'serve':
        raise SystemExit(subprocess.call(php_command()+['-S','127.0.0.1:18110','-t',str(sandbox(args.variant)),str(SCRIPTS/'F019-Router.php')], env=environment(args.variant, args.debug), cwd=ROOT))
