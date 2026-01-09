@echo off
cd /d "%~dp0web"
echo Starting local parking permit website...
echo.
echo Open http://localhost:8000 in your browser
echo Press Ctrl+C to stop the server
echo.
start http://localhost:8000
C:\tools\php85\php.exe -S localhost:8000
