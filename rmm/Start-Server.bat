@echo off
REM Start DiscloseRMM Server (Windows). No Python required if using the built exe.
cd /d "%~dp0"
if exist DiscloseRMM-Server.exe (
  start "DiscloseRMM Server" DiscloseRMM-Server.exe
  echo Server starting. Open http://THIS-PC-IP:8443 in a browser.
  exit /b 0
)
if exist dist\DiscloseRMM-Server.exe (
  start "DiscloseRMM Server" dist\DiscloseRMM-Server.exe
  exit /b 0
)
echo DiscloseRMM-Server.exe not found. Run build\build_windows.bat first.
exit /b 1
