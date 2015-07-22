<?php
namespace Test\Snok;
/**
 * Test class for entity generator class
 */

class PostgresqlGeneratorTest extends \PHPUnit_Framework_TestCase {
    protected static $dbh;

    public static function setUpBeforeClass()
    {
        $config = new TestConfig();
        if(!$config->postgresqlConfig) return;
        $c = $config->postgresqlConfig;
        self::$dbh = new \PDO("pgsql:host=".$c["host"].";port=".$c["port"].";dbname=".$c["database"].";user=".$c["username"].";password=".$c["password"]);

        self::$dbh->exec("CREATE TABLE IF NOT EXISTS article (article_id SERIAL,article_name varchar(20) NOT NULL,article_desc text NOT NULL,date_added timestamp default NULL,PRIMARY KEY (article_id))");
    }

    public static function tearDownAfterClass()
    {
        if (!self::$dbh) return;
        self::$dbh->exec("DROP TABLE people");
        self::$dbh = NULL;
    }

    private function setupEntity(&$instance) {
        if (!self::$dbh) {
            $this->markTestSkipped('The postgresql database isn\'t available, check config and server.');
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
        if (!self::$dbh) {
            $this->markTestSkipped('The postgresql database isn\'t available, check config and server.');
        }
        $generator = new \Snok\EntityGenerator(self::$dbh, "Test\Snok\Entity", __DIR__."/Entity");
        $generator->generateAll();

        require_once(__DIR__  . "/Entity/Article.php");

        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\Article");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->article_name = "High tides overseas";
        $instance->article_desc = "We are expecting high tides this season overseas.";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $this->assertNull($instance2->article_id);
        $this->assertNull($instance2->article_name);

        $instance2->article_id = $instance->article_id;
        $instance2->refresh();

        $obj = $instance2->toObject();

        $this->assertEquals("High tides overseas", $obj->article_name, "When adding a article_name to an object with auto increment article_name should be returned");


        unlink(__DIR__ . "/Entity/Article.php");
    }
}