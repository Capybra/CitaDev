<?php
/**
 * CitaDev Worker Core
 * Работает в фоновом режиме, управляет модулями.
 */

$baseDir = __DIR__;

// --- НАСТРОЙКА ОКРУЖЕНИЯ (Относительные пути) ---
// Добавляем локальные папки php и git в PATH текущего процесса
$localBin = $baseDir . '\php;' . $baseDir . '\git\bin;' . $baseDir . '\git\cmd;';
putenv("PATH=" . $localBin . getenv("PATH"));

require_once $baseDir . '/modules/BaseModule.php';

echo "[" . date('H:i:s') . "] Worker Core Started (Relative Paths Active)\n";

$modules = [];
$configFile = $baseDir . '/config/modules.json';

function loadModules($configFile, $baseDir) {
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    $instances = [];
    
    foreach (glob($baseDir . '/modules/*.php') as $filename) {
        if (basename($filename) === 'BaseModule.php') continue;
        require_once $filename;
        $className = pathinfo($filename, PATHINFO_FILENAME);
        
        if (class_exists($className)) {
            $modConfig = $config[$className] ?? ['enabled' => false];
            $instances[] = new $className($modConfig);
        }
    }
    return $instances;
}

// Начальная загрузка
$modules = loadModules($configFile, $baseDir);

while (true) {
    // Проверка сигнала на перезагрузку конфига (если сохранили в API)
    if (file_exists($baseDir . '/reload_signal')) {
        echo "[" . date('H:i:s') . "] Reloading module configurations...\n";
        $modules = loadModules($configFile, $baseDir);
        unlink($baseDir . '/reload_signal');
    }

    foreach ($modules as $module) {
        if ($module->isEnabled()) {
            try {
                $module->run();
            } catch (Exception $e) {
                echo "Error in module " . get_class($module) . ": " . $e->getMessage() . "\n";
            }
        }
    }

    // Пауза между итерациями (например, 5 секунд)
    sleep(5);
}