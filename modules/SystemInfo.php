<?php
require_once 'BaseModule.php';

class SystemInfo extends BaseModule {

    /**
     * Описание схемы настроек для генерации полей в интерфейсе index.html
     */
    public function getConfigSchema(): array {
        return [
            'info_level' => [
                'type' => 'text',
                'default' => 'full',
                'label' => 'Детализация (short/full)'
            ]
        ];
    }

    /**
     * Основной метод, который вызывается worker.php каждые 2 секунды
     */
    public function run() {
        echo "[SystemInfo] Сбор данных о железе через PowerShell...\n";

        // Получаем данные через современные команды PowerShell (Get-CimInstance)
        $cpu = $this->getPsOutput('(Get-CimInstance Win32_Processor).Name');
        $gpu = $this->getPsOutput('(Get-CimInstance Win32_VideoController).Name');
        $ramRaw = $this->getPsOutput('(Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory');

        // Преобразуем ОЗУ из байтов в ГБ
        $ramGb = 0;
        if (is_numeric($ramRaw)) {
            $ramGb = round($ramRaw / (1024 ** 3), 2);
        }

        // Формируем текст вывода
        $output = "--- Комплектующие системы ---\n";
        $output .= "Процессор: " . ($cpu ?: 'Не определен') . "\n";
        $output .= "Видеокарта: " . ($gpu ?: 'Не определена') . "\n";
        $output .= "Оперативная память: " . $ramGb . " GB\n";
        $output .= "Время обновления: " . date('H:i:s') . "\n";
        $output .= "----------------------------\n";

        // Вывод в консоль воркера
        echo $output;

        // Сохраняем в кэш-файл в папку config, чтобы API (index.php) мог отдать это клиенту
        $cachePath = __DIR__ . '/../config/sys_info_cache.txt';
        file_put_contents($cachePath, $output);
    }

    /**
     * Вспомогательный метод для выполнения команд PowerShell
     */
    private function getPsOutput($command) {
        // Запускаем PowerShell без профиля и с обходом политики выполнения
        $fullCmd = "powershell.exe -NoProfile -ExecutionPolicy Bypass -Command \"$command\"";
        $result = shell_exec($fullCmd);
        return $result ? trim($result) : null;
    }
}