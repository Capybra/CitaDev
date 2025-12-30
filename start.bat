@echo off
:: Устанавливаем заголовок окна, чтобы не путать
title CitaDev_Server_Watchdog
cd /d "%~dp0"

:loop
echo [%date% %time%] Запуск серверов...

:: Запускаем API в отдельном скрытом окне
:: Используем "php", так как путь уже в PATH после setup_env.ps1
start "CitaDev_API" /min php -S 0.0.0.0:2712 index.php

:: Запускаем Воркер в этом же окне (он будет держать цикл)
php worker.php

echo [%date% %time%] Сервер был остановлен для обновления или упал.
echo Перезапуск через 2 секунды...
timeout /t 2 >nul
goto loop