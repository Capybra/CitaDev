<?php
// worker.php
$phpPath = "C:\php\php.exe"; // Тот же путь, что в bat-файле
echo "Worker started...\n";

$processes = [];

while (true) {
    $configFile = __DIR__ . '/config/modules.json';
    $configs = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

    foreach ($configs as $moduleName => $settings) {
        if (!($settings['enabled'] ?? false)) continue;

        // Если процесс модуля уже запущен - пропускаем, если нет - стартуем
        // Для простоты в V1: просто запускаем скрипт-обертку для модуля
        // Но сейчас реализуем через инклюд, раз это "легкие" задачи:
        
        $moduleFile = __DIR__ . "/modules/{$moduleName}.php";
        if (file_exists($moduleFile)) {
            require_once $moduleFile;
            $module = new $moduleName();
            $module->setConfig($settings);
            
            echo "Executing: $moduleName...\n";
            $module->run(); // Выполняем одну итерацию логики модуля
        }
    }

    // Проверка самообновления (если пришел сигнал из API)
    if (file_exists(__DIR__ . '/reload_signal')) {
        echo "Reloading configurations...\n";
        unlink(__DIR__ . '/reload_signal');
    }

    sleep(2); // Задержка цикла ядра
}