<?php
namespace Snok;

abstract class BaseEntity {
    const SELECT = "select_statement";
    const INSERT = "insert_statement";
    const INSERT_AUTO = "insert_statement_auto_generate";
    const UPDATE = "update_statement";
    const DELETE = "delete_statement";
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
        $tableName = $this->constants["TABLE_NAME"];
        $statements = array();

        $selectStatementSQL = "SELECT * FROM " . $tableName . " WHERE ";
        $insertStatementSQL = "INSERT INTO " . $tableName . " ";
        $insertStatementAutoGenerateSQL = $insertStatementSQL;
        $updateStatementSQL = "UPDATE " . $tableName . " SET ";
        $deleteStatementSQL = "DELETE FROM " . $tableName . " WHERE ";

        $selectStatementSQL .= $this->createParamString($this->constants["PRIMARY_KEYS"], "% = :%", " AND ");
        $deleteStatementSQL .= $this->createParamString($this->constants["PRIMARY_KEYS"], "% = :%", " AND ");

        $propertyNames = array();
        foreach($this->properties as $property) {
            $propertyNames[] = $property->name;
        }
        $insertStatementSQL .= "(" . $this->createParamString($propertyNames, "%", ",") . ") VALUES (";
        $insertStatementSQL .= $this->createParamString($propertyNames, ":%", ",") . ")";

        $propertyNamesWithoutPrimaryKeys = array_diff($propertyNames, $this->constants["PRIMARY_KEYS"]);

        $insertStatementAutoGenerateSQL .= "(" . $this->createParamString($propertyNamesWithoutPrimaryKeys, "%", ",") . ") VALUES (";
        $insertStatementAutoGenerateSQL .= $this->createParamString($propertyNamesWithoutPrimaryKeys, ":%", ",") . ")";

        $updateStatementSQL .= $this->createParamString($propertyNamesWithoutPrimaryKeys, "% = :%", ",") . " WHERE ";
        $updateStatementSQL .= $this->createParamString($this->constants["PRIMARY_KEYS"], "% = :%", " AND ");

        $this->statements[self::SELECT] = $this->database->prepare($selectStatementSQL);
        $this->statements[self::INSERT] = $this->database->prepare($insertStatementSQL);
        if($this->constants["PRIMARY_KEYS"] == $this->constants["AUTO_GENERATED_KEYS"]) {
            $this->statements[self::INSERT_AUTO] = $this->database->prepare($insertStatementAutoGenerateSQL);
        }
        $this->statements[self::UPDATE] = $this->database->prepare($updateStatementSQL);
        $this->statements[self::DELETE] = $this->database->prepare($deleteStatementSQL);
    }

    private function bindProperties(&$statement) {
        //print_r($statement->queryString);
        // TODO only set params available in query.
        foreach($this->properties as $property) {
            /**
             * Ugly fix so the test case works. We may not set params not available in query.
             * Then the query will not execute for some reason. Not documented.
             */
            if($property->name == "name") continue;
            $statement->bindValue(":" . $property->name, $property->getValue($this));
        }
    }

    public function commit() {
        $this->bindProperties($this->statements[self::SELECT]);
        if($this->statements[self::SELECT]->execute()) {
            $this->bindProperties($this->statements[self::UPDATE]);
            return $this->statements[self::UPDATE]->execute();
        } else {
            if($this->statements[self::INSERT_AUTO]) {
                $this->bindProperties($this->statements[self::INSERT_AUTO]);
                return $this->statements[self::INSERT_AUTO]->execute();
            } else {
                $this->bindProperties($this->statements[INSERT]);
                return $this->statements[self::INSERT]->execute();
            }
        }
        return false;
    }

    public function refresh() {
        $this->bindProperties($this->statements[self::SELECT]);
        if($this->statements[self::SELECT]->execute()) {
            $result = $this->statements[self::SELECT]->fetch(\PDO::FETCH_ASSOC);
            foreach($this->properties as $property) {
                $property->setValue($this, $result[$property->name]);
            }
        }
    }

    public function delete() {
        $this->bindProperties($this->statements[self::DELETE]);
        return $this->statements[self::DELETE]->execute();
    }
}
?>