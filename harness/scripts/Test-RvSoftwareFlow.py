"""Create/assign/move through native HTTP/API on synthetic SQLite only."""
import base64
import html
import json
from pathlib import Path
import re
import runpy
import sqlite3
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = "http://127.0.0.1:18005/"


def main():
    common = runpy.run_path(str(Path(__file__).with_name("Test-IsolatedHttp.py")))
    login = common["session"]
    login.__globals__["BASE"] = BASE
    database = Path(__file__).resolve().parents[2] / ".runtime/data/db.sqlite"
    with sqlite3.connect(database.as_uri() + "?mode=ro", uri=True) as connection:
        token = connection.execute("SELECT value FROM settings WHERE option='api_token'").fetchone()[0]

    def rpc(method, params, username="jsonrpc", password=None, allow_error=False):
        key = username + ":" + (token if password is None else password)
        request = urllib.request.Request(BASE + "jsonrpc.php", json.dumps({"jsonrpc": "2.0", "id": 1, "method": method, "params": params}).encode(), headers={"Content-Type": "application/json", "Authorization": "Basic " + base64.b64encode(key.encode()).decode()})
        try:
            result = json.load(urllib.request.urlopen(request, timeout=15))
        except urllib.error.HTTPError as error:
            if allow_error and error.code == 403:
                return {"error": {"code": 403}}
            raise
        if not allow_error:
            assert "error" not in result, "Native API failed: " + method
            return result["result"]
        return result

    member = rpc("getUserByName", {"username": "baseline-member"})
    assert member, "Run Test-RvSoftwareHttp.py to create baseline fixtures first"
    viewer = rpc("getUserByName", {"username": "baseline-viewer"})
    if not viewer:
        assert rpc("createUser", {"username": "baseline-viewer", "password": "synthetic-viewer"})
        viewer = rpc("getUserByName", {"username": "baseline-viewer"})
    project = rpc("getProjectByIdentifier", {"identifier": "RVF006"})
    if not project:
        project_id = rpc("createProject", {"name": "Synthetic flow project", "identifier": "RVF006", "owner_id": 1})
        assert project_id
        assert rpc("addProjectUser", {"project_id": project_id, "user_id": int(member["id"]), "role": "project-member"})
        assert rpc("addProjectUser", {"project_id": project_id, "user_id": int(viewer["id"]), "role": "project-viewer"})
        project = rpc("getProjectById", {"project_id": project_id})
    project_id = int(project["id"])
    columns = rpc("getColumns", {"project_id": project_id})
    swimlane_id = int(rpc("getActiveSwimlanes", {"project_id": project_id})[0]["id"])
    client = login("baseline-member", "synthetic-baseline")
    form = client.open(BASE + f"?controller=TaskCreationController&action=show&project_id={project_id}", timeout=15).read().decode()
    assert "Campos avanzados" in form and 'name="csrf_token"' in form
    assert form.index('name="title"') < form.index("Campos avanzados")
    csrf = re.search(r'name="csrf_token" value="([^"]+)"', form)[1]
    title = 'Synthetic flow <em>escaped</em> ' + str(len(rpc("getAllTasks", {"project_id": project_id, "status_id": 1})))
    payload = urllib.parse.urlencode({"csrf_token": html.unescape(csrf), "title": title, "project_id": project_id, "owner_id": member["id"], "column_id": columns[0]["id"], "swimlane_id": swimlane_id, "color_id": "yellow"}).encode()
    created = client.open(BASE + f"?controller=TaskCreationController&action=save&project_id={project_id}", payload, timeout=15)
    assert created.status == 200
    tasks = rpc("getAllTasks", {"project_id": project_id, "status_id": 1})
    task = next((t for t in tasks if t["title"] == title), None)
    assert task and int(task["owner_id"]) == int(member["id"]), "UI task creation/assignment failed"
    assert rpc("moveTaskPosition", {"project_id": project_id, "task_id": int(task["id"]), "column_id": int(columns[1]["id"]), "position": 1, "swimlane_id": swimlane_id}, "baseline-member", "synthetic-baseline")
    assert int(rpc("getTask", {"task_id": int(task["id"])})["column_id"]) == int(columns[1]["id"])
    body = client.open(BASE + "?controller=RvHomeController&action=show&plugin=RvSoftware", timeout=15).read().decode()
    assert html.escape(title, quote=False) in body and title not in body, "Task title escaped incorrectly"
    assert "user-scalable=no" not in body and "rv-sidebar" in body and "rv-mobile-nav" in body
    board = client.open(BASE + f"?controller=BoardViewController&action=show&project_id={project_id}", timeout=15).read().decode()
    assert "Synthetic flow project" in board and "Fatal error" not in board
    observer = login("baseline-viewer", "synthetic-viewer")
    assert observer.open(BASE + f"?controller=BoardViewController&action=show&project_id={project_id}", timeout=15).status == 200
    try:
        observer.open(BASE + f"?controller=TaskCreationController&action=show&project_id={project_id}", timeout=15)
    except urllib.error.HTTPError as error:
        assert error.code == 403
    else:
        raise AssertionError("Viewer could open task creation")
    denied = rpc("createTask", {"title": "Denied synthetic mutation", "project_id": project_id}, "baseline-viewer", "synthetic-viewer", True)
    assert "error" in denied, "Viewer API mutation unexpectedly allowed"
    login.__globals__["BASE"] = "http://127.0.0.1:18006/"
    native_form = login("baseline-member", "synthetic-baseline").open("http://127.0.0.1:18006/" + f"?controller=TaskCreationController&action=show&project_id={project_id}", timeout=15).read().decode()
    assert set(re.findall(r'name="([^"]+)"', native_form)) == set(re.findall(r'name="([^"]+)"', form)), "A native form field was lost"
    print("PASS native UI HTTP: create/assign with CSRF, advanced fields, escaped title, scoped home")
    print("PASS native API: member moves task; viewer reads board but cannot create by UI/API")
    print("PASS HTML: branded navigation, mobile menu and unrestricted viewport; visual QA is separate")
    print("PASS native field names preserved: comparison with plugin-disabled form")


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(f"FAIL flow: {type(error).__name__}: {error}", file=sys.stderr)
        sys.exit(1)
