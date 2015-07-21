<?php
namespace Snok;

class Util {

    private static function checkConfig($config) {
        foreach(array("entity_directory", "namespace", "driver", "host", "port", "database", "username", "password") as $var) {
            if(!array_key_exists($var, $config)) {
                $config = null;
                break;
            }
        }
        return $config;
    }

    public static function getConfiguration() {
        $configStr = file_get_contents(__DIR__ . "/config.json");
        if(!$configStr) return new \StdClass();
        $configuration = json_decode($configStr, true);
        self::checkConfig($configuration);
        return $configuration;
    }

    public static function getDatabaseConnection($c) {
        if($c["driver"] == "mysql") {
            return new \PDO("mysql:host=".$c["host"].";port=".$c["port"].";dbname=".$c["database"].";charset=utf8",$c["username"],$c["password"]);
        } else if($c["driver"] == "pgsql") {
            return new \PDO("pgsql:host=".$c["host"].";port=".$c["port"].";dbname=".$c["database"].";user=".$c["username"].";password=".$c["password"]);
        } else {
            throw new \Exception("Unknown driver");
        }

    }

    public static function createParamString($list, $valueTemplate, $separator) {
        $first = true;
        $string = "";
        foreach($list as $val) {
            if(!$first) $string .= $separator;
            $string .= str_replace("%", $val, $valueTemplate);
            $first = false;
        }
        return $string;
    }
}