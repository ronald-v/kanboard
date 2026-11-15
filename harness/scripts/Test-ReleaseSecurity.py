"""Behavioral tests for stale/missing/mismatched release verification."""
from datetime import datetime, timedelta, timezone
import copy
import hashlib
import json
import os
from pathlib import Path
import tempfile
import unittest

from release_security import REQUIRED, validate_evidence, verification_hashes, artifact_bytes

ROOT = Path(__file__).resolve().parents[2]


class ReleaseSecurityTest(unittest.TestCase):
    def metadata(self, root_ref='a'*40, dependency_ref='d'*40):
        return ("<?php return array(\n    'root' => array(\n        'name' => 'rvsoftware/projecta-mcp',\n"
                "        'reference' => '"+root_ref+"',\n    ),\n    'versions' => array(\n"
                "        'vendor/dependency' => array(\n            'version' => '1.0.0',\n"
                "            'reference' => '"+dependency_ref+"',\n        ),\n"
                "        'rvsoftware/projecta-mcp' => array(\n            'reference' => '"+root_ref+"',\n"
                "        ),\n    ),\n);\n").encode()

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(dir=ROOT/'.runtime/f020')
        self.path = Path(self.directory.name)/'evidence.json'
        self.result = Path(self.directory.name)/'result.json'
        self.result.write_text('{"passed":true}')
        self.entries = {'index.php': b'<?php // verified bytes'}
        self.proof = {'schemaVersion': 1, 'scope': 'isolated-local', 'createdAt': datetime.now(timezone.utc).isoformat(),
                      'checks': {key: True for key in REQUIRED}, 'findings': [],
                      'files': {'index.php': hashlib.sha256(self.entries['index.php']).hexdigest()},
                      'auditLocks': {name: hashlib.sha256((ROOT/name).read_bytes()).hexdigest() for name in ('composer.lock', 'mcp/composer.lock')},
                      'verifiers': verification_hashes(ROOT),
                      'results': {self.result.relative_to(ROOT).as_posix(): hashlib.sha256(self.result.read_bytes()).hexdigest()}}

    def tearDown(self):
        self.directory.cleanup()

    def verify(self, proof=None, entries=None):
        self.path.write_text(json.dumps(self.proof if proof is None else proof))
        return validate_evidence(self.path, self.entries if entries is None else entries, ROOT)

    def testMatchingEvidenceAccepted(self):
        self.assertEqual(64, len(self.verify()))

    def testMissingEvidenceRejected(self):
        with self.assertRaises(ValueError): validate_evidence(None, self.entries, ROOT)

    def testEachMandatoryCheckMustPass(self):
        for name in REQUIRED:
            with self.subTest(name=name):
                proof = copy.deepcopy(self.proof)
                proof['checks'][name] = False
                with self.assertRaises(ValueError): self.verify(proof)

    def testOldFutureAndNaiveTimestampRejected(self):
        for timestamp in (datetime.now(timezone.utc)-timedelta(hours=25), datetime.now(timezone.utc)+timedelta(hours=1), datetime.now()):
            proof = copy.deepcopy(self.proof)
            proof['createdAt'] = timestamp.isoformat()
            with self.assertRaises(ValueError): self.verify(proof)

    def testOpenHighAndCriticalRejected(self):
        for severity in ('high', 'critical'):
            proof = copy.deepcopy(self.proof)
            proof['findings'] = [{'severity': severity, 'status': 'open'}]
            with self.assertRaises(ValueError): self.verify(proof)

    def testChangedAddedOrRemovedArtifactBytesRejected(self):
        for entries in ({}, {'index.php': b'changed'}, dict(self.entries, extra=b'new')):
            with self.assertRaises(ValueError): self.verify(entries=entries)

    def testModifiedToolingRejected(self):
        proof = copy.deepcopy(self.proof)
        proof['verifiers']['harness/scripts/F020-Errors.py'] = '0'*64
        with self.assertRaises(ValueError): self.verify(proof)

    def testModifiedResultsRejected(self):
        self.result.write_text('changed')
        with self.assertRaises(ValueError): self.verify()

    def testChangedAuditLockRejected(self):
        proof = copy.deepcopy(self.proof)
        proof['auditLocks']['composer.lock'] = '0'*64
        with self.assertRaises(ValueError): self.verify(proof)

    def testResultOutsideEvidenceDirectoryRejected(self):
        proof = copy.deepcopy(self.proof)
        proof['results'] = {'README.md': hashlib.sha256((ROOT/'README.md').read_bytes()).hexdigest()}
        with self.assertRaises(ValueError): self.verify(proof)

    def testResealingOldResultsDoesNotRenewThem(self):
        old = (datetime.now(timezone.utc)-timedelta(hours=25)).timestamp()
        os.utime(self.result, (old, old))
        with self.assertRaises(ValueError): self.verify()

    def metadata_proof(self):
        name = 'mcp/vendor/composer/installed.php'
        self.entries = {name: artifact_bytes(name, self.metadata())}
        self.proof['files'] = {name: hashlib.sha256(self.entries[name]).hexdigest()}
        return name

    def testComposerRootReferenceChangeKeepsEvidenceValid(self):
        name = self.metadata_proof()
        changed = artifact_bytes(name, self.metadata(root_ref='b'*40))
        self.assertEqual(self.entries[name], changed)
        self.verify(entries={name: changed})
        self.assertEqual(changed, artifact_bytes(name, changed))

    def testComposerDependencyChangeStillRejected(self):
        name = self.metadata_proof()
        changed = artifact_bytes(name, self.metadata(dependency_ref='e'*40))
        with self.assertRaises(ValueError): self.verify(entries={name: changed})

    def testComposerExtraExecutableCodeStillRejected(self):
        name = self.metadata_proof()
        changed = artifact_bytes(name, self.metadata()+b'echo "unexpected";')
        with self.assertRaises(ValueError): self.verify(entries={name: changed})

    def testUnexpectedComposerRootFailsClosed(self):
        with self.assertRaises(ValueError):
            artifact_bytes('mcp/vendor/composer/installed.php', self.metadata().replace(b'rvsoftware/projecta-mcp', b'other/project'))

    def testMismatchNamesAreReported(self):
        with self.assertRaisesRegex(ValueError, 'modificados: index.php'):
            self.verify(entries={'index.php': b'changed'})


if __name__ == '__main__':
    result = unittest.TextTestRunner().run(unittest.defaultTestLoader.loadTestsFromTestCase(ReleaseSecurityTest))
    (ROOT/'.runtime/f020/gate-result.json').write_text(json.dumps({'passed': result.wasSuccessful(), 'tests': result.testsRun})+'\n')
    raise SystemExit(0 if result.wasSuccessful() else 1)
