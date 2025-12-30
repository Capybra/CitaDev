<?php
/**
 * Core API Server
 * Адрес: http://localhost:2712
 */

// 1. Настройка CORS (чтобы клиент мог подключаться с любого ПК в сети ZeroTier)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Обработка Preflight-запроса браузера
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// 2. Параметры путей
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$configDir = __DIR__ . '/config';
$configFile = $configDir . '/modules.json';
$modulesDir = __DIR__ . '/modules';
$repoUrl = "https://github.com/Capybra/CitaDev.git";

// Создаем директории, если их нет
if (!is_dir($configDir)) mkdir($configDir, 0777, true);
if (!is_dir($modulesDir)) mkdir($modulesDir, 0777, true);

/**
 * Загрузка текущего конфига модулей
 */
function loadConfig($path) {
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?? [];
}

// --- 3. РОУТИНГ ---

// [GET] /api/status - Состояние системы и модулей
if ($uri == '/api/status' && $method == 'GET') {
    $result = [];
    $savedSettings = loadConfig($configFile);

    // Подключаем базовый класс, чтобы сканирование не упало
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

// [POST] /api/save - Сохранение настроек модуля
if ($uri == '/api/save' && $method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $moduleName = $data['module'] ?? null;
    $newSettings = $data['settings'] ?? null;

    if ($moduleName && $newSettings) {
        $allConfigs = loadConfig($configFile);
        $allConfigs[$moduleName] = $newSettings;
        
        file_put_contents($configFile, json_encode($allConfigs, JSON_PRETTY_PRINT));
        
        // Сигнал воркеру перечитать конфиг
        file_put_contents(__DIR__ . '/reload_signal', '1');
        
        echo json_encode(['status' => 'success', 'message' => "Настройки $moduleName обновлены"]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Неверные данные']);
    }
    exit;
}

// [GET] /api/update - Самообновление из GitHub
if ($uri == '/api/update') {
    echo json_encode([
        'status' => 'pending',
        'message' => 'Процесс обновления запущен. Сервер будет перезагружен.'
    ]);

    // Выполняем обновление в фоновом режиме, чтобы успеть отдать ответ клиенту
    // 1. Сброс локальных изменений (reset)
    // 2. Git Pull из твоего репозитория
    // 3. Убийство процессов PHP (батник их поднимет)
    $cmd = 'start /b cmd /c "cd /d ' . __DIR__ . ' && git reset --hard HEAD && git pull origin master && timeout /t 3 && taskkill /F /IM php.exe /T"';
    
    pclose(popen($cmd, "r"));
    exit;
}

// Дефолтный ответ
echo json_encode([
    'server' => 'CitaDev Core',
    'repo' => $repoUrl,
    'php_version' => PHP_VERSION,
    'os' => PHP_OS
]);