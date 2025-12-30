@echo off
:: Теперь мы просто пишем "php", так как он в PATH
start "API" /min php -S 0.0.0.0:2712 index.php
start "Worker" /min php worker.php
set "SCRIPT_PATH=%~dp0start.bat"
:: Добавляем старт самого батника в реестр
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "MyProjectServer" /t REG_SZ /d "%SCRIPT_PATH%" /f