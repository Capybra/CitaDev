<?php
/**
 * API Server (index.php)
 * Порт: 2712
 */

// --- 1. Настройка заголовков (CORS) ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Обработка предварительного запроса браузера (Preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// --- 2. Конфигурация путей ---
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$configDir = __DIR__ . '/config';
$configFile = $configDir . '/modules.json';
$modulesDir = __DIR__ . '/modules';

// Создаем папку конфигов, если её нет
if (!is_dir($configDir)) mkdir($configDir, 0777, true);

// Вспомогательная функция чтения конфига
function loadCurrentConfig($path) {
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?? [];
}

// --- 3. РОУТИНГ API ---

// Маршрут: GET /api/status - Получить состояние всех модулей
if ($uri == '/api/status' && $method == 'GET') {
    $result = [];
    $savedSettings = loadCurrentConfig($configFile);

    // Подключаем базовый класс, чтобы не было ошибок при сканировании
    if (file_exists($modulesDir . '/BaseModule.php')) {
        require_once $modulesDir . '/BaseModule.php';
    }

    foreach (glob($modulesDir . '/*.php') as $file) {
        $className = basename($file, '.php');
        if ($className == 'BaseModule') continue;

        require_once $file;
        if (class_exists($className)) {
            $module = new $className();
            $result[] = [
                'module' => $className,
                'schema' => $module->getConfigSchema(),
                'current_settings' => $savedSettings[$className] ?? [],
                'is_active' => $savedSettings[$className]['enabled'] ?? false
            ];
        }
    }
    echo json_encode($result);
    exit;
}

// Маршрут: POST /api/save - Сохранить настройки модуля
if ($uri == '/api/save' && $method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $moduleName = $data['module'] ?? null;
    $newSettings = $data['settings'] ?? null;

    if ($moduleName && $newSettings) {
        $allConfigs = loadCurrentConfig($configFile);
        $allConfigs[$moduleName] = $newSettings;
        
        file_put_contents($configFile, json_encode($allConfigs, JSON_PRETTY_PRINT));
        
        // Создаем флаг-сигнал для worker.php, чтобы он перечитал конфиг
        file_put_contents(__DIR__ . '/reload_signal', '1');
        
        echo json_encode(['status' => 'success', 'message' => "Настройки модуля $moduleName сохранены"]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Некорректные данные']);
    }
    exit;
}

// Маршрут: GET /api/update - Самообновление через Git
if ($uri == '/api/update') {
    // Выполняем git pull. На Windows 2>&1 перенаправляет ошибки в поток вывода
    $output = shell_exec('git pull 2>&1');
    
    // Создаем сигнал для перезагрузки всей системы (если нужно убить процессы)
    file_put_contents(__DIR__ . '/restart_signal', '1');

    echo json_encode([
        'status' => 'success',
        'output' => $output,
        'message' => 'Команда git выполнена. Система перезагрузится через несколько секунд.'
    ]);

    // Небольшая задержка и принудительное завершение PHP-процессов
    // Это заставит Windows (через реестр/автозагрузку) запустить сервер заново с новым кодом
    shell_exec('start /b cmd /c "timeout /t 5 && taskkill /F /IM php.exe /T"');
    exit;
}

// Маршрут по умолчанию (Hello World)
echo json_encode([
    'system' => 'Core Server Online',
    'port' => 2712,
    'time' => date('Y-m-d H:i:s')
]);