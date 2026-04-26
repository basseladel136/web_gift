@echo off
:: ============================================================
::  WebGift — OWASP ZAP Security Test Runner
::  Run this from the project root:  security\run_zap_test.bat
:: ============================================================

setlocal

:: ── User-configurable paths ─────────────────────────────────
set ZAP_HOME=C:\Program Files\ZAP\Zed Attack Proxy
set ZAP_EXE=%ZAP_HOME%\zap.bat
set ZAP_PORT=8090
set ZAP_API_KEY=zapkey123
set PYTHON=python

:: ── Colours (via ANSI if supported) ────────────────────────
set GRN=[92m
set RED=[91m
set YLW=[93m
set RST=[0m

echo.
echo %GRN%============================================================%RST%
echo %GRN%  WebGift — OWASP ZAP Security Test Suite%RST%
echo %GRN%============================================================%RST%
echo.

:: ── 1. Install Python dependencies ─────────────────────────
echo %YLW%[1/4] Installing Python dependencies ...%RST%
%PYTHON% -m pip install -q -r security\requirements.txt
if errorlevel 1 (
    echo %RED%ERROR: pip install failed. Is Python on your PATH?%RST%
    pause & exit /b 1
)
echo     Done.

:: ── 2. Start ZAP in daemon mode ─────────────────────────────
echo %YLW%[2/4] Starting OWASP ZAP daemon on port %ZAP_PORT% ...%RST%
if not exist "%ZAP_EXE%" (
    echo %RED%ERROR: ZAP not found at "%ZAP_EXE%"%RST%
    echo %YLW%       Download from https://www.zaproxy.org/download/%RST%
    pause & exit /b 1
)

:: Kill any leftover ZAP / Java processes to avoid "home dir in use" error
echo     Stopping any existing ZAP instances ...
taskkill /F /FI "WINDOWTITLE eq ZAP*" /T >nul 2>&1
taskkill /F /IM java.exe /FI "WINDOWTITLE eq ZAP*" /T >nul 2>&1
:: Broader fallback: kill every java.exe (safe on a dev machine)
taskkill /F /IM java.exe >nul 2>&1
timeout /t 3 /nobreak >nul

start "ZAP_DAEMON" "%~dp0start_zap_daemon.bat"

:: ── 3. Poll until ZAP is alive (up to 5 minutes) ──────────
echo %YLW%[3/4] Waiting for ZAP to become ready (up to 5 min) ...%RST%
echo     Initial 20 s wait for Java/ZAP to load ...
timeout /t 20 /nobreak >nul
set ZAP_READY=0
set ZAP_TRIES=0

:ZAP_WAIT_LOOP
set /a ZAP_TRIES+=1
timeout /t 5 /nobreak >nul
%PYTHON% -c "import sys,requests; r=requests.get('http://127.0.0.1:%ZAP_PORT%/JSON/core/view/version/?apikey=%ZAP_API_KEY%',timeout=4); print('    ZAP version:', r.json().get('version','?')); sys.exit(0)" 2>nul
if not errorlevel 1 (
    set ZAP_READY=1
    goto ZAP_READY
)
echo     Still waiting ... attempt %ZAP_TRIES%/60
if %ZAP_TRIES% lss 60 goto ZAP_WAIT_LOOP

echo %RED%ERROR: ZAP did not start in time. Try running it manually:%RST%
echo        "%ZAP_EXE%" -daemon -port %ZAP_PORT% -config api.key=%ZAP_API_KEY%
pause & exit /b 1

:ZAP_READY
echo %GRN%    ZAP is ready!%RST%

:: ── 4. Run the security test script ─────────────────────────
echo %YLW%[4/4] Running security tests ...%RST%
echo.
%PYTHON% security\zap_security_test.py
set EXIT_CODE=%errorlevel%

echo.
if %EXIT_CODE% == 0 (
    echo %GRN%============================================================%RST%
    echo %GRN%  RESULT: ALL TESTS PASSED ✅%RST%
    echo %GRN%============================================================%RST%
) else (
    echo %RED%============================================================%RST%
    echo %RED%  RESULT: SECURITY ISSUES FOUND ❌  — Check the reports:%RST%
    echo %RED%    security\zap_report.html%RST%
    echo %RED%    security\zap_report.json%RST%
    echo %RED%============================================================%RST%
)

echo.
echo Reports saved in:  security\zap_report.html  ^&  security\zap_report.json
pause
endlocal
exit /b %EXIT_CODE%
