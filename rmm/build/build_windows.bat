@echo off
REM Build AU-Kamra IT Experts Remote Manager Windows executables.
REM Result: dist\AU-Kamra-Remote-Manager-Server.exe and Agent.exe

setlocal
cd /d "%~dp0.."

where python >nul 2>&1
if errorlevel 1 (
  echo Python is required ONLY on the build machine, not on managed PCs.
  exit /b 1
)

if not exist .venv (
  python -m venv .venv
)
call .venv\Scripts\activate.bat
python -m pip install -U pip
pip install -r requirements.txt

echo.
echo Building Server exe...
pyinstaller --noconfirm build\server.spec
if errorlevel 1 exit /b 1

echo.
echo Building Agent exe...
pyinstaller --noconfirm build\agent.spec
if errorlevel 1 exit /b 1

if not exist bin mkdir bin
if not exist bin\agents\windows mkdir bin\agents\windows
copy /Y dist\AU-Kamra-Remote-Manager-Agent.exe bin\AU-Kamra-Remote-Manager-Agent.exe >nul
copy /Y dist\AU-Kamra-Remote-Manager-Server.exe bin\AU-Kamra-Remote-Manager-Server.exe >nul
copy /Y dist\AU-Kamra-Remote-Manager-Agent.exe bin\agents\windows\AU-Kamra-Remote-Manager-Agent.exe >nul
REM Legacy aliases for older docs/scripts
copy /Y dist\AU-Kamra-Remote-Manager-Agent.exe bin\DiscloseRMM-Agent.exe >nul
copy /Y dist\AU-Kamra-Remote-Manager-Server.exe bin\DiscloseRMM-Server.exe >nul

echo.
echo ============================================
echo  AU-Kamra IT Experts Remote Manager
echo  Built:
echo    dist\AU-Kamra-Remote-Manager-Server.exe
echo    dist\AU-Kamra-Remote-Manager-Agent.exe
echo ============================================
endlocal
