"""Exact time, independent HTTP sessions and two-process concurrency checks."""
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone, timedelta
import html
import re
import sys
import time
import urllib.error
import urllib.parse
from rv_http import API, BASE, login, query

OTHER = "http://127.0.0.1:18007/"
PAGE = "?controller=RvTimeController&action=show&plugin=RvSoftware"
SAVE = "?controller=RvTimeController&action=save&plugin=RvSoftware"


def csrf(body):
    return html.unescape(re.search(r'name="csrf_token" value="([^"]+)"', body)[1])


def main():
    root = API()
    user = root.call("getUserByName", {"username": "baseline-member"})
    project = root.call("getProjectByIdentifier", {"identifier": "RVTIME"})
    if not project:
        project_id = root.call("createProject", {"name": "Synthetic time project", "identifier": "RVTIME", "owner_id": 1})
        assert project_id
        assert root.call("addProjectUser", {"project_id": project_id, "user_id": int(user["id"]), "role": "project-member"})
    else:
        project_id = int(project["id"])
    task = root.call("createTask", {"title": "Synthetic time run", "project_id": project_id})
    subtask = root.call("createSubtask", {"task_id": task, "title": "Session A", "user_id": int(user["id"])})
    second = root.call("createSubtask", {"task_id": task, "title": "Session B", "user_id": int(user["id"])})
    assert subtask and second
    api = API("baseline-member", "synthetic-baseline")
    peer = API("baseline-member", "synthetic-baseline", OTHER)
    stamp = str(time.time_ns())
    end = int(time.time()) - 120
    params = {"subtask_id": subtask, "start": end - 3600, "end": end, "note": "Synthetic API work", "operation_key": "api-" + stamp}
    first = api.call("rvCreateTime", params)
    assert first == api.call("rvCreateTime", params)
    assert query("SELECT COUNT(*) AS n FROM rv_time_meta WHERE session_id=?", (first,))[0]["n"] == 1
    assert query("SELECT duration_seconds FROM rv_time_meta WHERE session_id=?", (first,))[0]["duration_seconds"] == 3600
    assert "error" in api.call("rvCreateTime", {**params, "end": end - 1}, True)
    assert "error" in api.call("rvCreateTime", {**params, "start": end + 1, "operation_key": "bad-" + stamp}, True)
    assert not api.call("updateSubtask", {"id": subtask, "task_id": task, "time_spent": 20})

    # Different PHP processes, different PDO connections, same synthetic SQLite.
    with ThreadPoolExecutor(2) as pool:
        results = list(pool.map(lambda client: client.call("setSubtaskStartTime", {"subtask_id": second, "user_id": int(user["id"])}), (api, peer)))
    assert results == [True, True]
    assert len(query("SELECT id FROM subtask_time_tracking WHERE user_id=? AND `end`=0", (user["id"],))) == 1
    fresh = login("baseline-member", "synthetic-baseline", OTHER)
    assert "Temporizador activo" in fresh.open(OTHER + PAGE, timeout=20).read().decode()
    assert not api.call("setSubtaskStartTime", {"subtask_id": subtask, "user_id": int(user["id"])})
    with ThreadPoolExecutor(2) as pool:
        results = list(pool.map(lambda client: client.call("setSubtaskEndTime", {"subtask_id": second, "user_id": int(user["id"])}), (api, peer)))
    assert results == [True, True]
    timer_id = query("SELECT id FROM subtask_time_tracking WHERE subtask_id=? ORDER BY id DESC", (second,))[0]["id"]
    assert query("SELECT COUNT(*) AS n FROM rv_time_audit WHERE session_id=? AND action='stop'", (timer_id,))[0]["n"] == 1

    # Concurrent UI POSTs use independent login cookies/CSRF tokens, same operation key.
    clients = (login("baseline-member", "synthetic-baseline"), fresh)
    bases = (BASE, OTHER)
    tokens = [csrf(client.open(base + PAGE, timeout=20).read().decode()) for client, base in zip(clients, bases)]
    local_end = datetime.now(timezone(timedelta(hours=-5))).replace(second=0, microsecond=0) - timedelta(minutes=5)
    payload = {"operation": "manual", "operation_key": "ui-" + stamp, "subtask_id": subtask, "end_at": local_end.strftime("%Y-%m-%dT%H:%M"), "duration_seconds": 1800, "note": "Synthetic UI concurrent work"}
    def post(index):
        data = urllib.parse.urlencode({**payload, "csrf_token": tokens[index]}).encode()
        response = clients[index].open(bases[index] + SAVE, data, timeout=40)
        return response.status
    with ThreadPoolExecutor(2) as pool:
        assert list(pool.map(post, (0, 1))) == [200, 200]
    assert query("SELECT COUNT(*) AS n FROM rv_time_meta WHERE operation_key=?", ("manual:" + str(user["id"]) + ":ui-" + stamp,))[0]["n"] == 1
    sessions = api.call("rvGetMyTime")
    assert any(int(row["id"]) == first for row in sessions)
    before = query("SELECT COUNT(*) AS n FROM subtask_time_tracking")[0]["n"]
    try:
        clients[0].open(BASE + SAVE, urllib.parse.urlencode(payload).encode(), timeout=20)
    except urllib.error.HTTPError as error:
        assert error.code == 403
    else:
        raise AssertionError("UI accepted missing CSRF")
    assert query("SELECT COUNT(*) AS n FROM subtask_time_tracking")[0]["n"] == before
    # Native timer UI also reaches the overridden service. Extract its reusable
    # CSRF from the authorized task page; do not print tokens or response bodies.
    from html.parser import HTMLParser
    class Links(HTMLParser):
        def __init__(self):
            super().__init__()
            self.token = None
        def handle_starttag(self, tag, attributes):
            href = dict(attributes).get("href", "")
            if tag == "a" and "SubtaskStatusController" in href:
                value = urllib.parse.parse_qs(urllib.parse.urlparse(html.unescape(href)).query)
                if value.get("csrf_token"):
                    self.token = value["csrf_token"][0]
    native_tokens = []
    for client, base in zip(clients, bases):
        page = client.open(base + f"?controller=TaskViewController&action=show&task_id={task}&project_id={project_id}", timeout=20).read().decode()
        links = Links()
        links.feed(page)
        assert links.token, "Native reusable CSRF link missing"
        native_tokens.append(links.token)
    def native_timer(index, action):
        values = {"controller": "SubtaskStatusController", "action": "timer", "task_id": task, "subtask_id": second, "project_id": project_id, "timer": action, "csrf_token": native_tokens[index]}
        return clients[index].open(bases[index] + "?" + urllib.parse.urlencode(values), timeout=40).status
    with ThreadPoolExecutor(2) as pool:
        assert list(pool.map(lambda i: native_timer(i, "start"), (0, 1))) == [200, 200]
    assert len(query("SELECT id FROM subtask_time_tracking WHERE user_id=? AND `end`=0", (user["id"],))) == 1
    with ThreadPoolExecutor(2) as pool:
        assert list(pool.map(lambda i: native_timer(i, "stop"), (0, 1))) == [200, 200]
    assert not query("SELECT id FROM subtask_time_tracking WHERE user_id=? AND `end`=0", (user["id"],))
    projected = query("SELECT time_spent FROM tasks WHERE id=?", (task,))[0]["time_spent"]
    summed = query("SELECT SUM(t.time_spent) AS h FROM subtask_time_tracking t JOIN rv_time_meta m ON m.session_id=t.id JOIN subtasks s ON s.id=t.subtask_id WHERE s.task_id=?", (task,))[0]["h"]
    assert abs(float(projected) - float(summed)) < 0.000001
    print("PASS time API: exact seconds, retry/payload conflict, invalid dates and aggregate-write guard")
    print("PASS two-process concurrency: duplicate timer start/stop, one session/closure, second timer rejected")
    print("PASS new authenticated HTTP client recovers active timer from server")
    print("PASS concurrent UI POSTs: independent cookies/CSRF, one manual entry; missing CSRF rejected")
    print("PASS concurrent native timer UI: reusable CSRF, one start and one closure across two servers")
    print("PASS native projection reconciles with session hours; exact seconds remain independent")


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(f"FAIL time integration: {type(error).__name__}: {error}", file=sys.stderr)
        sys.exit(1)
