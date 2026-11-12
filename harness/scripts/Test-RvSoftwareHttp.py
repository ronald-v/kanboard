"""Backend HTTP checks against the isolated PHP server; no browser automation."""
import runpy
import sys
import urllib.request
from pathlib import Path

BASE = "http://127.0.0.1:18005/"
DISABLED = "http://127.0.0.1:18006/"
ROUTE = "?controller=RvHomeController&action=show&plugin=RvSoftware"


def main():
    common = runpy.run_path(str(Path(__file__).with_name("Test-IsolatedHttp.py")))
    login = common["session"]
    login.__globals__["BASE"] = BASE
    common["main"]()
    anonymous = urllib.request.urlopen(BASE + ROUTE, timeout=15)
    assert 'name="password"' in anonymous.read().decode(), "Anonymous route did not require login"
    for username, password in (("admin", "admin"), ("baseline-member", "synthetic-baseline")):
        response = login(username, password).open(BASE + ROUTE, timeout=15)
        body = response.read().decode()
        assert response.status == 200 and 'id="rv-title"' in body, "Authenticated plugin route failed"
        assert "plugins/RvSoftware/Assets/app.css" in body, "Plugin stylesheet not loaded"
        policy = response.headers.get("Content-Security-Policy", "")
        assert policy and "unsafe-eval" not in policy, "Native CSP missing or weakened"
    asset = urllib.request.urlopen(BASE + "plugins/RvSoftware/Assets/app.css", timeout=10)
    assert asset.status == 200 and ".rv-panel" in asset.read().decode()
    login.__globals__["BASE"] = DISABLED
    native = login().open(DISABLED + "?controller=DashboardController&action=show", timeout=15).read().decode()
    assert "Dashboard" in native and "plugins/RvSoftware/Assets/app.css" not in native
    print("PASS plugin HTTP: anonymous login required; admin/member route; stylesheet; native CSP")
    print("PASS plugin disabled: native Dashboard accessible without plugin stylesheet")


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(f"FAIL plugin HTTP: {type(error).__name__}: {error}", file=sys.stderr)
        sys.exit(1)
