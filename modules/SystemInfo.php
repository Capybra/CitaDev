<?php
require_once 'BaseModule.php';

class SystemInfo extends BaseModule {

    public function getConfigSchema(): array {
        return [
            'info_level' => [
                'type' => 'text',
                'default' => 'full',
                'label' => 'Детализация (short/full)'
            ]
        ];
    }

    public function run() {
        echo "[SystemInfo] Сбор данных о железе...\n";

        // Собираем данные через WMIC (стандартная утилита Windows)
        $cpu = $this->getCmdOutput('wmic cpu get name');
        $gpu = $this->getCmdOutput('wmic path win32_VideoController get name');
        $ram = $this->getCmdOutput('wmic computersystem get totalphysicalmemory');

        // Преобразуем RAM в ГБ
        $ramGb = 0;
        if (preg_match('/\d+/', $ram, $matches)) {
            $ramGb = round($matches[0] / (1024 * 1024 * 1024), 2);
        }

        $output = "--- Комплектующие системы ---\n";
        $output .= "Процессор: " . trim(str_replace('Name', '', $cpu)) . "\n";
        $output .= "Видеокарта: " . trim(str_replace('Name', '', $gpu)) . "\n";
        $output .= "Оперативная память: " . $ramGb . " GB\n";
        $output .= "----------------------------\n";

        echo $output;

        // Сохраняем результат в файл, чтобы клиент мог его прочитать (опционально)
        file_put_contents(__DIR__ . '/../config/sys_info_cache.txt', $output);
    }

    private function getCmdOutput($cmd) {
        $out = shell_exec($cmd);
        return $out ? trim($out) : 'Н/Д';
    }
}