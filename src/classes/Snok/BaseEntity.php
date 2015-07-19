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

        $this->statements[self::SELECT] = array(
            self::QUERY_ARRAY_STATEMENT => $this->database->prepare($selectStatementSQL),
            self::QUERY_ARRAY_PARAMS => $this->constants[self::PRIMARY_KEY]
            );

        $this->statements[self::INSERT] = array(
            self::QUERY_ARRAY_STATEMENT => $this->database->prepare($insertStatementSQL),
            self::QUERY_ARRAY_PARAMS => $propertyNames
            );

        if($this->constants[self::PRIMARY_KEY] == $this->constants["AUTO_GENERATED_KEYS"]) {
            $this->statements[self::INSERT] = array(
                self::QUERY_ARRAY_STATEMENT => $this->database->prepare($insertStatementAutoGenerateSQL),
                self::QUERY_ARRAY_PARAMS => $propertyNamesWithoutPrimaryKeys
                );
        }

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

    public function commit() {
        $this->bindProperties($this->statements[self::SELECT]);
        if($this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->execute()) {
            $this->bindProperties($this->statements[self::UPDATE]);
            return $this->statements[self::UPDATE][self::QUERY_ARRAY_STATEMENT]->execute();
        } else {
            if($this->statements[self::INSERT_AUTO][self::QUERY_ARRAY_STATEMENT]) {
                $this->bindProperties($this->statements[self::INSERT_AUTO]);
                return $this->statements[self::INSERT_AUTO][self::QUERY_ARRAY_STATEMENT]->execute();
            } else {
                $this->bindProperties($this->statements[INSERT]);
                return $this->statements[self::INSERT][self::QUERY_ARRAY_STATEMENT]->execute();
            }
        }
        return false;
    }

    public function refresh() {
        $this->bindProperties($this->statements[self::SELECT]);
        if($this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->execute()) {
            $result = $this->statements[self::SELECT][self::QUERY_ARRAY_STATEMENT]->fetch(\PDO::FETCH_ASSOC);
            foreach($this->properties as $property) {
                $property->setValue($this, $result[$property->name]);
            }
        }
    }

    public function delete() {
        $this->bindProperties($this->statements[self::DELETE]);
        return $this->statements[self::DELETE][self::QUERY_ARRAY_STATEMENT]->execute();
    }
}
?>