# Получаем путь к папке, где лежит сам скрипт
$projectDir = Get-Location
$localPhpDir = Join-Path $projectDir "php"
$localGitDir = Join-Path $projectDir "git\cmd"

Write-Host "--- Настройка портативного окружения CitaDev ---" -ForegroundColor Cyan

# Проверка наличия папок
if (!(Test-Path $localPhpDir) -or !(Test-Path $localGitDir)) {
    Write-Host "Ошибка: Папки php или git\cmd не найдены в текущей директории!" -ForegroundColor Red
    pause
    exit
}

# Получаем текущий PATH
$oldPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
$currentPathArray = $oldPath -split ';'

# Добавляем пути, если их еще нет
$newPaths = @($localPhpDir, $localGitDir)
$updated = $false

foreach ($path in $newPaths) {
    if ($currentPathArray -notcontains $path) {
        $oldPath = $oldPath.TrimEnd(';') + ";" + $path
        $updated = $true
        Write-Host "[+] Добавлено в PATH: $path" -ForegroundColor Green
    }
}

if ($updated) {
    [Environment]::SetEnvironmentVariable("Path", $oldPath, "Machine")
    Write-Host "PATH успешно обновлен." -ForegroundColor Yellow
} else {
    Write-Host "Пути уже были настроены ранее." -ForegroundColor Gray
}

Write-Host "Настройка завершена. Теперь можно запускать start.bat." -ForegroundColor White
pause