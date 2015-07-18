<?php
namespace tests\Snok;
/**
 * Test class for entity generator class
 */

class SnokTest extends \PHPUnit_Framework_TestCase {
    const DB_NAME = 'tests/data/test.db';
    private $testdb;

    /**
     * @before
     */
    public function setupDB() {
        $this->testdb = new \PDO('sqlite:' . self::DB_NAME);
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
//        $generator = new \Snok\EntityGenerator($this->testdb);
//        $generator->generate();
    }

    public function testEntityTableA() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\TableAEntity");
        $instance = $reflection->newInstanceWithoutConstructor();
        $refObject = new \ReflectionClass($instance);
        $properties = $refObject->getProperties(\ReflectionProperty::IS_PROTECTED);
        foreach($properties as $property) {
            if($property->name == "database") {
                $property->setAccessible(true);
                $property->setValue($instance, $this->testdb);
            }
        }
        $method = $refObject->getConstructor();
        $method->invoke($instance);

        $instance->id = 1;
        $instance->refresh();
        $this->assertEquals($instance->name, "John");
    }


    /**
     * @after
     */
    public function removeDB() {
        unlink(self::DB_NAME);
    }
}

