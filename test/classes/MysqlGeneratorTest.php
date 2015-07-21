<?php
namespace Test\Snok;
/**
 * Test class for entity generator class
 */

class MysqlGeneratorTest extends \PHPUnit_Framework_TestCase {
    protected static $dbh;

    public static function setUpBeforeClass()
    {
        $config = new TestConfig();
        if(!$config->mysqlConfig) return;
        $c = $config->mysqlConfig;
        self::$dbh = new \PDO("mysql:host=".$c["host"].";port=".$c["port"].";dbname=".$c["database"].";charset=utf8",$c["username"],$c["password"]);

        self::$dbh->exec("CREATE TABLE IF NOT EXISTS address (address_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT, firstname VARCHAR(60) NOT NULL, lastname VARCHAR(60) NOT NULL, address VARCHAR(50) NOT NULL, address2 VARCHAR(50) DEFAULT NULL, district VARCHAR(20) NOT NULL, city VARCHAR(40) NOT NULL, postal_code VARCHAR(10) DEFAULT NULL, phone VARCHAR(20) NOT NULL, last_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (address_id))");
    }

    public static function tearDownAfterClass()
    {
        if (!self::$dbh) return;
        self::$dbh->exec("DROP TABLE people");
        self::$dbh->exec("DROP TABLE species");
        self::$dbh->exec("DROP TABLE multikey");
        self::$dbh = NULL;
    }

    private function setupEntity(&$instance) {
        if (!self::$dbh) {
            $this->markTestSkipped('The mysql database isn\'t available, check config and server.');
        }
        $refObject = new \ReflectionClass($instance);
        $properties = $refObject->getProperties(\ReflectionProperty::IS_PROTECTED);
        foreach($properties as $property) {
            if($property->name == "database") {
                $property->setAccessible(true);
                $property->setValue($instance, self::$dbh);
            }
        }
        $method = $refObject->getConstructor();
        $method->invoke($instance);
    }

    public function testGenerator() {
        $generator = new \Snok\EntityGenerator(self::$dbh);
        $generator->generate("Test\Snok\Entity", "address", __DIR__ . "/Entity");

        require_once(__DIR__  . "/Entity/Address.php");

        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\Address");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->firstname = "John";
        $instance->lastname = "Logan";
        $instance->address = "123 Ground street";
        $instance->district = "Harlem";
        $instance->city = "Detroit";
        $instance->phone = "555-1234";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $this->assertNull($instance2->address_id);
        $this->assertNull($instance2->firstname);

        $instance2->address_id = $instance->address_id;
        $instance2->refresh();
        $this->assertEquals("John", $instance2->firstname, "When adding a name to an object with auto increment name should be returned");

        unlink(__DIR__ . "/Entity/Address.php");
    }
}