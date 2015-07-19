<?php
namespace Snok;

abstract class BaseEntity {
    const SELECT = "select_statement";
    const INSERT = "insert_statement";
    const INSERT_AUTO = "insert_statement_auto_generate";
    const UPDATE = "update_statement";
    const DELETE = "delete_statement";

    const QUERY_ARRAY_STATEMENT = "statement";
    const QUERY_ARRAY_PARAMS = "query_params";

    const PRIMARY_KEY = "PRIMARY_KEYS";
    const TABLE_NAME = "TABLE_NAME";

    private $statements;
    private $constants;
    private $properties;
    protected $database;

    private function createParamString($list, $valueTemplate, $separator) {
        $first = true;
        $string = "";
        foreach($list as $val) {
            if(!$first) $string .= $separator;
            $string .= str_replace("%", $val, $valueTemplate);
            $first = false;
        }
        return $string;
    }

    public function __construct() {
        $refObject = new \ReflectionClass($this);
        $this->properties = $refObject->getProperties(\ReflectionProperty::IS_PUBLIC);
        $this->constants = $refObject->getConstants();
        $tableName = $this->constants[self::TABLE_NAME];
        $statements = array();

        $selectStatementSQL = "SELECT * FROM " . $tableName . " WHERE ";
        $insertStatementSQL = "INSERT INTO " . $tableName . " ";
        $insertStatementAutoGenerateSQL = $insertStatementSQL;
        $updateStatementSQL = "UPDATE " . $tableName . " SET ";
        $deleteStatementSQL = "DELETE FROM " . $tableName . " WHERE ";

        $selectStatementSQL .= $this->createParamString($this->constants[self::PRIMARY_KEY], "% = :%", " AND ");
        $deleteStatementSQL .= $this->createParamString($this->constants[self::PRIMARY_KEY], "% = :%", " AND ");

        $propertyNames = array();
        foreach($this->properties as $property) {
            $propertyNames[] = $property->name;
        }
        $insertStatementSQL .= "(" . $this->createParamString($propertyNames, "%", ",") . ") VALUES (";
        $insertStatementSQL .= $this->createParamString($propertyNames, ":%", ",") . ")";

        $propertyNamesWithoutPrimaryKeys = array_diff($propertyNames, $this->constants[self::PRIMARY_KEY]);

        $insertStatementAutoGenerateSQL .= "(" . $this->createParamString($propertyNamesWithoutPrimaryKeys, "%", ",") . ") VALUES (";
        $insertStatementAutoGenerateSQL .= $this->createParamString($propertyNamesWithoutPrimaryKeys, ":%", ",") . ")";

        $updateStatementSQL .= $this->createParamString($propertyNamesWithoutPrimaryKeys, "% = :%", ",") . " WHERE ";
        $updateStatementSQL .= $this->createParamString($this->constants[self::PRIMARY_KEY], "% = :%", " AND ");

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
        foreach($this->properties as $property) {
            if(!in_array($property->name, $queryArray[self::QUERY_ARRAY_PARAMS])) continue;
            $queryArray[self::QUERY_ARRAY_STATEMENT]->bindValue(":" . $property->name, $property->getValue($this));
        }
    }

    private function checkAllPrimaryKeys() {
        $keys = $this->constants[self::PRIMARY_KEY];
        foreach($this->properties as $property) {
            if(!in_array($property->name, $keys)) continue;
            if(!empty($property->getValue($this))) {
                if(($key = array_search($property->name, $keys)) !== false) {
                    unset($keys[$key]);
                }
            }
        }
        return count($keys) == 0;
    }

    public function commit() {
        if(!$this->checkAllPrimaryKeys()) {
            if($this->constants[self::PRIMARY_KEY] != $this->constants["AUTO_GENERATED_KEYS"]) return new \Snok\Exception\InvalidOperationException("Trying to auto generate ids when primary keys aren't the same as auto generated. Try creating setting id's instead.");
            $this->bindProperties($this->statements[self::INSERT_AUTO]);
            $status = $this->statements[self::INSERT_AUTO][self::QUERY_ARRAY_STATEMENT]->execute();
            $newIDs = array();
            foreach($this->constants[self::PRIMARY_KEY] as $key) {
                $newIDs[$key] = $this->database->lastInsertId($key);
            }
            foreach($this->properties as $property) {
                if(!array_key_exists($property->name, $newIDs)) continue;
                $property->setValue($this, $newIDs[$property->name]);
            }

            return $status;
        } else {
            $this->bindProperties($this->statements[self::SELECT]);
            $this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->execute();
            $result = $this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->fetch(\PDO::FETCH_ASSOC);
            if($result) {
                $this->bindProperties($this->statements[self::UPDATE]);
                return $this->statements[self::UPDATE][self::QUERY_ARRAY_STATEMENT]->execute();
            } else {
                $this->bindProperties($this->statements[self::INSERT]);
                return $this->statements[self::INSERT][self::QUERY_ARRAY_STATEMENT]->execute();
            }
        }
        return false;
    }

    public function refresh() {
        $this->bindProperties($this->statements[self::SELECT]);
        if($this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->execute()) {
            $result = $this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->fetch(\PDO::FETCH_ASSOC);
            if(empty($result)) throw new \Snok\Exception\ObjectNotFoundException();
            foreach($this->properties as $property) {
                $property->setValue($this, $result[$property->name]);
            }
        }
    }

    public function delete() {
        $this->bindProperties($this->statements[self::DELETE]);
        $status = $this->statements[self::DELETE][self::QUERY_ARRAY_STATEMENT]->execute();
        foreach($this->properties as $property) {
            if(!in_array($property->name, $this->constants[self::PRIMARY_KEY])) continue;
            $property->setValue($this, null);
        }
    }
}
?>