# Проверка прав администратора
if (!([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "!!! ЗАПУСТИТЕ ОТ ИМЕНИ АДМИНИСТРАТОРА !!!" -ForegroundColor Red
    pause
    exit
}

# Определяем путь к папке проекта и к папке PHP внутри него
$projectDir = Get-Location
$localPhpDir = Join-Path $projectDir "php"

if (-not (Test-Path (Join-Path $localPhpDir "php.exe"))) {
    Write-Host "Ошибка: Файл $localPhpDir\php.exe не найден!" -ForegroundColor Red
    pause
    exit
}

Write-Host "Регистрируем локальный PHP в системе..." -ForegroundColor Cyan

# Получаем текущий PATH
$oldPath = [Environment]::GetEnvironmentVariable("Path", "Machine")

# Добавляем путь, если его еще нет
if ($oldPath -notlike "*$localPhpDir*") {
    $newPath = $oldPath.TrimEnd(';') + ";" + $localPhpDir
    [Environment]::SetEnvironmentVariable("Path", $newPath, "Machine")
    Write-Host "[+] Успешно добавлено: $localPhpDir" -ForegroundColor Green
} else {
    Write-Host "[!] Этот путь уже есть в PATH" -ForegroundColor Yellow
}

Write-Host "Готово. Теперь команда 'php' будет использовать версию из папки проекта." -ForegroundColor White
pause