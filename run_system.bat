@echo off
:loop
echo [%time%] Запуск Core Server...
:: Запускаем API в отдельном окне (или скрыто)
start "API_Server" /min php -S 0.0.0.0:2712 index.php

:: Запускаем Воркер в текущем окне (чтобы видеть логи)
php worker.php

echo [%time%] Система упала или была убита для обновления. Перезапуск через 5 секунд...
timeout /t 5
goto loop