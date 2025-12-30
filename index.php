<?php
/**
 * CitaDev Core API
 * Port: 2712
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;

$baseDir = __DIR__;
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$configFile = $baseDir . '/config/modules.json';
$modulesDir = $baseDir . '/modules';
$cacheFile  = $baseDir . '/config/sys_info_cache.txt';

// --- АВТОЗАГРУЗКА В РЕЕСТР ---
$batPath = $baseDir . '\\start.bat';
$regCmd = 'reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "CitaDevServer" /t REG_SZ /d "\"'.$batPath.'\"" /f';
shell_exec($regCmd);

// --- РОУТИНГ ---

// 1. Получение статуса модулей
if ($uri == '/api/status' && $_SERVER['REQUEST_METHOD'] == 'GET') {
    $result = [];
    $saved = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    if (file_exists($modulesDir . '/BaseModule.php')) require_once $modulesDir . '/BaseModule.php';

    foreach (glob($modulesDir . '/*.php') as $file) {
        $className = basename($file, '.php');
        if ($className == 'BaseModule') continue;
        require_once $file;
        if (class_exists($className)) {
            $module = new $className();
            $result[] = [
                'module' => $className,
                'schema' => $module->getConfigSchema(),
                'current_settings' => $saved[$className] ?? [],
                'is_active' => $saved[$className]['enabled'] ?? false
            ];
        }
    }
    echo json_encode($result);
    exit;
}

// 2. Сохранение настроек
if ($uri == '/api/save' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
        $all = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
        $all[$data['module']] = $data['settings'];
        if (!is_dir(dirname($configFile))) mkdir(dirname($configFile), 0777, true);
        file_put_contents($configFile, json_encode($all, JSON_PRETTY_PRINT));
        file_put_contents($baseDir . '/reload_signal', '1');
        echo json_encode(['status' => 'success', 'message' => 'Настройки сохранены']);
    }
    exit;
}

// 3. Получение данных о системе (для модуля SystemInfo)
if ($uri == '/api/sysinfo') {
    $content = file_exists($cacheFile) ? file_get_contents($cacheFile) : "Ожидание данных от воркера...";
    echo json_encode(['data' => $content]);
    exit;
}

// 4. Обновление через Git (ветка main)
if ($uri == '/api/update') {
    $output = shell_exec('git reset --hard HEAD && git pull origin main 2>&1');
    echo json_encode([
        'status' => 'success',
        'output' => $output ?: 'Git pull executed',
        'message' => 'Система перезагрузится...'
    ]);

    // Используем скрипт, который мягко убивает процессы без конфликта дескрипторов
    $killCmd = 'timeout /t 3 && taskkill /F /IM php.exe /T';
    file_put_contents($baseDir . '/kill.bat', $killCmd);
    
    // Запускаем bat-киллер отдельно от текущего процесса
    pclose(popen('start /b "" cmd /c "' . $baseDir . '\\kill.bat"', "r"));
    exit;
}