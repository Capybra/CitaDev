<?php
// modules/SystemInfo.php
require_once 'BaseModule.php';

class SystemInfo extends BaseModule {
    
    // Описываем настройки, чтобы клиент знал, что рисовать
    public function getConfigSchema(): array {
        return [
            'interval' => [
                'type' => 'number', 
                'default' => 5, 
                'label' => 'Интервал обновления (сек)'
            ],
            'message' => [
                'type' => 'text', 
                'default' => 'System OK', 
                'label' => 'Префикс лога'
            ]
        ];
    }

    public function run() {
        // Логика модуля
        $interval = $this->config['interval'] ?? 5;
        $msg = $this->config['message'] ?? 'Default';
        
        echo "[{$msg}] Модуль работает... Время: " . date('H:i:s') . "\n";
        
        // В реальном модуле тут не должно быть sleep, если мы хотим асинхронность,
        // но для простой версии worker.php это допустимо.
        sleep($interval); 
    }
}