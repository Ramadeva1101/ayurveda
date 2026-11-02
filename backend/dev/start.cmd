@echo off
rem Local test servers (Laragon binaries). Opens two windows:
rem   MySQL on 127.0.0.1:3306 and the PHP backend on http://127.0.0.1:8099
rem Then run "npm run dev" in the project folder and open http://localhost:4321
rem Close the two windows to stop them.

set PHP_DIR=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64
set MYSQL_DIR=C:\laragon\bin\mysql\mysql-8.0.30-winx64

start "AWH MySQL" "%MYSQL_DIR%\bin\mysqld.exe" --datadir=C:/laragon/data/mysql-8 --port=3306 --console
start "AWH PHP backend" "%PHP_DIR%\php.exe" -c "%PHP_DIR%" -S 127.0.0.1:8099 -t "%~dp0..\public"
