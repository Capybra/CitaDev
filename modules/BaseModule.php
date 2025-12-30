<?php
abstract class BaseModule {
    protected $config;

    public function __construct($config) {
        $this->config = $config;
    }

    // Этот метод проверяет, включен ли модуль в конфиге
    public function isEnabled() {
        return isset($this->config['enabled']) && $this->config['enabled'] === true;
    }

    abstract public function run();
    abstract public function getConfigSchema();
}