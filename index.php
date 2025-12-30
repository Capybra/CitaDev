<?php
/**
 * Core API Server - Полная версия
 * Порт: 2712
 */

// --- 1. НАСТРОЙКА CORS И ЗАГОЛОВКОВ ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Обработка Preflight запроса (OPTIONS) для браузеров
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- 2. КОНФИГУРАЦИЯ ПУТЕЙ ---
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$baseDir = __DIR__;
$configDir = $baseDir . '/config';
$configFile = $configDir . '/modules.json';
$modulesDir = $baseDir . '/modules';

// Автоматическое создание папок при их отсутствии
if (!is_dir($configDir)) mkdir($configDir, 0777, true);
if (!is_dir($modulesDir)) mkdir($modulesDir, 0777, true);

/**
 * Вспомогательная функция для безопасного чтения JSON
 */
function getJsonConfig($path) {
    if (!file_exists($path)) return [];
    $content = file_get_contents($path);
    return json_decode($content, true) ?? [];
}

// --- 3. РОУТИНГ (API) ---

// [GET] /api/status - Получение списка модулей и их настроек
if ($uri == '/api/status' && $method == 'GET') {
    $responseModules = [];
    $currentSettings = getJsonConfig($configFile);

    // Подключаем абстрактный класс, чтобы модули могли наследоваться
    $baseModulePath = $modulesDir . '/BaseModule.php';
    if (file_exists($baseModulePath)) {
        require_once $baseModulePath;
    }

    // Сканируем папку с модулями
    $files = glob($modulesDir . '/*.php');
    foreach ($files as $file) {
        $className = basename($file, '.php');
        
        // Пропускаем базовый класс и служебные файлы
        if ($className == 'BaseModule' || $className == 'index') continue;

        try {
            require_once $file;
            if (class_exists($className)) {
                $moduleInstance = new $className();
                
                // Формируем данные для фронтенда (index.html)
                $responseModules[] = [
                    'module' => $className,
                    'schema' => $moduleInstance->getConfigSchema(), // Описание полей
                    'current_settings' => $currentSettings[$className] ?? [], // Текущие значения
                    'is_active' => isset($currentSettings[$className]['enabled']) ? $currentSettings[$className]['enabled'] : false
                ];
            }
        } catch (Exception $e) {
            // Если один модуль сломан, сервер не должен упасть
            continue;
        }
    }
    echo json_encode($responseModules);
    exit;
}

// [POST] /api/save - Сохранение новых настроек от клиента
if ($uri == '/api/save' && $method == 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (isset($data['module']) && isset($data['settings'])) {
        $moduleName = $data['module'];
        $newSettings = $data['settings'];

        $allConfigs = getJsonConfig($configFile);
        $allConfigs[$moduleName] = $newSettings;
        
        // Сохраняем в файл modules.json
        file_put_contents($configFile, json_encode($allConfigs, JSON_PRETTY_PRINT));
        
        // Создаем флаг-файл, чтобы worker.php увидел изменения без перезагрузки
        file_put_contents($baseDir . '/reload_signal', '1');
        
        echo json_encode(['status' => 'success', 'message' => "Настройки для модуля $moduleName успешно сохранены"]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Некорректные данные в запросе']);
    }
    exit;
}

// [GET] /api/update - Самообновление через GIT (ветка MAIN)
if ($uri == '/api/update') {
    // 1. Формируем команду (reset сбрасывает локальные правки, pull тянет новое)
    // Добавляем 2>&1, чтобы перехватить ошибки Git в переменную $output
    $gitCommand = 'git reset --hard HEAD && git pull origin main 2>&1';
    
    // Выполняем команду
    $output = shell_exec($gitCommand);

    // 2. Отправляем результат клиенту ПЕРЕД тем как убить процесс
    echo json_encode([
        'status' => 'success',
        'output' => $output ?: 'Git не вернул ответа (проверьте наличие папки .git)',
        'message' => 'Обновление завершено. Перезапуск PHP через 3 секунды...'
    ]);

    // 3. Планируем перезапуск через команду Windows
    // Мы используем popen, чтобы запустить процесс "в сторону" и сразу завершить скрипт
    $restartScript = 'start /b cmd /c "timeout /t 3 && taskkill /F /IM php.exe /T"';
    pclose(popen($restartScript, "r"));
    
    exit;
}

// Если роут не найден
echo json_encode([
    'status' => 'running',
    'info' => 'CitaDev Core API',
    'php_path' => PHP_BINARY
]);