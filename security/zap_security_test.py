"""
WebGift API — OWASP ZAP Security Test Suite
============================================
Uses ZAP's Python API (zapv2) to run automated active and passive
security scans against the local WebGift Laravel API.

Prerequisites
-------------
1. OWASP ZAP installed and running in daemon mode:
       zap.bat -daemon -port 8090 -config api.key=zapkey123
2. Python packages:
       pip install python-owasp-zap-v2.4 requests colorama
3. WebGift running at http://webgift.test (Laragon vhost)

Run:
       python security/zap_security_test.py
"""

import time
import sys
import json
import datetime
import requests
from zapv2 import ZAPv2
from colorama import init, Fore, Style

init(autoreset=True)

# ─── Configuration ──────────────────────────────────────────────────────────
ZAP_PROXY   = "http://127.0.0.1:8090"
ZAP_API_KEY = "zapkey123"
TARGET_URL  = "http://webgift.test"          # Laragon vhost
API_BASE    = f"{TARGET_URL}/api"

# Test user credentials (created by seeder)
TEST_USER = {
    "email":    "test@webgift.test",
    "password": "password",
    "name":     "ZAP Test User",
}

ADMIN_USER = {
    "email":    "admin@webgift.test",
    "password": "password",
}

# Alert risk levels considered a FAILURE
FAIL_ON_RISK_LEVELS = {"High", "Critical"}

# ─── Helpers ────────────────────────────────────────────────────────────────
def log(msg, color=Fore.WHITE):
    ts = datetime.datetime.now().strftime("%H:%M:%S")
    print(f"{color}[{ts}] {msg}{Style.RESET_ALL}")


def section(title):
    print(f"\n{Fore.CYAN}{'═'*60}")
    print(f"  {title}")
    print(f"{'═'*60}{Style.RESET_ALL}")


def api(path, method="GET", json_body=None, token=None):
    """Send a request through the ZAP proxy so ZAP learns the traffic."""
    proxies = {"http": ZAP_PROXY, "https": ZAP_PROXY}
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    url = f"{API_BASE}{path}"
    resp = requests.request(
        method, url, json=json_body, headers=headers,
        proxies=proxies, verify=False, timeout=30,
    )
    return resp


# ─── Step 1: Connect to ZAP ─────────────────────────────────────────────────
def connect_zap():
    section("Connecting to OWASP ZAP")
    zap = ZAPv2(apikey=ZAP_API_KEY, proxies={"http": ZAP_PROXY, "https": ZAP_PROXY})
    try:
        version = zap.core.version
        log(f"Connected to ZAP {version}", Fore.GREEN)
    except Exception as exc:
        log(f"Cannot connect to ZAP: {exc}", Fore.RED)
        log("Make sure ZAP is running:  zap.bat -daemon -port 8090 -config api.key=zapkey123", Fore.YELLOW)
        sys.exit(1)
    return zap


# ─── Step 2: Spider the API to discover URLs ────────────────────────────────
def spider_api(zap):
    section("Step 1 — Spidering the API")
    log("Starting traditional spider …")
    scan_id = zap.spider.scan(TARGET_URL, apikey=ZAP_API_KEY)
    time.sleep(2)
    while int(zap.spider.status(scan_id)) < 100:
        pct = zap.spider.status(scan_id)
        log(f"  Spider progress: {pct}%")
        time.sleep(3)
    log("Spider complete.", Fore.GREEN)
    urls = zap.spider.results(scan_id)
    log(f"  Discovered {len(urls)} URLs")
    return urls


# ─── Step 3: Inject known traffic so ZAP learns authenticated endpoints ──────
def inject_traffic(zap):
    section("Step 2 — Injecting Authenticated Traffic")

    # --- Register a fresh test user ------------------------------------------
    log("Registering test user …")
    reg = api("/auth/register", "POST", {
        "name": TEST_USER["name"],
        "email": TEST_USER["email"],
        "password": TEST_USER["password"],
        "password_confirmation": TEST_USER["password"],
    })
    if reg.status_code == 201:
        token = reg.json().get("token")
        log(f"  Registered. Token: {token[:20]}…", Fore.GREEN)
    elif reg.status_code == 422:
        # Already exists — just login
        login = api("/auth/login", "POST", {
            "email": TEST_USER["email"],
            "password": TEST_USER["password"],
        })
        token = login.json().get("token")
        log(f"  User exists; logged in. Token: {token[:20]}…", Fore.YELLOW)
    else:
        log(f"  Registration failed: {reg.status_code} {reg.text}", Fore.RED)
        token = None

    if not token:
        log("No token obtained — authenticated tests will be skipped.", Fore.RED)
        return None

    # --- Public endpoints -----------------------------------------------------
    log("Visiting public endpoints …")
    api("/categories")
    api("/products")
    api("/products/1")
    api("/products/1/reviews")

    # --- Authenticated endpoints ---------------------------------------------
    log("Visiting authenticated endpoints …")
    api("/auth/profile", token=token)
    api("/cart", token=token)
    api("/cart/add", "POST", {"product_id": 1, "quantity": 2}, token=token)
    api("/cart/update", "PUT", {"product_id": 1, "quantity": 1}, token=token)
    api("/cart/remove/1", "DELETE", token=token)
    api("/orders", token=token)
    api("/orders", "POST", {"items": [{"product_id": 1, "quantity": 1}]}, token=token)
    api("/orders/1", token=token)
    api("/wishlist", token=token)
    api("/wishlist/add", "POST", {"product_id": 2}, token=token)
    api("/products/1/reviews", "POST", {"rating": 5, "comment": "Great!"}, token=token)
    api("/coupons/apply", "POST", {"code": "SAVE10"}, token=token)

    log("Traffic injection complete.", Fore.GREEN)
    return token


# ─── Step 4: Active Scan ────────────────────────────────────────────────────
def active_scan(zap):
    section("Step 3 — Active Security Scan")
    log("Launching ZAP active scan …  (this may take several minutes)")
    scan_id = zap.ascan.scan(TARGET_URL, apikey=ZAP_API_KEY)
    time.sleep(5)
    while True:
        progress = int(zap.ascan.status(scan_id))
        log(f"  Active scan: {progress}%")
        if progress >= 100:
            break
        time.sleep(10)
    log("Active scan complete.", Fore.GREEN)


# ─── Step 5: Manual security checks via requests ────────────────────────────
def manual_checks(token):
    section("Step 4 — Manual Security Checks")
    results = []

    # 4-A  SQL Injection probes --------------------------------------------------
    log("[4-A] SQL Injection probes …")
    sqli_payloads = ["' OR '1'='1", "1; DROP TABLE users--", "' UNION SELECT 1,2,3--"]
    for payload in sqli_payloads:
        r = api(f"/products/{payload}")
        if r.status_code not in (400, 404, 422):
            results.append({
                "check": "SQL Injection",
                "status": "⚠ POSSIBLE",
                "detail": f"Payload '{payload}' returned HTTP {r.status_code}",
                "risk": "High",
            })
        else:
            results.append({"check": f"SQLi probe ({payload})", "status": "✓ Handled", "risk": "Info"})

    # 4-B  Auth: access protected route without token ----------------------------
    log("[4-B] Unauthenticated access to protected routes …")
    protected = ["/auth/profile", "/cart", "/orders", "/wishlist"]
    for path in protected:
        r = api(path)          # no token
        if r.status_code == 401:
            results.append({"check": f"Unauth {path}", "status": "✓ 401 returned", "risk": "Info"})
        else:
            results.append({
                "check": f"Unauth {path}",
                "status": f"⚠ FAIL — HTTP {r.status_code} (expected 401)",
                "risk": "High",
            })

    # 4-C  IDOR: try to access another user's order (ID=1 as non-owner) ----------
    log("[4-C] IDOR probe on /orders/1 …")
    if token:
        r = api("/orders/1", token=token)
        if r.status_code in (403, 404):
            results.append({"check": "IDOR /orders/1", "status": "✓ 403/404 returned", "risk": "Info"})
        elif r.status_code == 200:
            results.append({
                "check": "IDOR /orders/1",
                "status": "⚠ POSSIBLE — returned 200 for another user's order",
                "risk": "High",
            })

    # 4-D  Mass assignment probe -------------------------------------------------
    log("[4-D] Mass assignment probe on register …")
    r = api("/auth/register", "POST", {
        "name": "Hacker",
        "email": "hacker_zap@webgift.test",
        "password": "password",
        "password_confirmation": "password",
        "role": "admin",          # should be ignored
        "is_admin": True,
    })
    body = r.json() if r.headers.get("Content-Type", "").startswith("application/json") else {}
    role = body.get("user", {}).get("role", "not returned")
    if role == "admin":
        results.append({
            "check": "Mass Assignment (role escalation)",
            "status": "⚠ CRITICAL — role=admin was accepted",
            "risk": "Critical",
        })
    else:
        results.append({"check": "Mass Assignment", "status": "✓ role field ignored", "risk": "Info"})

    # 4-E  Brute-force: rapid repeated login attempts ----------------------------
    log("[4-E] Brute-force login (10 rapid attempts) …")
    blocked = False
    for i in range(10):
        r = api("/auth/login", "POST", {"email": TEST_USER["email"], "password": f"wrong{i}"})
        if r.status_code in (429, 423):
            blocked = True
            log(f"  Rate-limited at attempt {i+1} — {r.status_code}", Fore.GREEN)
            break
    if blocked:
        results.append({"check": "Brute-Force Protection", "status": "✓ Rate limiting active", "risk": "Info"})
    else:
        results.append({
            "check": "Brute-Force Protection",
            "status": "⚠ WARNING — no rate limiting detected after 10 attempts",
            "risk": "Medium",
        })

    # 4-F  Admin route access by normal user -------------------------------------
    log("[4-F] Normal user accessing admin routes …")
    admin_routes = [
        ("GET",    "/admin/coupons"),
        ("GET",    "/admin/analytics/sales"),
        ("GET",    "/admin/analytics/customers"),
        ("DELETE", "/admin/products/1"),
    ]
    if token:
        for method, path in admin_routes:
            r = api(path, method, token=token)
            if r.status_code in (403, 401):
                results.append({"check": f"Admin gate {method} {path}", "status": "✓ Forbidden", "risk": "Info"})
            else:
                results.append({
                    "check": f"Admin gate {method} {path}",
                    "status": f"⚠ FAIL — HTTP {r.status_code}",
                    "risk": "High",
                })

    # 4-G  XSS probe in user-controlled fields ------------------------------------
    log("[4-G] XSS probe in review comment …")
    xss_payload = "<script>alert('XSS')</script>"
    if token:
        r = api("/products/1/reviews", "POST", {
            "rating": 4,
            "comment": xss_payload,
        }, token=token)
        body_str = r.text
        if xss_payload in body_str:
            results.append({
                "check": "XSS in review comment",
                "status": "⚠ WARNING — raw script tag reflected in response",
                "risk": "Medium",
            })
        else:
            results.append({"check": "XSS in review comment", "status": "✓ Payload not reflected", "risk": "Info"})

    # 4-H  Sensitive data in responses -------------------------------------------
    log("[4-H] Checking for sensitive data leakage …")
    if token:
        r = api("/auth/profile", token=token)
        body = r.json() if r.ok else {}
        user_data = body.get("user", {})
        for sensitive_field in ["password", "password_hash", "remember_token"]:
            if sensitive_field in user_data:
                results.append({
                    "check": f"Sensitive field '{sensitive_field}' in profile",
                    "status": "⚠ CRITICAL — sensitive data exposed",
                    "risk": "Critical",
                })
            else:
                results.append({
                    "check": f"Sensitive field '{sensitive_field}' absent",
                    "status": "✓ Not exposed",
                    "risk": "Info",
                })

    return results


# ─── Step 6: Collect & Report ZAP Alerts ────────────────────────────────────
def report_alerts(zap, manual_results):
    section("Step 5 — Security Report")

    alerts = zap.core.alerts(baseurl=TARGET_URL)
    log(f"ZAP found {len(alerts)} alert(s)")

    # Group by risk
    by_risk = {}
    for a in alerts:
        risk = a.get("risk", "Informational")
        by_risk.setdefault(risk, []).append(a)

    RISK_COLOR = {
        "Critical": Fore.RED,
        "High":     Fore.RED,
        "Medium":   Fore.YELLOW,
        "Low":      Fore.CYAN,
        "Informational": Fore.WHITE,
    }

    for risk_level in ["Critical", "High", "Medium", "Low", "Informational"]:
        items = by_risk.get(risk_level, [])
        if not items:
            continue
        log(f"\n  ── {risk_level} ({len(items)}) ──", RISK_COLOR.get(risk_level, Fore.WHITE))
        for a in items:
            log(f"    • {a.get('alert')} — {a.get('url')}", RISK_COLOR.get(risk_level, Fore.WHITE))
            log(f"      Solution: {a.get('solution', 'N/A')[:120]}", Fore.WHITE)

    # Manual check summary
    log("\n  ── Manual Checks ──", Fore.CYAN)
    for r in manual_results:
        color = Fore.RED if r["risk"] in ("High", "Critical") else \
                Fore.YELLOW if r["risk"] == "Medium" else Fore.GREEN
        log(f"    {r['status']}", color)

    # Save JSON report
    report = {
        "generated_at": datetime.datetime.now().isoformat(),
        "target": TARGET_URL,
        "zap_alerts": alerts,
        "manual_checks": manual_results,
    }
    report_path = "security/zap_report.json"
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)
    log(f"\nFull JSON report saved → {report_path}", Fore.GREEN)

    # HTML report via ZAP
    html_report = zap.core.htmlreport(apikey=ZAP_API_KEY)
    html_path = "security/zap_report.html"
    with open(html_path, "w", encoding="utf-8") as f:
        f.write(html_report)
    log(f"HTML report saved      → {html_path}", Fore.GREEN)

    # Determine overall pass/fail
    fail = any(
        a.get("risk") in FAIL_ON_RISK_LEVELS for a in alerts
    ) or any(
        r["risk"] in FAIL_ON_RISK_LEVELS for r in manual_results
    )
    return fail


# ─── Main ───────────────────────────────────────────────────────────────────
def main():
    log("WebGift — OWASP ZAP Security Test Suite", Fore.MAGENTA)
    log(f"Target: {TARGET_URL}", Fore.MAGENTA)

    zap = connect_zap()

    # Open the target in ZAP
    zap.core.access_url(TARGET_URL, apikey=ZAP_API_KEY)
    time.sleep(2)

    spider_api(zap)
    token = inject_traffic(zap)
    active_scan(zap)
    manual_results = manual_checks(token)
    failed = report_alerts(zap, manual_results)

    section("Overall Result")
    if failed:
        log("❌  SECURITY TEST FAILED — High/Critical issues found. See report.", Fore.RED)
        sys.exit(1)
    else:
        log("✅  SECURITY TEST PASSED — No High/Critical issues detected.", Fore.GREEN)
        sys.exit(0)


if __name__ == "__main__":
    main()
