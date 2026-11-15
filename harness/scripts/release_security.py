"""Validate local security evidence against the exact bytes about to be packaged."""
from datetime import datetime, timezone, timedelta
import hashlib
import json
import re
from pathlib import Path

REQUIRED = {'http_errors', 'apache_rules', 'manual_board_browser', 'sqlite_regression',
            'mariadb_regression', 'auth_api_mcp', 'dependency_audit', 'security_review'}


def artifact_bytes(name, data):
    """Canonicalize only the Composer root package's incidental Git reference.

    Dependency metadata and executable PHP remain byte-for-byte protected. The
    actual source identity lives in the release manifest, not Composer's guessed
    reference for this package embedded in the parent Kanboard repository.
    """
    if name != 'mcp/vendor/composer/installed.php':
        return data
    text = data.decode('utf-8')
    blocks = re.compile(r"^([ \t]*)'(root|rvsoftware/projecta-mcp)' => array\(\r?\n(.*?)^\1\),", re.M | re.S)
    found = []

    def normalize(match):
        indent, key, body = match.groups()
        if key == 'root' and not re.search(r"^\s*'name' => 'rvsoftware/projecta-mcp',\r?$", body, re.M):
            raise ValueError('Unexpected Composer root package; verify mcp/vendor/composer/installed.php')
        pattern = r"^([ \t]*'reference' => )(?:'[0-9a-fA-F]{40}(?:[0-9a-fA-F]{24})?'|null)(,\r?$)"
        normalized, count = re.subn(pattern, lambda field: field[1]+'null'+field[2], body, flags=re.M)
        if count != 1:
            raise ValueError('Unexpected Composer root reference format; repeat dependency verification')
        found.append(key)
        return match[0].replace(body, normalized, 1)

    text = blocks.sub(normalize, text)
    if sorted(found) != ['root', 'rvsoftware/projecta-mcp']:
        raise ValueError('Missing/duplicate Composer root metadata; repeat dependency verification')
    return text.encode('utf-8')


def verification_hashes(root):
    """Tests and shared helpers are part of the evidence, even when not deployed."""
    paths = [path for base in ('harness/scripts', 'tests') for path in (root/base).rglob('*')
             if path.is_file() and path.suffix in ('.py', '.php', '.ps1', '.xml') and '__pycache__' not in path.parts]
    if any(path.is_symlink() or not path.resolve().is_relative_to(root.resolve()) for path in paths):
        raise ValueError('Verification input escapes repository')
    return {path.relative_to(root).as_posix(): hashlib.sha256(path.read_bytes()).hexdigest() for path in sorted(paths)}


def validate_evidence(path, entries, root):
    if path is None:
        raise ValueError('Security evidence required: run F020 verification and pass --security-evidence PATH')
    proof = json.loads(Path(path).read_text(encoding='utf-8'))
    if proof.get('schemaVersion') != 1 or proof.get('scope') != 'isolated-local':
        raise ValueError('Invalid security evidence scope/version')
    created = datetime.fromisoformat(proof['createdAt'])
    if created.tzinfo is None or not timedelta(0) <= datetime.now(timezone.utc)-created <= timedelta(hours=24):
        raise ValueError('Security evidence expired or has an invalid timestamp (24h maximum)')
    if not all(proof.get('checks', {}).get(name) is True for name in REQUIRED):
        raise ValueError('Missing or failed mandatory security checks')
    findings = proof.get('findings')
    if not isinstance(findings, list) or any(item.get('severity') not in ('info', 'low', 'medium', 'high', 'critical')
        or item.get('status') not in ('verified_fixed', 'open', 'mitigated', 'needs_validation') for item in findings):
        raise ValueError('Invalid security findings')
    if any(item.get('severity') in ('high', 'critical') and item.get('status') != 'verified_fixed'
           for item in findings):
        raise ValueError('Open high/critical security finding prevents packaging')
    actual = {name: hashlib.sha256(data).hexdigest() for name, data in sorted(entries.items())}
    expected = proof.get('files')
    if not isinstance(expected, dict):
        raise ValueError('Invalid security evidence file map')
    if expected != actual:
        groups = {'agregados': sorted(actual.keys()-expected.keys()), 'retirados': sorted(expected.keys()-actual.keys()),
                  'modificados': sorted(name for name in actual.keys() & expected.keys() if actual[name] != expected[name])}
        details = '; '.join(label+': '+', '.join(names[:8])+(' (...)' if len(names)>8 else '')
                            for label, names in groups.items() if names)
        raise ValueError('La evidencia no coincide con los archivos del ZIP ('+details+'). Ejecuta de nuevo la verificación antes de sellarla.')
    locks = proof.get('auditLocks', {})
    if set(locks) != {'composer.lock', 'mcp/composer.lock'} or any(hashlib.sha256((root/name).read_bytes()).hexdigest() != value for name, value in locks.items()):
        raise ValueError('Lockfiles changed after dependency audit')
    tools = proof.get('verifiers', {})
    if tools != verification_hashes(root):
        raise ValueError('Security verification tooling changed; repeat verification')
    for name, value in proof.get('results', {}).items():
        target = (root/name).resolve()
        if not target.is_relative_to((root/'.runtime/f020').resolve()) or not target.is_file():
            raise ValueError('Security result path outside isolated evidence directory')
        if hashlib.sha256(target.read_bytes()).hexdigest() != value:
            raise ValueError('Security result changed after verification')
        age = datetime.now(timezone.utc)-datetime.fromtimestamp(target.stat().st_mtime, timezone.utc)
        if age < -timedelta(minutes=5) or age > timedelta(hours=24):
            raise ValueError('Security result expired or has an invalid timestamp')
    if not proof.get('results'):
        raise ValueError('Security evidence has no retained results')
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()
