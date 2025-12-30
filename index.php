<?php
// --- CORS & HEADERS ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;

$baseDir = __DIR__;
$configDir = $baseDir . DIRECTORY_SEPARATOR . 'config';
$configFile = $configDir . DIRECTORY_SEPARATOR . 'modules.json';
$sysinfoFile = $configDir . DIRECTORY_SEPARATOR . 'sysinfo.txt';
$gitPath = $baseDir . DIRECTORY_SEPARATOR . 'git' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'git.exe';

$uri = $_SERVER['REQUEST_URI'];

// API: Системная информация (с глубоким логированием)
if (strpos($uri, '/api/sysinfo') !== false) {
    $data = "Ожидание данных...";
    
    // Запись в лог ошибок PHP (можно увидеть в логах сервера)
    error_log("API: Checking for file at: " . $sysinfoFile);

    if (file_exists($sysinfoFile)) {
        $content = file_get_contents($sysinfoFile);
        if (!empty(trim($content))) {
            $data = $content;
            error_log("API: Data successfully read from file.");
        } else {
            $data = "Файл найден, но он пуст.";
            error_log("API: File exists but is EMPTY.");
        }
    } else {
        error_log("API: File NOT FOUND.");
    }
    
    header('Content-Type: application/json');
    echo json_encode(['data' => $data]);
    exit;
}

// API: Статус модулей
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

// API: Сохранение настроек
if (strpos($uri, '/api/save') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    $config[$input['module']] = $input['settings'];
    if (!is_dir($configDir)) mkdir($configDir);
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    file_put_contents($baseDir . '/reload_signal', '1');
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Настройки сохранены']);
    exit;
}

// API: Обновление
if (strpos($uri, '/api/update') !== false) {
    $cmd = '"' . $gitPath . '" pull 2>&1';
    $output = shell_exec($cmd);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Git update done', 'output' => $output]);
    exit;
}

// Рендер страницы
if ($uri == '/' || $uri == '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    echo file_get_contents($baseDir . '/index.html');
    exit;
}