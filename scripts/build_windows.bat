@echo off
setlocal
title Build AU-Kamra Server + Agent EXEs
cd /d "%~dp0.."

echo.
echo ============================================
echo  Building AU-Kamra Server and Agent EXEs
echo ============================================
echo.

where python >nul 2>nul
if errorlevel 1 (
  echo ERROR: Python is not installed or not on PATH.
  echo Install Python 3.8+ from https://www.python.org/downloads/
  pause
  exit /b 1
)

python -m pip install --upgrade pip
python -m pip install -r requirements.txt
python -m pip install pyinstaller pywebview

echo.
echo [1/2] Building SERVER exe...
python -m PyInstaller ^
  --noconfirm --clean --onefile --windowed ^
  --name "AU-Kamra-IT-Loan-Cards-Server" ^
  --add-data "au_kamra_loan_cards\templates;au_kamra_loan_cards\templates" ^
  --add-data "au_kamra_loan_cards\static;au_kamra_loan_cards\static" ^
  --add-data "au_kamra_loan_cards\samples;au_kamra_loan_cards\samples" ^
  --hidden-import flask --hidden-import bs4 --hidden-import lxml ^
  --hidden-import pypdf --hidden-import reportlab --hidden-import webview ^
  run.py

if errorlevel 1 (
  echo Server build failed.
  pause
  exit /b 1
)

echo.
echo [2/2] Building AGENT exe...
python -m PyInstaller ^
  --noconfirm --clean --onefile --windowed ^
  --name "AU-Kamra-IT-Agent" ^
  --hidden-import webview ^
  agent_run.py

if errorlevel 1 (
  echo Agent build failed.
  pause
  exit /b 1
)

echo.
echo ============================================
echo  Build complete!
echo  Server: dist\AU-Kamra-IT-Loan-Cards-Server.exe
echo  Agent : dist\AU-Kamra-IT-Agent.exe
echo ============================================
echo.
echo Install the SERVER exe on the Windows server PC.
echo Install the AGENT exe on remote PCs on the same LAN.
echo Agents connect using the server PC IP address.
echo Default login: admin / admin123
echo.
pause
