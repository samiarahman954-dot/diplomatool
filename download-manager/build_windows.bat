@echo off
REM Build SwiftGet.exe on your own Windows PC (needs Python 3.10+ from python.org)
cd /d "%~dp0"
python -m pip install --upgrade pyinstaller || goto :error
pyinstaller --noconfirm --onefile --windowed --name SwiftGet --icon assets\swiftget.ico --add-data "assets\swiftget.ico;assets" run.py || goto :error
if not exist dist\browser-extension xcopy /e /i /y browser-extension dist\browser-extension >nul
echo.
echo Done!  Your app is in: %~dp0dist\SwiftGet.exe
pause
exit /b 0
:error
echo Build failed.
pause
exit /b 1
