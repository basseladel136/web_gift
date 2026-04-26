# WebGift — OWASP ZAP Security Test Suite

Automated security scanner for the **WebGift Laravel REST API** using [OWASP ZAP](https://www.zaproxy.org/).

---

## 📁 Files in this folder

| File | Purpose |
|------|---------|
| `zap_security_test.py` | Main Python test script (Spider → Active Scan → Manual Checks → Report) |
| `webgift_zap_context.xml` | ZAP context — scope, auth & users (import into ZAP GUI) |
| `requirements.txt` | Python dependencies |
| `run_zap_test.bat` | One-click runner (starts ZAP + runs tests) |
| `zap_report.html` | HTML report (generated after each run) |
| `zap_report.json` | JSON report (generated after each run) |

---

## 🚀 Quick Start (one-click)

```bat
cd C:\laragon\www\WebGift
security\run_zap_test.bat
```

> The batch file installs Python packages, starts ZAP in daemon mode, waits for it, then runs the tests.

---

## 🔧 Manual Setup

### Step 1 — Install OWASP ZAP

Download the Windows installer from:  
**https://www.zaproxy.org/download/**

Default install path: `C:\Program Files\OWASP\Zed Attack Proxy\`

### Step 2 — Install Python dependencies

```powershell
pip install python-owasp-zap-v2.4 requests colorama
```

### Step 3 — Start ZAP in daemon mode

```powershell
& "C:\Program Files\OWASP\Zed Attack Proxy\zap.bat" `
    -daemon `
    -port 8090 `
    -config api.key=zapkey123 `
    -config api.addrs.addr.name=.* `
    -config api.addrs.addr.enabled=true
```

Wait ~20 seconds for ZAP to fully start.

### Step 4 — Make sure WebGift is running

Laragon must be running and `http://webgift.test` must be reachable.  
The database should be seeded (run `php artisan db:seed` if needed).

### Step 5 — Run the tests

```powershell
cd C:\laragon\www\WebGift
python security\zap_security_test.py
```

---

## 🔍 What the Test Suite Checks

### Automated (ZAP Engine)
| Phase | What happens |
|-------|-------------|
| **Spider** | ZAP crawls all discoverable URLs on `http://webgift.test` |
| **Traffic Injection** | The script registers/logs in a test user and calls every API endpoint through the ZAP proxy so ZAP learns them |
| **Active Scan** | ZAP automatically fuzzes every parameter with thousands of attack payloads (SQLi, XSS, RFI, etc.) |

### Manual Checks (custom Python logic)

| ID | Check | What's tested |
|----|-------|--------------|
| 4-A | **SQL Injection** | Sends SQLi payloads to `/products/{id}` |
| 4-B | **Unauthenticated access** | Calls protected routes without a token; expects 401 |
| 4-C | **IDOR** | Tries to read another user's order by ID |
| 4-D | **Mass Assignment** | Sends `role=admin` during registration; should be ignored |
| 4-E | **Brute-Force Protection** | 10 rapid bad-password logins; expects 429 rate limit |
| 4-F | **Admin Route Privilege Escalation** | Normal-user token hitting `/admin/*` routes; expects 403 |
| 4-G | **XSS in Review Comment** | Posts `<script>alert('XSS')</script>`; checks if reflected |
| 4-H | **Sensitive Data Leakage** | Checks `/auth/profile` response for `password`, `remember_token` |

---

## 📊 Reports

After each run, two reports are generated:

| Report | Location | Format |
|--------|---------|--------|
| Human-readable | `security/zap_report.html` | HTML (open in browser) |
| Machine-readable | `security/zap_report.json` | JSON |

---

## ⚙️ Configuration

Edit the top of `zap_security_test.py` to change defaults:

```python
ZAP_PROXY   = "http://127.0.0.1:8090"   # ZAP daemon address
ZAP_API_KEY = "zapkey123"               # Must match -config api.key=...
TARGET_URL  = "http://webgift.test"     # Your Laragon vhost
API_BASE    = f"{TARGET_URL}/api"

TEST_USER   = { "email": "test@webgift.test", "password": "password", ... }
```

---

## 🚦 Exit Codes

| Code | Meaning |
|------|---------|
| `0` | All checks passed — no High/Critical issues |
| `1` | One or more High or Critical issues found |

---

## 🔒 Known Security Considerations for WebGift

Based on the API structure, pay particular attention to:

1. **Rate limiting on `/api/auth/login`** — No throttle middleware visible in routes; consider adding `ThrottleRequests` middleware.
2. **IDOR on `/api/orders/{id}`** — Ensure `OrderController@show` checks `order->user_id === auth()->id()`.
3. **Admin middleware** — `is_admin` middleware must be verified for all admin routes.
4. **`APP_DEBUG=true`** in `.env` — Must be `false` in production to avoid stack trace leakage.
5. **VAPID keys in `.env`** — Treat as secrets; never commit `.env` to git.

---

## 📋 Recommended ZAP Scan Policy

For the active scan, import the following scan policy in ZAP GUI:  
`Analyse → Scan Policy Manager → Add` and enable:

- ✅ SQL Injection
- ✅ Cross Site Scripting (Reflected)
- ✅ Cross Site Scripting (Persistent)
- ✅ Path Traversal
- ✅ Remote File Inclusion
- ✅ Server Side Include
- ✅ External Redirect
- ✅ Buffer Overflow
- ✅ CRLF Injection
- ✅ Parameter Tampering
