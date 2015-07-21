<?php
namespace Test\Snok\Entity;

class MultiKey extends \Snok\BaseEntity {

    const TABLE_NAME = "multikey";
    const PRIMARY_KEYS = array("id1", "id2");
    const REQUIRED_VALUES = array("name");
    const AUTO_INCREMENTED_KEYS = array("id1", "id2");

    public $id1;
    public $id2;
    public $name;

    public function __construct($id1 = null, $id2 = null) {
        parent::__construct();
        $this->id1 = $id1;
        $this->id2 = $id2;
    }
}
?>