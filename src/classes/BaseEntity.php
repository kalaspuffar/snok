<?php
namespace Snok;
use Snok\Exception\InvalidOperationException;
use Snok\Exception\DataConsistencyException;
use Snok\Exception\MissingRequiredFieldException;

abstract class BaseEntity {
    const SELECT = "select_statement";
    const INSERT = "insert_statement";
    const INSERT_AUTO = "insert_statement_auto_generate";
    const UPDATE = "update_statement";
    const DELETE = "delete_statement";

    const QUERY_ARRAY_STATEMENT = "statement";
    const QUERY_ARRAY_PARAMS = "query_params";

    const PRIMARY_KEY = "PRIMARY_KEYS";
    const AUTO_INCREMENT = "AUTO_INCREMENTED_KEYS";
    const TABLE_NAME = "TABLE_NAME";
    const REQUIRED_VALUES = "REQUIRED_VALUES";
    const POSTGRES_DRIVER_NAME = "pgsql";

    private $statements;
    private $constants;
    private $properties;
    private $table_hash;
    protected $database;

    public function __construct() {
        if($this->database == null) {
            $this->database = Util::getDatabaseConnection(Util::getConfiguration());
        }

        $refObject = new \ReflectionClass($this);
        $this->properties = $refObject->getProperties(\ReflectionProperty::IS_PUBLIC);
        $this->constants = $refObject->getConstants();
        $tableName = $this->constants[self::TABLE_NAME];

        $selectStatementSQL = "SELECT * FROM " . $tableName . " WHERE ";
        $insertStatementSQL = "INSERT INTO " . $tableName . " ";
        $insertStatementAutoGenerateSQL = $insertStatementSQL;
        $updateStatementSQL = "UPDATE " . $tableName . " SET ";
        $deleteStatementSQL = "DELETE FROM " . $tableName . " WHERE ";

        $selectStatementSQL .= Util::createParamString($this->constants[self::PRIMARY_KEY], "% = :%", " AND ");
        $deleteStatementSQL .= Util::createParamString($this->constants[self::PRIMARY_KEY], "% = :%", " AND ");

        $propertyNames = array();
        foreach ($this->properties as $property) {
            $propertyNames[] = $property->name;
        }
        $insertStatementSQL .= "(" . Util::createParamString($propertyNames, "%", ",") . ") VALUES (";
        $insertStatementSQL .= Util::createParamString($propertyNames, ":%", ",") . ")";

        $propertyNamesWithoutPrimaryKeys = array_diff($propertyNames, $this->constants[self::PRIMARY_KEY]);

        $insertStatementAutoGenerateSQL .= "(" . Util::createParamString($propertyNamesWithoutPrimaryKeys, "%", ",") . ") VALUES (";
        $insertStatementAutoGenerateSQL .= Util::createParamString($propertyNamesWithoutPrimaryKeys, ":%", ",") . ")";

        if ($this->database->getAttribute(\PDO::ATTR_DRIVER_NAME) == self::POSTGRES_DRIVER_NAME) {
            $insertStatementAutoGenerateSQL .= " RETURNING " . Util::createParamString($this->constants[self::PRIMARY_KEY], "%", ",");
        }

        $updateStatementSQL .= Util::createParamString($propertyNamesWithoutPrimaryKeys, "% = :%", ",") . " WHERE ";
        $updateStatementSQL .= Util::createParamString($this->constants[self::PRIMARY_KEY], "% = :%", " AND ");

        try {
            $this->statements[self::SELECT] = array(
                self::QUERY_ARRAY_STATEMENT => $this->database->prepare($selectStatementSQL),
                self::QUERY_ARRAY_PARAMS => $this->constants[self::PRIMARY_KEY]
                );
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }

        $this->statements[self::INSERT] = array(
            self::QUERY_ARRAY_STATEMENT => $this->database->prepare($insertStatementSQL),
            self::QUERY_ARRAY_PARAMS => $propertyNames
            );

        $this->statements[self::INSERT_AUTO] = array(
            self::QUERY_ARRAY_STATEMENT => $this->database->prepare($insertStatementAutoGenerateSQL),
            self::QUERY_ARRAY_PARAMS => $propertyNamesWithoutPrimaryKeys
            );

        $this->statements[self::UPDATE] = array(
            self::QUERY_ARRAY_STATEMENT => $this->database->prepare($updateStatementSQL),
            self::QUERY_ARRAY_PARAMS => $propertyNames
            );

        $this->statements[self::DELETE] = array(
            self::QUERY_ARRAY_STATEMENT => $this->database->prepare($deleteStatementSQL),
            self::QUERY_ARRAY_PARAMS => $this->constants[self::PRIMARY_KEY]
            );
    }

    private function bindProperties(&$queryArray) {
        foreach ($this->properties as $property) {
            if (!in_array($property->name, $queryArray[self::QUERY_ARRAY_PARAMS])) continue;
            $queryArray[self::QUERY_ARRAY_STATEMENT]->bindValue(":" . $property->name, $property->getValue($this));
        }
    }

    private function checkAllPrimaryKeys() {
        $keys = $this->constants[self::PRIMARY_KEY];
        foreach ($this->properties as $property) {
            if (in_array($property->name, $this->constants[self::REQUIRED_VALUES]) && $property->getValue($this) == null) {
                throw new MissingRequiredFieldException();
            }
            if (!in_array($property->name, $keys)) continue;
            if (!empty($property->getValue($this))) {
                if (($key = array_search($property->name, $keys)) !== false) {
                    unset($keys[$key]);
                }
            }
        }
        return count($keys) == 0;
    }

    public function commit() {
        if (!$this->checkAllPrimaryKeys()) {
            if ($this->constants[self::PRIMARY_KEY] != $this->constants[self::AUTO_INCREMENT]) {
                throw new InvalidOperationException("Trying to auto generate ids when primary keys aren't the same as auto generated. Try creating setting id's instead.");
            }
            $this->bindProperties($this->statements[self::INSERT_AUTO]);
            $status = $this->statements[self::INSERT_AUTO][self::QUERY_ARRAY_STATEMENT]->execute();
            $newIDs = array();
            if ($this->database->getAttribute(\PDO::ATTR_DRIVER_NAME) == self::POSTGRES_DRIVER_NAME) {
                $result = $this->statements[self::INSERT_AUTO][self::QUERY_ARRAY_STATEMENT]->fetch(\PDO::FETCH_ASSOC);
                if ($result) {
                    if (is_array($this->constants)) {
                        foreach ($this->constants[self::PRIMARY_KEY] as $key) {
                            if (array_key_exists($key, $result)) $newIDs[$key] = $result[$key];
                        }
                    }
                }
            } else {
                if (is_array($this->constants)) {
                    foreach ($this->constants[self::PRIMARY_KEY] as $key) {
                        $newIDs[$key] = $this->database->lastInsertId($key);
                    }
                }
            }


            $new_table_hash = "";
            foreach ($this->properties as $property) {
                if (array_key_exists($property->name, $newIDs)) $property->setValue($this, $newIDs[$property->name]);
                $new_table_hash .= $property->getValue($this) . "|";
            }
            $this->table_hash = md5($new_table_hash);
            return $status;
        } else {
            $this->bindProperties($this->statements[self::SELECT]);
            $this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->execute();
            $result = $this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->fetch(\PDO::FETCH_ASSOC);
            if($result) {

                if ($this->table_hash) {
                    $new_table_hash = "";
                    foreach ($this->properties as $property) {
                        $new_table_hash .= $result[$property->name] . "|";
                    }
                    $new_table_hash = md5($new_table_hash);

                    if ($this->table_hash != $new_table_hash) {
                        throw new DataConsistencyException("Data in database doesn't match data in this entity. Hashes: " . $this->table_hash . " != " . $new_table_hash);
                    }
                }

                $this->bindProperties($this->statements[self::UPDATE]);
                return $this->statements[self::UPDATE][self::QUERY_ARRAY_STATEMENT]->execute();
            } else {
                $new_table_hash = "";
                foreach ($this->properties as $property) $new_table_hash .= $property->getValue($this) . "|";
                $this->table_hash = md5($new_table_hash);

                $this->bindProperties($this->statements[self::INSERT]);
                return $this->statements[self::INSERT][self::QUERY_ARRAY_STATEMENT]->execute();
            }
        }
    }

    public function refresh() {
        $this->bindProperties($this->statements[self::SELECT]);
        if ($this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->execute()) {
            $result = $this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->fetch(\PDO::FETCH_ASSOC);
            if (empty($result)) throw new \Snok\Exception\ObjectNotFoundException();

            $new_table_hash = "";
            foreach ($this->properties as $property) {
                $new_table_hash .= $result[$property->name] . "|";
                $property->setValue($this, $result[$property->name]);
            }
            $this->table_hash = md5($new_table_hash);
        }
    }

    public function delete() {
        $this->bindProperties($this->statements[self::DELETE]);
        $this->statements[self::DELETE][self::QUERY_ARRAY_STATEMENT]->execute();
        foreach ($this->properties as $property) {
            if (!in_array($property->name, $this->constants[self::PRIMARY_KEY])) continue;
            $property->setValue($this, null);
        }
    }

    public function toObject() {
        $newObj = new \StdClass();
        foreach ($this->properties as $property) {
            $newObj->{$property->name} = $property->getValue($this);
        }
        return $newObj;
    }


    public function fromObject($obj) {
        foreach ($this->properties as $property) {
            if (!property_exists($obj, $property->name)) continue;
            $property->setValue($this, $obj->{$property->name});
        }
    }
}
