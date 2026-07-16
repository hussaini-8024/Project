@echo off
REM Build AU-Kamra IT Experts Remote Manager Windows executables.
REM Agent is built FIRST, then bundled into the Server.

setlocal EnableExtensions
cd /d "%~dp0.."
echo Working directory: %CD%

if not exist "run_server.py" (
  echo ERROR: run_server.py not found. Run this from rmm\build\build_windows.bat
  exit /b 1
)
if not exist "run_agent.py" (
  echo ERROR: run_agent.py not found.
  exit /b 1
)

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
pip install -U pyinstaller

if not exist bin mkdir bin
if not exist bin\agents\windows mkdir bin\agents\windows
if not exist dist mkdir dist

echo.
echo [1/2] Building Agent exe...
pyinstaller --noconfirm --clean --distpath dist --workpath build\work-agent --specpath build build\agent.spec
if errorlevel 1 (
  echo ERROR: Agent build failed.
  exit /b 1
)

if not exist "dist\AU-Kamra-Remote-Manager-Agent.exe" (
  echo ERROR: dist\AU-Kamra-Remote-Manager-Agent.exe was not created.
  dir dist
  exit /b 1
)

copy /Y "dist\AU-Kamra-Remote-Manager-Agent.exe" "bin\AU-Kamra-Remote-Manager-Agent.exe" >nul
copy /Y "dist\AU-Kamra-Remote-Manager-Agent.exe" "bin\agents\windows\AU-Kamra-Remote-Manager-Agent.exe" >nul

echo.
echo [2/2] Building Server exe (with agent bundled)...
pyinstaller --noconfirm --clean --distpath dist --workpath build\work-server --specpath build build\server.spec
if errorlevel 1 (
  echo ERROR: Server build failed.
  exit /b 1
)

if not exist "dist\AU-Kamra-Remote-Manager-Server.exe" (
  echo ERROR: dist\AU-Kamra-Remote-Manager-Server.exe was not created.
  dir dist
  exit /b 1
)

copy /Y "dist\AU-Kamra-Remote-Manager-Server.exe" "bin\AU-Kamra-Remote-Manager-Server.exe" >nul

echo.
echo ============================================
echo  BUILD OK
echo  dist\AU-Kamra-Remote-Manager-Server.exe
echo  dist\AU-Kamra-Remote-Manager-Agent.exe
echo ============================================
endlocal
