<?php
/**
 * CitaDev Worker Core
 */
$baseDir = __DIR__;
$debugLog = $baseDir . DIRECTORY_SEPARATOR . 'worker_debug.log';

function logWorker($message) {
    global $debugLog;
    $entry = "[" . date('Y-m-d H:i:s') . "] $message" . PHP_EOL;
    echo $entry;
    file_put_contents($debugLog, $entry, FILE_APPEND);
}

logWorker("--- WORKER RELOADED ---");

// PATH
$localBin = $baseDir . '\php;' . $baseDir . '\git\bin;';
putenv("PATH=" . $localBin . getenv("PATH"));

// СИНХРОНИЗИРОВАНО: Путь и Имя файла
$configDir = $baseDir . DIRECTORY_SEPARATOR . 'config';
$sysinfoPath = $configDir . DIRECTORY_SEPARATOR . 'sys_info_cache.txt';

if (!is_dir($configDir)) {
    mkdir($configDir, 0777, true);
    logWorker("Config dir created manually.");
}

require_once $baseDir . '/modules/BaseModule.php';
$configFile = $configDir . DIRECTORY_SEPARATOR . 'modules.json';

while (true) {
    logWorker("New Cycle...");
    
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
    } else {
        $config = [];
    }

    foreach (glob($baseDir . '/modules/*.php') as $filename) {
        if (basename($filename) === 'BaseModule.php') continue;
        
        require_once $filename;
        $className = pathinfo($filename, PATHINFO_FILENAME);
        
        if (class_exists($className)) {
            $modConfig = $config[$className] ?? ['enabled' => false];
            $module = new $className($modConfig);
            
            if ($module->isEnabled()) {
                logWorker("Running: $className");
                $module->run();
                
                // Проверка конкретно для SystemInfo
                if ($className === 'SystemInfo') {
                    if (file_exists($sysinfoPath)) {
                        logWorker("SUCCESS: File exists. Size: " . filesize($sysinfoPath));
                    } else {
                        logWorker("CRITICAL: Module SystemInfo finished but $sysinfoPath NOT FOUND!");
                    }
                }
            }
        }
    }

    if (file_exists($baseDir . '/reload_signal')) {
        unlink($baseDir . '/reload_signal');
        logWorker("Config reloaded by signal.");
    }

    sleep(10);
}