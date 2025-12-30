<?php
// modules/BaseModule.php

abstract class BaseModule {
    protected $config = [];

    // Метод запуска логики модуля
    abstract public function run();

    // Описание, какие настройки нужны модулю (для Клиента)
    abstract public function getConfigSchema(): array;

    public function setConfig($config) {
        $this->config = array_merge($this->config, $config);
    }

    public function getName() {
        return (new ReflectionClass($this))->getShortName();
    }
}