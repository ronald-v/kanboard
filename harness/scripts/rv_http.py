"""Clients for our loopback-only synthetic Kanboard test environment."""
import base64
import json
from pathlib import Path
import runpy
import sqlite3
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
BASE = "http://127.0.0.1:18005/"


def query(sql, params=()):
    with sqlite3.connect((ROOT / ".runtime/data/db.sqlite").as_uri() + "?mode=ro", uri=True) as connection:
        connection.row_factory = sqlite3.Row
        return [dict(row) for row in connection.execute(sql, params)]


def login(username="admin", password="admin", base=BASE):
    common = runpy.run_path(str(Path(__file__).with_name("Test-IsolatedHttp.py")))
    function = common["session"]
    function.__globals__["BASE"] = base
    return function(username, password)


class API:
    def __init__(self, username="jsonrpc", password=None, base=BASE):
        assert base in (BASE, "http://127.0.0.1:18007/", "http://127.0.0.1:18008/"), "Only isolated loopback servers allowed"
        if password is None:
            password = query("SELECT value FROM settings WHERE option='api_token'")[0]["value"]
        self.base = base
        self.header = "Basic " + base64.b64encode((username + ":" + password).encode()).decode()

    def call(self, method, params=None, allow_error=False):
        request = urllib.request.Request(self.base + "jsonrpc.php", json.dumps({"jsonrpc": "2.0", "id": 1, "method": method, "params": params or {}}).encode(), headers={"Content-Type": "application/json", "Authorization": self.header})
        try:
            result = json.load(urllib.request.urlopen(request, timeout=40))
        except urllib.error.HTTPError as error:
            if allow_error and error.code in (400, 403):
                return {"error": {"code": error.code}}
            raise AssertionError("HTTP failure in " + method) from None
        if "error" in result:
            if allow_error:
                return result
            raise AssertionError("API failure in " + method)
        return result["result"]
