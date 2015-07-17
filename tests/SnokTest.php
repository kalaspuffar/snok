<?php

/**
 * Test class for entity generator class
 */

class Snok§Test extends PHPUnit_Framework_TestCase {
    const DB_NAME = 'tests/data/test.db';
    private $testdb;

    /**
     * @before
     */
    public function setupDB() {
        $this->testdb = new PDO('sqlite:' . self::DB_NAME);
        $this->testdb->exec("CREATE TABLE IF NOT EXISTS tablea (id INTEGER PRIMARY KEY, name TEXT)");
        $this->testdb->exec("CREATE TABLE IF NOT EXISTS tableb (id INTEGER PRIMARY KEY, name TEXT)");
        $this->testdb->exec("CREATE TABLE IF NOT EXISTS tablec (id INTEGER PRIMARY KEY, name TEXT)");

        $data = array(
                "tablea" => array('John', 'Sally', 'Peter'),
                "tableb" => array('Dog', 'Cat', 'Hamster'),
                "tablec" => array('Hammer', 'Screwdriver', 'Ruler')
            );

        foreach(["tablea", "tableb", "tablec"] as $table) {
            $insert = "INSERT INTO {$table} (name) VALUES (:name)";
            $stmt = $this->testdb->prepare($insert);
            $stmt->bindParam(':name', $name);

            foreach($data[$table] as $name) {
                $stmt->execute();
            }
        }
    }

    public function testGenerate() {
        $q = $this->testdb->prepare("select * from tablea");
        $q->execute();

        for ($i = 0; $i < $q->columnCount(); $i++) {
            $col = $q->getColumnMeta($i);
            print_r($col);
        }

        // TODO: Identify the primary keys.
    }


    /**
     * @after
     */
    public function removeDB() {
        unlink(self::DB_NAME);
    }
}

