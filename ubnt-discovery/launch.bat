@echo off
REM Launch UBNT Dish IP Finder (Web UI) on Windows
cd /d "%~dp0"

python -c "import flask" 2>nul
if errorlevel 1 (
  echo Installing Flask...
  pip install -r requirements.txt
)

echo.
echo   UBNT Dish IP Finder
echo   Open http://127.0.0.1:5055 in your browser
echo   Press Ctrl+C to stop
echo.
python app.py
pause
