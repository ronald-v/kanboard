"""Build an audited development artifact using native git archive exclusions.

--staged creates a review preview from the index tree. Default uses an exact
commit. --working-tree includes new product files and locked MCP runtime dependencies.
No checkout/deletion/deployment; private configuration and data are always excluded.
"""
import argparse
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import re
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[2]
DEST = ROOT / '.runtime/releases'


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT).strip()


def forbidden(name):
    path = PurePosixPath(name)
    parts = path.parts
    if any(part in ('.git', '.github', '.runtime', 'harness', 'tests', 'test', 'node_modules', '__pycache__') for part in parts):
        return True
    if path.name in ('AGENTS.md', 'config.php', '.env') or path.suffix.lower() in ('.sqlite','.bak','.log','.pem','.key'):
        return True
    if 'data' in parts and path.suffix.lower() in ('.sql','.zip'):
        return True
    return False


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--ref', default='HEAD')
    parser.add_argument('--staged', action='store_true')
    parser.add_argument('--working-tree', action='store_true', help='Reviewed working sources, including new product files; excludes private data.')
    args = parser.parse_args()
    commit = git('rev-parse', args.ref + '^{commit}').decode()
    assert not (args.staged and args.working_tree)
    tree = git('write-tree').decode() if args.staged else git('rev-parse', commit + '^{tree}').decode()
    reference = tree if args.staged else commit
    raw = subprocess.check_output(['git','archive','--format=zip',reference],cwd=ROOT)
    entries = {}
    modes = {}
    with zipfile.ZipFile(io.BytesIO(raw)) as source:
        for item in source.infolist():
            if item.is_dir():
                continue
            if forbidden(item.filename):
                raise AssertionError('Forbidden artifact path: ' + item.filename)
            entries[item.filename] = source.read(item)
            modes[item.filename] = item.external_attr
    if args.working_tree:
        names=git('ls-files','--cached','--others','--exclude-standard','-z').decode().split('\0')
        names=[name for name in names if name and (ROOT/name).is_file()]
        attributes=subprocess.check_output(['git','check-attr','-z','--stdin','export-ignore'],cwd=ROOT,input=('\0'.join(names)+'\0').encode()).decode().split('\0')
        ignored={attributes[i] for i in range(0,len(attributes)-2,3) if attributes[i+2]=='set'}
        entries={}
        modes={}
        for name in names:
            if name in ignored or forbidden(name): continue
            entries[name]=(ROOT/name).read_bytes()
        dependency_root=ROOT/'mcp/vendor'
        assert (dependency_root/'autoload.php').is_file(), 'Install locked MCP dependencies first'
        for source in dependency_root.rglob('*'):
            if not source.is_file() or source.is_symlink(): continue
            assert source.resolve().is_relative_to(dependency_root.resolve())
            name=source.relative_to(ROOT).as_posix()
            if forbidden(name) or any(part.lower() in ('docs','examples','fixtures','bin') for part in source.relative_to(dependency_root).parts): continue
            entries[name]=source.read_bytes()
    for required in ('LICENSE','index.php','vendor/autoload.php','libs/picodb/lib/PicoDb/Database.php','assets/css/light.min.css','plugins/RvSoftware/Plugin.php'):
        assert required in entries, 'Required runtime file missing: ' + required
    if 'mcp/index.php' in entries:
        for required in ('mcp/src/Config.php','mcp/src/Tools.php','mcp/src/OAuth/Server.php','mcp/vendor/autoload.php'):
            assert required in entries, 'Missing MCP runtime file; build the reviewed working bundle: '+required
    plugin = entries['plugins/RvSoftware/Plugin.php'].decode()
    manifest = {'kind':'working-tree-preview' if args.working_tree else ('development-preview' if args.staged else 'development-snapshot'),
                'productionAuthorized':False,'sourceCommit':None if (args.staged or args.working_tree) else commit,
                'baseCommit':commit,'sourceTree':None if args.working_tree else tree,'targetVersion':None,
                'pluginVersion':re.search(r"const VERSION = '([^']+)'",plugin)[1],
                'upstreamCommit':re.search(r"const UPSTREAM_COMMIT = '([^']+)'",plugin)[1],
                'files':{name:hashlib.sha256(data).hexdigest() for name,data in sorted(entries.items())}}
    entries['rvsoftware-manifest.json'] = (json.dumps(manifest,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
    DEST.mkdir(parents=True,exist_ok=True)
    bundle_id=hashlib.sha256(json.dumps(manifest['files'],sort_keys=True).encode()).hexdigest()[:12]
    path = DEST / ('projecta-' + manifest['pluginVersion'] + '-preview-' + bundle_id + '.zip' if args.working_tree else 'kanboard-rvsoftware-' + reference[:12] + '.zip')
    with zipfile.ZipFile(path,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as output:
        for name,data in sorted(entries.items()):
            info = zipfile.ZipInfo('kanboard/' + name,(2026,1,1,0,0,0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = modes.get(name,0o100644 << 16)
            output.writestr(info,data)
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    path.with_suffix('.zip.sha256').write_text(digest+'  '+path.name+'\n',encoding='ascii')
    # Read back actual compressed content, including every runtime file hash.
    with zipfile.ZipFile(path) as archive:
        for name,checksum in manifest['files'].items():
            assert hashlib.sha256(archive.read('kanboard/'+name)).hexdigest() == checksum
        assert not any(forbidden('/'.join(PurePosixPath(name).parts[1:])) for name in archive.namelist())
    print('PASS artifact:',path.relative_to(ROOT).as_posix(),len(manifest['files']),'files; SHA256',digest)


if __name__ == '__main__':
    main()
