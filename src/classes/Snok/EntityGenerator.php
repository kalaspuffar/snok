<?php
namespace Snok;
/**
 *  Generates entities from database schema.
 */
class EntityGenerator {
    private $database;

    public function __construct($database = null) {
        $this->database = $database;
    }

    public function generate() {
        $q = $this->database->prepare("select * from tablea");
        $q->execute();

        for ($i = 0; $i < $q->columnCount(); $i++) {
            $col = $q->getColumnMeta($i);
            print_r($col);
        }

        // TODO: Identify the primary keys
    }
}
?>