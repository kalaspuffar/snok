<?php
namespace tests\Snok;

class TableAEntity extends \Snok\BaseEntity {

    const TABLE_NAME = "tablea";
    const PRIMARY_KEYS = array("id");
    const REQUIRED_VALUES = array("name");
    const AUTO_GENERATED_KEYS = array("id");

    public $id;
    public $name;

    public function __construct($id = null) {
        parent::__construct();
        $this->id = $id;
    }
}
?>