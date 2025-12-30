<?php
// --- НАСТРОЙКИ БЕЗОПАСНОСТИ (CORS) ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// --- ПУТИ ---
$baseDir = __DIR__;
$configDir = $baseDir . '/config';
$configFile = $configDir . '/modules.json';
$gitPath = $baseDir . '\git\bin\git.exe'; // Относительный путь к портативному Git

// Автозагрузка сервера (скрытая)
$vbsPath = $baseDir . '\\silent_start.vbs';
$regCmd = 'reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "CitaDevServer" /t REG_SZ /d "wscript.exe \"'.$vbsPath.'\"" /f';
shell_exec($regCmd);

$uri = $_SERVER['REQUEST_URI'];

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
    
    // Сигнал воркеру на обновление настроек
    file_put_contents($baseDir . '/reload_signal', '1');
    
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Настройки модуля ' . $input['module'] . ' сохранены']);
    exit;
}

// API: Системная информация (Исправлено чтение файла)
if (strpos($uri, '/api/sysinfo') !== false) {
    $logFile = $configDir . '/sysinfo.txt';
    $data = "Ожидание данных...";

    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if (!empty(trim($content))) {
            $data = $content;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['data' => $data]);
    exit;
}

// API: Обновление через Git
if (strpos($uri, '/api/update') !== false) {
    $cmd = '"' . $gitPath . '" pull 2>&1';
    $output = shell_exec($cmd);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Обновление завершено', 'output' => $output]);
    exit;
}

// Отдача фронтенда
if ($uri == '/' || $uri == '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    echo file_get_contents($baseDir . '/index.html');
    exit;
}