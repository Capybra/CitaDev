<?php
// --- НАСТРОЙКИ БЕЗОПАСНОСТИ (CORS) ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// --- ОПРЕДЕЛЕНИЕ ПУТЕЙ ---
$baseDir = __DIR__;
$configDir = $baseDir . DIRECTORY_SEPARATOR . 'config';
$configFile = $configDir . DIRECTORY_SEPARATOR . 'modules.json';
$sysinfoFile = $configDir . DIRECTORY_SEPARATOR . 'sys_info_cache.txt';
$gitPath = $baseDir . DIRECTORY_SEPARATOR . 'git' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'git.exe';

$uri = $_SERVER['REQUEST_URI'];

// 1. API: Системная информация
if (strpos($uri, '/api/sysinfo') !== false) {
    $data = "Ожидание данных...";
    if (file_exists($sysinfoFile)) {
        $content = file_get_contents($sysinfoFile);
        if (!empty(trim($content))) {
            $data = $content;
        } else {
            $data = "Файл найден, но пуст (воркер еще не собрал данные).";
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['data' => $data]);
    exit;
}

// 2. API: Статус и конфигурация модулей
if (strpos($uri, '/api/status') !== false) {
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    $modules = [];
    
    foreach (glob($baseDir . '/modules/*.php') as $filename) {
        if (basename($filename) === 'BaseModule.php') continue;
        require_once $filename;
        $className = pathinfo($filename, PATHINFO_FILENAME);
        if (class_exists($className)) {
            $mod = new $className($config[$className] ?? []);
            $modules[] = [
                'module' => $className,
                'is_active' => $config[$className]['enabled'] ?? false,
                'schema' => $mod->getConfigSchema(),
                'current_settings' => $config[$className] ?? []
            ];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($modules);
    exit;
}

// 3. API: Сохранение настроек
if (strpos($uri, '/api/save') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    
    $config[$input['module']] = $input['settings'];
    if (!is_dir($configDir)) mkdir($configDir, 0777, true);
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    
    // Сигнал воркеру на перезагрузку
    file_put_contents($baseDir . DIRECTORY_SEPARATOR . 'reload_signal', '1');
    
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Настройки модуля ' . $input['module'] . ' успешно сохранены']);
    exit;
}

// 4. API: Обновление (Git Pull)
if (strpos($uri, '/api/update') !== false) {
    header('Content-Type: application/json');
    if (!file_exists($gitPath)) {
        echo json_encode(['status' => 'error', 'output' => 'Git не найден: ' . $gitPath]);
        exit;
    }
    $command = "cd /d \"$baseDir\" && \"$gitPath\" pull 2>&1";
    $output = shell_exec($command);
    echo json_encode([
        'status' => 'success',
        'output' => $output ?: 'Команда выполнена успешно (вывод пуст).'
    ]);
    exit;
}

// 5. Отдача интерфейса (HTML)
if ($uri == '/' || $uri == '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    if (file_exists($baseDir . '/index.html')) {
        echo file_get_contents($baseDir . '/index.html');
    } else {
        echo "Файл index.html не найден в " . $baseDir;
    }
    exit;
}