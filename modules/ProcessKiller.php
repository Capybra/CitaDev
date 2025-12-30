<?php
require_once 'BaseModule.php';

class ProcessKiller extends BaseModule {

    /**
     * Схема настроек: поле ввода для списка процессов через запятую
     */
    public function getConfigSchema(): array {
        return [
            'process_list' => [
                'type' => 'text',
                'default' => 'notepad.exe, calc.exe',
                'label' => 'Список процессов через запятую (например: discord.exe, telegram.exe)'
            ]
        ];
    }

    public function run() {
            $rawList = $this->config['process_list'] ?? '';
            if (empty($rawList)) return;

            $processes = array_map('trim', explode(',', $rawList));

            foreach ($processes as $proc) {
                if (empty($proc)) continue;

                // Добавляем >nul 2>&1, чтобы полностью подавить вывод в консоль
                // Это решает проблему с дублированием дескрипторов (handle)
                $cmd = "taskkill /F /IM \"$proc\" /T >nul 2>&1";
                
                // Используем exec вместо shell_exec для таких случаев
                exec($cmd);
                
                // Так как вывод подавлен, мы просто логируем попытку в консоль воркера
                echo "[ProcessKiller] Попытка завершить: $proc\n";
            }
        }
    }
