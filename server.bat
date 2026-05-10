@echo off
chcp 65001 >nul
cd /d %~dp0
echo Starting Service Discovery Server...
php -S 127.0.0.1:8789 -t %~dp0 index.php
pause
