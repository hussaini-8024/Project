@echo off
setlocal
title Build AU-Kamra-IT Loan Cards Management
cd /d "%~dp0.."

echo.
echo ============================================
echo  AU-Kamra-IT Loan Cards Management Builder
echo ============================================
echo.

where python >nul 2>nul
if errorlevel 1 (
  echo ERROR: Python is not installed or not on PATH.
  echo Install Python 3.8+ from https://www.python.org/downloads/
  echo Make sure "Add Python to PATH" is checked.
  pause
  exit /b 1
)

python -m pip install --upgrade pip
python -m pip install -r requirements.txt
python -m pip install pyinstaller pywebview

echo.
echo Building single-file EXE (this may take a few minutes)...
echo.

python -m PyInstaller ^
  --noconfirm ^
  --clean ^
  --onefile ^
  --windowed ^
  --name "AU-Kamra-IT-Loan-Cards" ^
  --add-data "au_kamra_loan_cards\templates;au_kamra_loan_cards\templates" ^
  --add-data "au_kamra_loan_cards\static;au_kamra_loan_cards\static" ^
  --add-data "au_kamra_loan_cards\samples;au_kamra_loan_cards\samples" ^
  --hidden-import flask ^
  --hidden-import bs4 ^
  --hidden-import lxml ^
  --hidden-import pypdf ^
  --hidden-import reportlab ^
  --hidden-import webview ^
  run.py

if errorlevel 1 (
  echo.
  echo Build failed.
  pause
  exit /b 1
)

echo.
echo ============================================
echo  Build complete!
echo  EXE: dist\AU-Kamra-IT-Loan-Cards.exe
echo ============================================
echo.
echo Copy the EXE anywhere and run it.
echo Data is stored in AU_Kamra_Data next to the EXE.
echo.
pause
