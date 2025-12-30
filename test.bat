@echo off
set "baseDir=%~dp0"
set "phpExe=%baseDir%php\php.exe"

echo Запуск воркера из: %phpExe%
"%phpExe%" "%baseDir%worker.php"
pause