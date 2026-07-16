@echo off
REM Build DiscloseRMM single-file Windows executables (run on Windows).
REM Result: dist\DiscloseRMM-Server.exe and dist\DiscloseRMM-Agent.exe
REM End users do NOT need Python — only these two .exe files.

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
copy /Y dist\DiscloseRMM-Agent.exe bin\DiscloseRMM-Agent.exe >nul
copy /Y dist\DiscloseRMM-Server.exe bin\DiscloseRMM-Server.exe >nul

echo.
echo ============================================
echo  Built:
echo    dist\DiscloseRMM-Server.exe
echo    dist\DiscloseRMM-Agent.exe
echo  Also copied to bin\ for server push-install.
echo.
echo  On the management PC:
echo    DiscloseRMM-Server.exe
echo  On each remote PC (Run as Administrator once):
echo    DiscloseRMM-Agent.exe
echo ============================================
endlocal
