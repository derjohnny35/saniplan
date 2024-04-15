<?php
class Config
{
    private $configPath;
    public $config;

    function __construct()
    {
        $this->configPath = __DIR__ . '/settings/config.json';
        $this->config = json_decode(file_get_contents($this->configPath), true);
    }

    function update($name, $value)
    {
        $this->config[$name] = $value;
        file_put_contents($this->configPath, json_encode($this->config, JSON_PRETTY_PRINT));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['update'])) {
    $key = $_GET['key'];
    $value = $_GET['value'];
    $config = new Config();
    $config->update($key, $value);
    echo "{$key} wurde auf {$value} geändert.";
}
?>