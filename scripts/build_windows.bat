@echo off
REM Build AU Labs single-file .exe binaries + optional Inno Setup installer (Windows)
setlocal EnableExtensions
cd /d "%~dp0\.."

echo [build] Creating venv and installing deps...
if not exist .venv (
  py -3 -m venv .venv
)
call .venv\Scripts\activate.bat
python -m pip install -q -r requirements.txt pyinstaller==6.11.1

if exist build rmdir /s /q build
if not exist dist mkdir dist
if not exist dist\payload mkdir dist\payload
if not exist release mkdir release

echo [build] Building AULabsServer.exe ...
pyinstaller --noconfirm --clean --distpath dist --workpath build packaging\AULabsServer.spec
copy /y dist\AULabsServer.exe dist\payload\AULabsServer.exe >nul

echo [build] Building AULabsAgent.exe ...
pyinstaller --noconfirm --clean --distpath dist --workpath build packaging\AULabsAgent.spec
copy /y dist\AULabsAgent.exe dist\payload\AULabsAgent.exe >nul

echo [build] Building AULabsSetup.exe ...
python packaging\generate_setup_spec.py
pyinstaller --noconfirm --clean --distpath dist --workpath build packaging\_AULabsSetup_gen.spec

if not exist release\windows mkdir release\windows
copy /y dist\AULabsServer.exe release\windows\ >nul
copy /y dist\AULabsAgent.exe release\windows\ >nul
copy /y dist\AULabsSetup.exe release\windows\ >nul
copy /y README.md release\windows\README.txt >nul

echo.
echo [build] Single-file executables ready in release\windows\
echo   AULabsSetup.exe   - double-click installer (like VLC)
echo   AULabsServer.exe  - web panel server
echo   AULabsAgent.exe   - host agent
echo.

where iscc >nul 2>nul
if %ERRORLEVEL%==0 (
  echo [build] Compiling Inno Setup wrapper...
  iscc packaging\windows\AULabsSetup.iss
) else (
  echo [build] Inno Setup not found — AULabsSetup.exe already works as the installer.
)

echo [build] Done.
dir release\windows
endlocal
