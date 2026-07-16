@echo off
REM Start AU-Kamra IT Experts Remote Manager Server
cd /d "%~dp0"
if exist AU-Kamra-Remote-Manager-Server.exe (
  start "AU-Kamra Remote Manager" AU-Kamra-Remote-Manager-Server.exe
  echo Server starting. Open http://THIS-PC-IP:8443 in a browser.
  exit /b 0
)
if exist dist\AU-Kamra-Remote-Manager-Server.exe (
  start "AU-Kamra Remote Manager" dist\AU-Kamra-Remote-Manager-Server.exe
  exit /b 0
)
if exist DiscloseRMM-Server.exe (
  start "AU-Kamra Remote Manager" DiscloseRMM-Server.exe
  exit /b 0
)
echo Server exe not found. Run build\build_windows.bat first.
exit /b 1
