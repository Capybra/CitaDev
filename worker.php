<?php
/**
 * CitaDev Worker Core + Debug Logger
 */
$baseDir = __DIR__;
$debugLog = $baseDir . DIRECTORY_SEPARATOR . 'worker_debug.log';

// Функция логирования
function logWorker($message) {
    global $debugLog;
    $time = date('Y-m-d H:i:s');
    $entry = "[$time] $message" . PHP_EOL;
    echo $entry; // Для отображения в консоли debug_worker.bat
    file_put_contents($debugLog, $entry, FILE_APPEND);
}

logWorker("--- WORKER STARTUP ---");

// Настройка окружения
$localBin = $baseDir . '\php;' . $baseDir . '\git\bin;';
putenv("PATH=" . $localBin . getenv("PATH"));

// Папки
$configDir = $baseDir . DIRECTORY_SEPARATOR . 'config';
$sysinfoPath = $configDir . DIRECTORY_SEPARATOR . 'sysinfo.txt';
if (!is_dir($configDir)) mkdir($configDir);

require_once $baseDir . '/modules/BaseModule.php';
$configFile = $configDir . DIRECTORY_SEPARATOR . 'modules.json';

while (true) {
    logWorker("Starting new cycle...");
    
    // Загрузка свежего конфига
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    
    // Поиск и запуск модулей
    $moduleFiles = glob($baseDir . '/modules/*.php');
    foreach ($moduleFiles as $filename) {
        if (basename($filename) === 'BaseModule.php') continue;
        
        require_once $filename;
        $className = pathinfo($filename, PATHINFO_FILENAME);
        
        if (class_exists($className)) {
            $modConfig = $config[$className] ?? ['enabled' => false];
            $module = new $className($modConfig);
            
            if ($module->isEnabled()) {
                logWorker("Running module: $className");
                try {
                    $module->run();
                    
                    // Проверка результата для SystemInfo
                    if ($className === 'SystemInfo') {
                        if (file_exists($sysinfoPath)) {
                            $size = filesize($sysinfoPath);
                            logWorker("SystemInfo: File updated, size: $size bytes");
                        } else {
                            logWorker("SystemInfo ERROR: File sysinfo.txt was NOT created!");
                        }
                    }
                } catch (Exception $e) {
                    logWorker("CRITICAL ERROR in $className: " . $e->getMessage());
                }
            }
        }
    }

    // Сигнал перезагрузки
    if (file_exists($baseDir . '/reload_signal')) {
        logWorker("Reload signal received.");
        unlink($baseDir . '/reload_signal');
    }

    logWorker("Cycle finished. Sleeping 10s...");
    sleep(10);
}