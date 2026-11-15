"""Seal already observed local results for exact deployable bytes; never runs the app."""
from datetime import datetime, timezone, timedelta
import hashlib
import importlib.util
import json
from pathlib import Path

from release_security import REQUIRED, verification_hashes, artifact_bytes

ROOT = Path(__file__).resolve().parents[2]
WORK = ROOT/'.runtime/f020'


def main():
    spec = importlib.util.spec_from_file_location('builder', ROOT/'harness/scripts/Build-Release.py')
    builder = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(builder)
    entries, _ = builder.collect_entries('HEAD', True)
    # The artifact must contain the exact product files used by integration.
    for name, data in entries.items():
        tested = ROOT/'.runtime/projecta/app'/name
        if not tested.is_file() or tested.read_bytes() != data:
            raise ValueError('Product changed since integration: '+name)
    sources = ['errors/result.json', 'integration-result.json', 'browser-result.json', 'sqlite-result.json',
               'mariadb-result.json', 'audit-root.json', 'audit-mcp.json', 'review.json', 'gate-result.json']
    results = {name: json.loads((WORK/name).read_text(encoding='utf-8-sig')) for name in sources}
    for name, value in results.items():
        if name.startswith('audit-'):
            audit = value['result']
            if audit.get('advisories') or audit.get('abandoned'): raise ValueError('Dependency review required: '+name)
            lock = ROOT/('composer.lock' if name == 'audit-root.json' else 'mcp/composer.lock')
            if value['lockSha256'] != hashlib.sha256(lock.read_bytes()).hexdigest(): raise ValueError('Lock changed since audit')
        elif value.get('passed') is not True:
            raise ValueError('Missing passing result: '+name)
        for source, digest in value.get('testedFiles', {}).items():
            path = (ROOT/source).resolve()
            if not path.is_relative_to(ROOT.resolve()) or hashlib.sha256(artifact_bytes(source, path.read_bytes())).hexdigest() != digest:
                raise ValueError('Source changed since '+name+': '+source)
    review = results['review.json']
    proof = {'schemaVersion': 1, 'scope': 'isolated-local', 'createdAt': datetime.now(timezone(timedelta(hours=-5))).isoformat(),
             'checks': {key: True for key in REQUIRED}, 'findings': review['findings'],
             'files': {name: hashlib.sha256(data).hexdigest() for name, data in sorted(entries.items())},
             'auditLocks': {name: hashlib.sha256((ROOT/name).read_bytes()).hexdigest() for name in ('composer.lock', 'mcp/composer.lock')},
             'verifiers': verification_hashes(ROOT),
             'results': {(WORK/name).relative_to(ROOT).as_posix(): hashlib.sha256((WORK/name).read_bytes()).hexdigest() for name in sources}}
    target = WORK/'security-evidence.json'
    target.write_text(json.dumps(proof, indent=2)+'\n')
    builder.validate_evidence(target, entries, ROOT)
    print('PASS sealed isolated evidence for', len(entries), 'exact artifact files (24h validity).')


if __name__ == '__main__':
    main()
