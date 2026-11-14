"""Append the approved Projecta settings to private config without exposing values."""
from pathlib import Path
import base64
import secrets

ROOT=Path(__file__).resolve().parents[2]
path=ROOT/'data/config.php'
assert path.is_file()
raw=path.read_bytes()
assert b'PROJECTA LOCAL SETTINGS' not in raw, 'Already configured; inspect instead of duplicating'
text=raw.decode('utf-8-sig')
assert not text.rstrip().endswith('?>'), 'Review closing PHP tag before editing private config'
key=base64.b64encode(secrets.token_bytes(32)).decode()
addition="\n// PROJECTA LOCAL SETTINGS: never commit this private file.\n"
addition+="require_once dirname(__DIR__).'/config/projecta.php';\n"
addition+="// Prefer PROJECTA_KEY from the server environment. Back up the private fallback key.\n"
addition+="defined('PROJECTA_KEY') or define('PROJECTA_KEY', getenv('PROJECTA_KEY') ?: '"+key+"');\n"
path.write_bytes(raw+addition.encode())
print('Private config updated: SMTP environment settings and stable local MFA key. No application/DB startup.')
