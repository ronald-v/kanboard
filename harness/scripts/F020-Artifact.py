"""Verify and execute the exact ZIP with an unavailable, fixed synthetic DB endpoint."""
from pathlib import Path, PurePosixPath
import argparse
import hashlib
import json
import os
import socket
import subprocess
import time
import urllib.error
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f020'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('artifact', type=Path)
    args = parser.parse_args()
    artifact = args.artifact.resolve()
    assert artifact.is_relative_to((ROOT/'.runtime/releases').resolve())
    digest = hashlib.sha256(artifact.read_bytes()).hexdigest()
    destination = WORK/('artifact-'+digest[:12])
    with zipfile.ZipFile(artifact) as archive:
        for entry in archive.infolist():
            path = PurePosixPath(entry.filename)
            assert path.parts[0] == 'kanboard' and '..' not in path.parts and not path.is_absolute()
            assert '\\' not in entry.filename and ':' not in entry.filename
            assert (destination/entry.filename).resolve().is_relative_to(destination.resolve())
            assert (entry.external_attr >> 16) & 0o170000 != 0o120000
        manifest = json.loads(archive.read('kanboard/rvsoftware-manifest.json'))
        for name, value in manifest['files'].items():
            assert hashlib.sha256(archive.read('kanboard/'+name)).hexdigest() == value
        assert manifest['productionAuthorized'] is False and manifest['securityEvidenceSha256']
        for name in ('app/Core/Http/ErrorHandler.php', '.user.ini', '.htaccess', 'plugins/RvSoftware/Template/board/manual.php'):
            assert name in manifest['files']
        archive.extractall(destination)
    env = os.environ.copy()
    for name in list(env):
        if name.startswith(('DB_', 'PROJECTA_', 'MAIL_', 'SMTP_')) or name == 'DATABASE_URL': env.pop(name)
    env.update(DB_DRIVER='mysql', DB_HOSTNAME='127.0.0.1', DB_PORT='13319', DB_USERNAME='f020_unavailable',
               DB_PASSWORD='synthetic-only', DB_NAME='f020_unavailable', DEBUG='true')
    for port in (13319, 18114):
        with socket.socket() as probe: assert probe.connect_ex(('127.0.0.1', port)) != 0, 'Synthetic port must be unused'
    php = ROOT/'.runtime/compatibility/php85/php.exe'
    command = [str(php), '-n', '-d', 'extension_dir='+str(php.parent/'ext'), '-d', 'display_errors=1']
    for extension in ('gd', 'mbstring', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'zip', 'sodium'):
        command += ['-d', 'extension='+extension]
    with (WORK/'artifact-server.log').open('w') as log:
        process = subprocess.Popen(command+['-S', '127.0.0.1:18114', '-t', str(destination/'kanboard')], env=env, stdout=log, stderr=log)
        try:
            for _ in range(50):
                try:
                    with socket.create_connection(('127.0.0.1', 18114), timeout=.2): break
                except OSError: time.sleep(.1)
            for name in ('index.php', 'jsonrpc.php', 'mcp/index.php', 'mcp/oauth.php'):
                req = urllib.request.Request('http://127.0.0.1:18114/'+name, data=b'{}' if name == 'jsonrpc.php' else None)
                try: response = urllib.request.urlopen(req, timeout=10)
                except urllib.error.HTTPError as error: response = error
                body = response.read().decode()
                assert response.status == 503, (name, response.status)
                assert response.headers['Cache-Control'] == 'no-store' and response.headers['X-Request-ID'] in body
                assert 'SQLSTATE' not in body and 'f020_unavailable' not in body and str(destination) not in body
        finally:
            process.terminate()
            process.wait(timeout=10)
    (WORK/'artifact-result.json').write_text(json.dumps({'passed': True, 'sha256': digest, 'files': len(manifest['files']),
        'real_connection_failure_checks': 4, 'securityEvidenceSha256': manifest['securityEvidenceSha256']}, indent=2)+'\n')
    print('PASS exact ZIP hashes/exclusions/runtime: four real connection failures safely return 503; SHA256', digest)


if __name__ == '__main__':
    main()
