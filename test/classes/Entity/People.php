<?php
namespace Test\Snok\Entity;

class People extends \Snok\BaseEntity {

    const TABLE_NAME = "people";
    const PRIMARY_KEYS = array("id");
    const REQUIRED_VALUES = array();
    const AUTO_INCREMENTED_KEYS = array("id");

    public $id;
    public $name;

    public function __construct($id = null) {
        parent::__construct();
        $this->id = $id;
    }
}
?>