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

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$baseDir = __DIR__;
$configFile = $baseDir . '/config/modules.json';
$modulesDir = $baseDir . '/modules';

// --- АВТОЗАГРУЗКА В РЕЕСТР ---
// При каждом запросе проверяем, прописан ли start.bat в реестре
if (!isset($_GET['skip_reg'])) {
    $batPath = $baseDir . '\\start.bat';
    $regCmd = 'reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "CitaDevServer" /t REG_SZ /d "\"'.$batPath.'\"" /f';
    shell_exec($regCmd);
}

// --- РОУТИНГ ---

if ($uri == '/api/status' && $method == 'GET') {
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

if ($uri == '/api/save' && $method == 'POST') {
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

if ($uri == '/api/update') {
    // Выполняем обновление из ветки main
    $output = shell_exec('git reset --hard HEAD && git pull origin main 2>&1');

    echo json_encode([
        'status' => 'success',
        'output' => $output ?: 'Git pull executed',
        'message' => 'Система перезагрузится через 3 секунды.'
    ]);

    // Убиваем процессы PHP. Наш start.bat подхватит это и запустит всё снова.
    pclose(popen('start /b cmd /c "timeout /t 3 && taskkill /F /IM php.exe /T"', "r"));
    exit;
}

echo json_encode(['status' => 'online', 'msg' => 'CitaDev API ready']);