@echo off
title CitaDev_Watchdog
cd /d "%~dp0"

:: --- ПРЕДВАРИТЕЛЬНАЯ НАСТРОЙКА GIT ---
:: Разрешаем работу в локальной папке (защита от ошибки Dubious Ownership)
git config --global --add safe.directory "*"
:: Заглушки данных пользователя для работы pull без ошибок
git config --global user.email "bot@citadev.local"
git config --global user.name "CitaDevBot"

:loop
echo [%time%] Запуск PHP сервисов...

:: Запуск API сервера на порту 2712 в фоновом окне
start "CitaDev_API" /min php -S 0.0.0.0:2712 index.php

:: Запуск Воркера в текущем окне (он держит цикл)
php worker.php

echo [%time%] Сервис был остановлен. Перезапуск через 3 секунды...
timeout /t 3 >nul
goto loop