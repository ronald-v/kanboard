"""Portable, checksum-verified target runtimes; no global install or services."""
from concurrent.futures import ThreadPoolExecutor
import hashlib
from pathlib import Path
import shutil
import subprocess
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[2] / '.runtime/compatibility'
PHP_FILE = 'php-8.5.12-1-nts-Win32-vs17-x64.zip'
PHP_HASH = '2a36a2edcb7a5b9252f638fd5e4cf84d7f6e066092654220dc0b63e5cf4e9723'
MARIA_BASE = 'https://dlm.mariadb.com/MariaDB/mariadb-11.8.9/winx64-packages/'


def install(name, filename, url, digest):
    archive = ROOT / filename
    target = ROOT / name
    if not archive.exists():
        with urllib.request.urlopen(url,timeout=120) as response, archive.open('wb') as output:
            shutil.copyfileobj(response,output)
    actual = hashlib.sha256(archive.read_bytes()).hexdigest()
    if actual != digest:
        raise AssertionError('Checksum mismatch: '+filename)
    marker = target / '.verified-sha256'
    if not marker.exists():
        target.mkdir(exist_ok=True)
        with zipfile.ZipFile(archive) as source:
            for item in source.infolist():
                assert (target / item.filename).resolve().is_relative_to(target.resolve())
            source.extractall(target)
        marker.write_text(digest,encoding='ascii')
    assert marker.read_text(encoding='ascii') == digest
    print('PASS portable runtime verified:',name,'SHA256',digest,flush=True)


def main():
    ROOT.mkdir(parents=True,exist_ok=True)
    sums = urllib.request.urlopen(MARIA_BASE+'sha256sums.txt',timeout=30).read().decode()
    filename = 'mariadb-11.8.9-winx64.zip'
    digest = next(line.split()[0].lower() for line in sums.splitlines() if line.split() and line.split()[-1].lstrip('*').rsplit('/',1)[-1] == filename)
    assert len(digest) == 64 and all(c in '0123456789abcdef' for c in digest)
    with ThreadPoolExecutor(2) as pool:
        jobs = [pool.submit(install,'php85',PHP_FILE,'https://downloads.php.net/~windows/releases/archives/'+PHP_FILE,PHP_HASH),
                pool.submit(install,'mariadb1189',filename,MARIA_BASE+filename,digest)]
        for job in jobs:
            job.result()
    data = ROOT/'maria-data'
    if not data.exists():
        result = subprocess.run([str(ROOT/'mariadb1189/mariadb-11.8.9-winx64/bin/mariadb-install-db.exe'),'--datadir='+str(data),'--port=13318','--password=synthetic-local-only','--silent'],capture_output=True,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
        assert result.returncode == 0, 'Dedicated MariaDB initialization failed; inspect local directory before retrying'
        print('PASS dedicated database directory initialized; no Windows service installed')


if __name__ == '__main__':
    main()
