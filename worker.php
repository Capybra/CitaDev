<?php
/**
 * CitaDev Worker Core
 */
set_time_limit(0); // Убираем лимит времени выполнения
echo "[".date('H:i:s')."] Worker Core Started...\n";

$baseDir = __DIR__;
$modulesDir = $baseDir . '/modules';
$configFile = $baseDir . '/config/modules.json';

// Подгружаем базовый класс один раз
if (file_exists($modulesDir . '/BaseModule.php')) {
    require_once $modulesDir . '/BaseModule.php';
}

while (true) {
    // 1. Проверка сигнала на обновление настроек
    if (file_exists($baseDir . '/reload_signal')) {
        echo "[INFO] Настройки изменены клиентом. Применяю...\n";
        unlink($baseDir . '/reload_signal');
    }

    // 2. Чтение конфигурации
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

    // 3. Запуск активных модулей
    foreach (glob($modulesDir . '/*.php') as $file) {
        $className = basename($file, '.php');
        if ($className === 'BaseModule') continue;

        // Проверяем, включен ли модуль в настройках
        if (isset($config[$className]['enabled']) && $config[$className]['enabled'] == true) {
            try {
                require_once $file;
                if (class_exists($className)) {
                    $module = new $className();
                    $module->setConfig($config[$className]);
                    
                    // Выполнение задачи модуля
                    $module->run(); 
                }
            } catch (Error $e) {
                echo "[FATAL ERROR] В модуле $className: " . $e->getMessage() . "\n";
            } catch (Exception $e) {
                echo "[ERROR] В модуле $className: " . $e->getMessage() . "\n";
            }
        }
    }

    // Пауза 2 секунды, чтобы не перегружать CPU
    sleep(2);
}