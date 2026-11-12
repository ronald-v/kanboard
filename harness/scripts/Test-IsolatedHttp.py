"""HTTP smoke test for Start-IsolatedServer.ps1 (synthetic credentials only)."""
import html
import base64
import http.cookiejar
import json
from pathlib import Path
import re
import sqlite3
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = "http://127.0.0.1:18001/"


def session(username="admin", password="admin"):
    client = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
    )
    login = client.open(BASE, timeout=15)
    body = login.read().decode()
    assert login.status == 200 and 'name="password"' in body, "Login form unavailable"
    token = re.search(r'name="csrf_token" value="([^"]+)"', body)
    assert token, "CSRF token unavailable"
    payload = urllib.parse.urlencode({
        "username": username, "password": password,
        "csrf_token": html.unescape(token[1]),
    }).encode()
    response = client.open(BASE + "?controller=AuthController&action=check", payload, timeout=15)
    body = response.read().decode()
    assert response.status == 200 and 'name="password"' not in body, "Login failed"
    return client


def main():
    client = session()
    for action in ("show", "tasks", "subtasks", "projects"):
        response = client.open(BASE + "?controller=DashboardController&action=" + action, timeout=15)
        body = response.read().decode()
        assert response.status == 200, action
        assert "Fatal error" not in body and "Warning:" not in body, action
        assert 'name="password"' not in body, action
        assert "Dashboard" in body, action
        print(f"PASS authenticated Dashboard/{action}: HTTP 200")
    print("PASS login with CSRF and synthetic admin; empty Dashboard routes")

    # Read only the disposable database created by our launcher. Never print token.
    database = Path(__file__).resolve().parents[2] / ".runtime/data/db.sqlite"
    with sqlite3.connect(database.as_uri() + "?mode=ro", uri=True) as connection:
        token = connection.execute("SELECT value FROM settings WHERE option = 'api_token'").fetchone()[0]

    def rpc(method, params):
        request = urllib.request.Request(BASE + "jsonrpc.php", json.dumps({
            "jsonrpc": "2.0", "id": 1, "method": method, "params": params,
        }).encode(), headers={
            "Content-Type": "application/json",
            "Authorization": "Basic " + base64.b64encode(("jsonrpc:" + token).encode()).decode(),
        })
        result = json.load(urllib.request.urlopen(request, timeout=15))
        assert "error" not in result, f"Synthetic API fixture failed: {method}"
        return result["result"]

    member = rpc("getUserByName", {"username": "baseline-member"})
    if not member:
        assert rpc("createUser", {"username": "baseline-member", "password": "synthetic-baseline"})
        member = rpc("getUserByName", {"username": "baseline-member"})
    project = rpc("getProjectByIdentifier", {"identifier": "BASELINEEMPTY"})
    if not project:
        project_id = rpc("createProject", {"name": "Synthetic empty project", "identifier": "BASELINEEMPTY", "owner_id": 1})
        assert project_id, "Synthetic project creation failed"
        assert rpc("addProjectUser", {"project_id": project_id, "user_id": int(member["id"])}), "Synthetic project membership failed"
    member_client = session("baseline-member", "synthetic-baseline")
    response = member_client.open(BASE + "?controller=DashboardController&action=show", timeout=15)
    assert response.status == 200 and "Synthetic empty project" in response.read().decode()
    try:
        denied = member_client.open(BASE + "?controller=DashboardController&action=show&user_id=1", timeout=15)
    except urllib.error.HTTPError as error:
        assert error.code == 404, "Foreign user access must be rejected"
        denial = "HTTP 404"
    else:
        denied_body = denied.read().decode()
        # Upstream renders PageNotFoundException with HTTP 200 in this revision.
        title = re.search(r"<title>(.*?)</title>", denied_body, re.S)
        assert title and "Page not found" in title[1], "Foreign user Dashboard unexpectedly accessible"
        assert "Dashboard for admin" not in denied_body, "Foreign Dashboard leaked"
        denial = "native Page not found response (HTTP 200)"
    print(f"PASS synthetic member: empty project visible; foreign Dashboard denied: {denial}")


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        # Do not expose responses, passwords, session cookies or tracebacks.
        print(f"FAIL HTTP smoke: {type(error).__name__}: {error}", file=sys.stderr)
        sys.exit(1)
