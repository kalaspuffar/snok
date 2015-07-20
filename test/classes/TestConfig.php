<?php
namespace Test\Snok;
/**
 * Class to retrieve the config for tests.
 */

class TestConfig {
    public $loaded;
    public $mysqlConfig;
    public $postgresqlConfig;

    private function checkConfig($config) {
        foreach(array("host", "port", "database", "username", "password") as $var) {
            if(!array_key_exists($var, $config)) {
                $config = null;
                break;
            }
        }
        return $config;
    }

    public function __construct() {
        $configStr = file_get_contents(__DIR__ . "/config.json");
        if(!$configStr) return;
        $configArr = json_decode($configStr, true);
        if(array_key_exists("mysql", $configArr)) {
            $this->mysqlConfig = $this->checkConfig($configArr["mysql"]);
        }
        if(array_key_exists("postgresql", $configArr)) {
            $this->postgresqlConfig = $this->checkConfig($configArr["postgresql"]);
        }
        $loaded = true;
    }
}