<?php
namespace tests\Snok;
/**
 * Test class for entity generator class
 */

class SnokTest extends \PHPUnit_Framework_TestCase {
    const DB_NAME = 'tests/data/test.db';
    protected static $dbh;

    public static function setUpBeforeClass()
    {
        self::$dbh = new \PDO('sqlite::memory:');
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS people (id INTEGER PRIMARY KEY, name TEXT)");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS species (id INTEGER PRIMARY KEY, type TEXT, name TEXT)");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS tools (id INTEGER PRIMARY KEY, name TEXT)");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS multikey (id1 INTEGER PRIMARY KEY, id2 INTEGER PRIMARY KEY, name TEXT)");

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

/*
    public function testPeopleCommitMultiKey() {
        $reflection = new \ReflectionClass("\\tests\\Snok\\MultiKey");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);
        print_r($instance);

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
*/
}