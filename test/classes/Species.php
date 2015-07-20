<?php
namespace Test\Snok;

class Species extends \Snok\BaseEntity {

    const TABLE_NAME = "species";
    const PRIMARY_KEYS = array("id");
    const REQUIRED_VALUES = array("name");
    const AUTO_GENERATED_KEYS = array("id");

    public $id;
    public $name;
    public $type;

    public function __construct($id = null) {
        parent::__construct();
        $this->id = $id;
    }
}
?>