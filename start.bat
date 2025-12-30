@echo off
:: Устанавливаем кодировку UTF-8 для корректного отображения кириллицы
chcp 65001 >nul
title CitaDev_Watchdog
cd /d "%~dp0"

:: --- ОЧИСТКА ЗАВИСШИХ ПРОЦЕССОВ ---
taskkill /F /IM php.exe >nul 2>&1

:: --- ПОИСК IP ZEROTIER (ДЛЯ ИНФОРМАЦИИ) ---
echo [%time%] Поиск IP в сети ZeroTier...
set "ZT_IP=127.0.0.1"
for /f "tokens=*" %%a in ('powershell -NoProfile -Command "Get-NetIPAddress -InterfaceAlias '*ZeroTier*' -AddressFamily IPv4 | Select-Object -ExpandProperty IPAddress"') do set ZT_IP=%%a

echo [+] IP ZeroTier определен как: %ZT_IP%

:: --- НАСТРОЙКА ПОРТАТИВНОГО GIT ---
git config --global --add safe.directory "*"
git config --global user.email "bot@citadev.local"
git config --global user.name "CitaDevBot"

:loop
echo [%time%] Запуск PHP API на порту 2712...
:: Запускаем на 0.0.0.0, чтобы сервер принимал запросы и на 127.0.0.1, и на IP ZeroTier
start "CitaDev_API" /min php -S 0.0.0.0:2712 index.php

echo [%time%] Запуск Worker Core...
:: Запуск Воркера в текущем окне для мониторинга
php worker.php

echo [%time%] Сервис был остановлен. Перезапуск через 3 секунды...
timeout /t 3 >nul
goto loop