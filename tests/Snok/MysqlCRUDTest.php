<?php
namespace tests\Snok;
/**
 * Test class for entity generator class
 */

class MysqlCRUDTest extends \PHPUnit_Framework_TestCase {
    protected static $dbh;

    public static function setUpBeforeClass()
    {
        self::$dbh = new \PDO('mysql:host=localhost;dbname=snokdb;charset=utf8', 'root', 'testroot');
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS people (id INTEGER NOT NULL AUTO_INCREMENT, name TEXT, PRIMARY KEY (id))");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS species (id INTEGER NOT NULL AUTO_INCREMENT, type TEXT, name TEXT, PRIMARY KEY (id))");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS tools (id INTEGER NOT NULL AUTO_INCREMENT, name TEXT, PRIMARY KEY (id))");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS multikey (id1 INTEGER NOT NULL, id2 INTEGER NOT NULL, name TEXT, PRIMARY KEY(id1, id2))");

        $data = array(
                "people" => array('John', 'Sally', 'Peter'),
                "species" => array('Dog', 'Cat', 'Hamster'),
                "tools" => array('Hammer', 'Screwdriver', 'Ruler')
            );

        foreach(["people", "species", "tools"] as $table) {
            $insert = "INSERT INTO {$table} (name) VALUES (:name)";
            $stmt = self::$dbh->prepare($insert);
            $stmt->bindParam(':name', $name);

            foreach($data[$table] as $name) {
                $stmt->execute();
            }
        }

    }

    public static function tearDownAfterClass()
    {
        self::$dbh->exec("DROP TABLE people");
        self::$dbh->exec("DROP TABLE species");
        self::$dbh->exec("DROP TABLE tools");
        self::$dbh->exec("DROP TABLE multikey");
        self::$dbh = NULL;
    }

    private function setupEntity(&$instance) {
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

    public function testPeopleRead() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);
        $instance->id = 1;
        $instance->refresh();
        $this->assertEquals($instance->name, "John", "Reading the pre created database should give John for id 1");
    }


    public function testPeopleCommit() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->name = "Logan";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $this->assertNull($instance2->id);
        $this->assertNull($instance2->name);

        $instance2->id = $instance->id;
        $instance2->refresh();
        $this->assertEquals("Logan", $instance2->name, "When adding a name to an object with auto increment name should be returned.");
    }


    public function testPeopleCommitWithID() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->id = 10;
        $instance->name = "George";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $instance2->id = 10;
        $instance2->refresh();
        $this->assertEquals("George", $instance2->name, "After added with ID we should get the name back when asking in another object.");
    }

    public function testPeopleUpdate() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->name = "Chris";
        $instance->commit();
        $instance->name = "Annie";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $instance2->id = $instance->id;
        $instance2->refresh();
        $this->assertEquals("Annie", $instance2->name, "After the update the value should change. (Annie not Chris)");

        $instance2->name = "Felicia";
        $instance2->commit();

        $instance->refresh();
        $this->assertEquals("Felicia", $instance->name, "Another change should change the value. (Felicia not Annie)");
    }

    public function testPeopleDelete() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->name = "Shannon";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $instance2->id = $instance->id;
        $instance2->delete();
        $this->assertNull($instance2->id, "After deleting the id should reset.");

        $this->setExpectedException("\Snok\Exception\ObjectNotFoundException");
        $instance->refresh();
    }

    public function testPeopleCommitMultiValue() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\Species");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->name = "Gerry";
        $instance->type = "Snake";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $this->assertNull($instance2->id);
        $this->assertNull($instance2->name);
        $this->assertNull($instance2->type);

        $instance2->id = $instance->id;
        $instance2->refresh();

        $this->assertEquals("Gerry", $instance2->name);
        $this->assertEquals("Snake", $instance2->type);
    }

    public function testPeopleCommitMultiKeyWithID() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\MultiKey");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->id1 = 4;
        $instance->id2 = 5;
        $instance->name = "Logan";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $this->assertNull($instance2->id1);
        $this->assertNull($instance2->id2);
        $this->assertNull($instance2->name);

        $instance2->id1 = $instance->id1;
        $instance2->id2 = $instance->id2;
        $instance2->refresh();
        $this->assertEquals("Logan", $instance2->name, "When adding a name to an object with auto increment name should be returned.");
    }

}