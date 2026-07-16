@echo off
REM Build AU-Kamra IT Experts Remote Manager Windows executables.
REM IMPORTANT: Agent is built FIRST, then bundled into the Server so
REM admin-panel "Generate agent packages" can serve a real .exe.

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

if not exist bin mkdir bin
if not exist bin\agents\windows mkdir bin\agents\windows
if not exist dist mkdir dist

echo.
echo [1/2] Building Agent exe...
pyinstaller --noconfirm build\agent.spec
if errorlevel 1 exit /b 1

if not exist dist\AU-Kamra-Remote-Manager-Agent.exe (
  echo ERROR: Agent exe was not created in dist\
  exit /b 1
)

copy /Y dist\AU-Kamra-Remote-Manager-Agent.exe bin\AU-Kamra-Remote-Manager-Agent.exe >nul
copy /Y dist\AU-Kamra-Remote-Manager-Agent.exe bin\agents\windows\AU-Kamra-Remote-Manager-Agent.exe >nul
copy /Y dist\AU-Kamra-Remote-Manager-Agent.exe bin\DiscloseRMM-Agent.exe >nul

echo.
echo [2/2] Building Server exe (with agent bundled)...
pyinstaller --noconfirm build\server.spec
if errorlevel 1 exit /b 1

if not exist dist\AU-Kamra-Remote-Manager-Server.exe (
  echo ERROR: Server exe was not created in dist\
  exit /b 1
)

copy /Y dist\AU-Kamra-Remote-Manager-Server.exe bin\AU-Kamra-Remote-Manager-Server.exe >nul
copy /Y dist\AU-Kamra-Remote-Manager-Server.exe bin\DiscloseRMM-Server.exe >nul

echo.
echo ============================================
echo  AU-Kamra IT Experts Remote Manager
echo  Built:
echo    dist\AU-Kamra-Remote-Manager-Server.exe
echo    dist\AU-Kamra-Remote-Manager-Agent.exe
echo    bin\agents\windows\AU-Kamra-Remote-Manager-Agent.exe
echo.
echo  Deploy BOTH files together, or just the Server
echo  (agent is bundled for Generate / Download).
echo ============================================
endlocal
