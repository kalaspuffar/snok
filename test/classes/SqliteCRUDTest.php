<?php
namespace Test\Snok;
/**
 * Test class for entity generator class
 */

class SqliteCRUDTest extends \PHPUnit_Framework_TestCase {
    protected static $dbh;

    public static function setUpBeforeClass()
    {
        self::$dbh = new \PDO('sqlite::memory:');
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS people (id INTEGER PRIMARY KEY, name TEXT)");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS species (id INTEGER PRIMARY KEY, type TEXT, name TEXT)");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS multikey (id1 INTEGER, id2 INTEGER, name TEXT, PRIMARY KEY(id1, id2))");

        $data = array(
                "people" => array('John', 'Sally', 'Peter'),
                "species" => array('Dog', 'Cat', 'Hamster'),
            );

        foreach(["people", "species"] as $table) {
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);
        $instance->id = 1;
        $instance->refresh();
        $this->assertEquals($instance->name, "John", "Reading the pre created database should give John for id 1");
    }


    public function testPeopleCommit() {
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\People");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\People");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\People");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\People");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\Species");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\MultiKey");
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

    public function testSimpleObject() {

        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\Species");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $simpleObj = new \StdClass();
        $simpleObj->type = "Lizard";

        $instance->fromObject($simpleObj);
        $this->setExpectedException("\Snok\Exception\MissingRequiredFieldException");
        $instance->commit();

        $simpleObj->name = "Ola";
        $instance->fromObject($simpleObj);
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $this->assertNull($instance2->id);
        $this->assertNull($instance2->name);
        $this->assertNull($instance2->type);

        $instance2->id = $instance->id;
        $instance2->refresh();

        $obj = $instance2->toObject();
        $this->assertEquals(get_class($obj), "stdClass");
        $this->assertEquals($obj->name, "Ola");
        $this->assertEquals($obj->type, "Lizard");
    }

    public function testConsistencyAutoIncrement() {
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->name = "Haily";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $instance2->id = $instance->id;
        $instance2->refresh();
        $instance2->name = "Pat";
        $instance2->commit();

        $instance->name = "Ronald";
        $this->setExpectedException("\Snok\Exception\DataConsistencyException");
        $instance->commit();
    }

    public function testConsistencyWithID() {
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->id = 89;
        $instance->name = "Haily";
        $instance->commit();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $instance2->id = $instance->id;
        $instance2->refresh();
        $instance2->name = "Pat";
        $instance2->commit();

        $instance->name = "Ronald";
        $this->setExpectedException("\Snok\Exception\DataConsistencyException");
        $instance->commit();
    }

    public function testConsistencyRefresh() {
        $reflection = new \ReflectionClass("\\Test\\Snok\\Entity\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

        $instance->name = "Haily";
        $instance->commit();
        $instance->refresh();

        $instance2 = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance2);

        $instance2->id = $instance->id;
        $instance2->refresh();
        $instance2->name = "Pat";
        $instance2->commit();

        $instance->name = "Ronald";
        $this->setExpectedException("\Snok\Exception\DataConsistencyException");
        $instance->commit();
    }
}