@echo off
:: ============================================================
::  ZAP Daemon Launcher — called by run_zap_test.bat
::  Do NOT run this directly.
:: ============================================================
set ZAP_HOME=C:\Program Files\ZAP\Zed Attack Proxy
set ZAP_EXE=%ZAP_HOME%\zap.bat
set ZAP_PORT=8090
set ZAP_API_KEY=zapkey123
:: Use an isolated ZAP home so we control config and it never
:: gets overwritten by the user's main ZAP config.xml
set ZAP_DIR=%~dp0zap_home

if not exist "%ZAP_DIR%" mkdir "%ZAP_DIR%"

cd /D "%ZAP_HOME%"
"%ZAP_EXE%" -daemon -port %ZAP_PORT% -dir "%ZAP_DIR%" ^
    -config api.key=%ZAP_API_KEY% ^
    -config "api.addrs.addr(0).name=.*" ^
    -config "api.addrs.addr(0).enabled=true" ^
    -config start.checkForUpdates=false ^
    -config start.checkAddonUpdates=false ^
    -config start.installAddonUpdates=false ^
    -config start.installScannerRules=false
