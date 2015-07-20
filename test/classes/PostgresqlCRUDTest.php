<?php
namespace Test\Snok;
/**
 * Test class for entity generator class
 */

class PostgresqlCRUDTest extends \PHPUnit_Framework_TestCase {
    protected static $dbh;

    public static function setUpBeforeClass()
    {
        $config = new TestConfig();
        if(!$config->postgresqlConfig) return;
        $c = $config->postgresqlConfig;
        self::$dbh = new \PDO("pgsql:host=".$c["host"].";port=".$c["port"].";dbname=".$c["database"].";user=".$c["username"].";password=".$c["password"]);

        self::$dbh->exec("CREATE SEQUENCE people_id_seq");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS people (id smallint DEFAULT nextval('people_id_seq'), name TEXT, PRIMARY KEY(id))");
        self::$dbh->exec("ALTER SEQUENCE people_id_seq OWNED BY people.id");

        self::$dbh->exec("CREATE SEQUENCE species_id_seq");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS species (id smallint DEFAULT nextval('species_id_seq'), type TEXT, name TEXT, PRIMARY KEY(id))");
        self::$dbh->exec("ALTER SEQUENCE species_id_seq OWNED BY species.id");

        self::$dbh->exec("CREATE TABLE IF NOT EXISTS tools (id smallint, name TEXT, PRIMARY KEY(id))");


        self::$dbh->exec("CREATE SEQUENCE multikey_id1_seq");
        self::$dbh->exec("CREATE SEQUENCE multikey_id2_seq");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS people (id smallint DEFAULT nextval('people_id_seq'), name TEXT, PRIMARY KEY(id))");
        self::$dbh->exec("CREATE TABLE IF NOT EXISTS multikey (id1 smallint DEFAULT nextval('multikey_id1_seq'), id2 smallint DEFAULT nextval('multikey_id2_seq'), name TEXT, PRIMARY KEY(id1, id2))");
        self::$dbh->exec("ALTER SEQUENCE multikey_id1_seq OWNED BY multikey.id1");
        self::$dbh->exec("ALTER SEQUENCE multikey_id2_seq OWNED BY multikey.id2");


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
        if (!self::$dbh) return;
        self::$dbh->exec("DROP TABLE people");
        self::$dbh->exec("DROP TABLE species");
        self::$dbh->exec("DROP TABLE tools");
        self::$dbh->exec("DROP TABLE multikey");
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

    public function testPeopleRead() {
        $reflection = new \ReflectionClass("\\Test\\Snok\\People");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);
        $instance->id = 1;
        $instance->refresh();
        $this->assertEquals($instance->name, "John", "Reading the pre created database should give John for id 1");
    }


    public function testPeopleCommit() {
        $reflection = new \ReflectionClass("\\Test\\Snok\\People");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\People");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\People");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\People");
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
        $reflection = new \ReflectionClass("\\Test\\Snok\\Species");
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

    public function testPeopleCommitMultiKey() {
        $reflection = new \ReflectionClass("\\Test\\Snok\\MultiKey");
        $instance = $reflection->newInstanceWithoutConstructor();
        $this->setupEntity($instance);

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

    public function testPeopleCommitMultiKeyWithID() {
        $reflection = new \ReflectionClass("\\Test\\Snok\\MultiKey");
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