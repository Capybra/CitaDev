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

// --- АВТОЗАГРУЗКА В РЕЕСТР ---
// Прописываем start.bat в автозагрузку пользователя
$batPath = $baseDir . '\\start.bat';
$regCmd = 'reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "CitaDevServer" /t REG_SZ /d "\"'.$batPath.'\"" /f';
shell_exec($regCmd);

// --- РОУТИНГ ---

// Состояние модулей
if ($uri == '/api/status') {
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

// Сохранение настроек
if ($uri == '/api/save') {
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

// Обновление из Git (ветка main)
if ($uri == '/api/update') {
    // 2>&1 позволяет поймать ошибки в переменную $output
    $output = shell_exec('git reset --hard HEAD && git pull origin main 2>&1');

    echo json_encode([
        'status' => 'success',
        'output' => $output ?: 'Команда выполнена',
        'message' => 'Система перезагрузится через 3 секунды.'
    ]);

    // Суицид процессов для перезагрузки через start.bat
    pclose(popen('start /b cmd /c "timeout /t 3 && taskkill /F /IM php.exe /T"', "r"));
    exit;
}

echo json_encode(['status' => 'online', 'system' => 'CitaDev Core']);