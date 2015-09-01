<?php
namespace Snok;
/**
 *  Generates entities from database schema.
 */
class EntityGenerator {
    const MYSQL_DRIVER_NAME = "mysql";

    private $database;
    private $configuration;
    private $entity_directory;
    private $namespace;

    public function __construct($database = null, $namespace = null, $entity_directory = null) {
        $this->database = $database;
        $this->namespace = $namespace;
        $this->entity_directory = $entity_directory;
        $this->configuration = Util::getConfiguration();
        if ($this->database == null) {
            $this->database = Util::getDatabaseConnection($this->configuration);
        }
        if ($this->namespace == null) {
            $this->namespace = $this->configuration["namespace"];
        }
        if ($this->entity_directory == null) {
            $this->entity_directory = $this->configuration["entity_directory"];
        }
    }

    public function generateAll() {
        if ($this->database->getAttribute(\PDO::ATTR_DRIVER_NAME) == self::MYSQL_DRIVER_NAME) {
            $q = $this->database->prepare("select table_name from INFORMATION_SCHEMA.TABLES where table_schema = '" . $this->configuration["database"] . "'");
        } else {
            $q = $this->database->prepare("select table_name from INFORMATION_SCHEMA.TABLES where table_catalog = '" . $this->configuration["database"] . "' and table_schema = 'public'");
        }
        if ($q->execute()) {
            $res = $q->fetchAll(\PDO::FETCH_ASSOC);
            foreach($res as $row) $this->generate($row["table_name"]);
        }
    }

    public function generate($tablename) {

        $tablename = strtolower($tablename);

        $extra = "";
        if ($this->database->getAttribute(\PDO::ATTR_DRIVER_NAME) == self::MYSQL_DRIVER_NAME) {
            $extra = ", column_key, extra";
        }

        $q = $this->database->prepare("select column_name, column_default, is_nullable, data_type" . $extra . " from INFORMATION_SCHEMA.COLUMNS where table_name = '" . $tablename . "'");
        $qpri = $this->database->prepare("select column_name from INFORMATION_SCHEMA.KEY_COLUMN_USAGE as kcu, INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc where kcu.table_name = '" . $tablename . "' and tc.table_name = '" . $tablename . "' and tc.constraint_type = 'PRIMARY KEY' and kcu.constraint_name = tc.constraint_name");

        if ($q->execute() && $qpri->execute()) {
            $res = $q->fetchAll(\PDO::FETCH_ASSOC);

            $columns = array();
            $primary = array();
            $required = array();
            $auto_increment = array();

            $respri = $qpri->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($respri as $row) {
                $primary[] = $row["column_name"];
            }
            foreach ($res as $row) {
                $columns[] = $row["column_name"];
                if($row["is_nullable"] != "YES" && !in_array($row["column_name"], $primary) && empty($row["column_default"])) $required[] = $row["column_name"];
                if(array_key_exists("extra", $row) && $row["extra"] == "auto_increment") $auto_increment[] = $row["column_name"];
                if(substr($row["column_default"], 0, strlen("nextval")) === "nextval") $auto_increment[] = $row["column_name"];
            }

            $phpcode = "<?php\n";
            $phpcode .= "namespace " . $this->namespace . ";\n\n";
            $phpcode .= "class " . ucfirst($tablename) . " extends \Snok\BaseEntity {\n\n";
            $phpcode .= "\tconst TABLE_NAME = \"" . $tablename . "\";\n";
            $phpcode .= "\tconst PRIMARY_KEYS = array(" . Util::createParamString($primary, "\"%\"", ",") . ");\n";
            $phpcode .= "\tconst REQUIRED_VALUES = array(" . Util::createParamString($required, "\"%\"", ",") . ");\n";
            $phpcode .= "\tconst AUTO_INCREMENTED_KEYS = array(" . Util::createParamString($auto_increment, "\"%\"", ",") . ");\n\n";
            $phpcode .= Util::createParamString($columns, "\tpublic \$%;", "\n");
            $phpcode .= "\n\n";
            $phpcode .= "\tpublic function __construct(" . Util::createParamString($primary, "\$% = null", ",") . ") {\n";
            $phpcode .= "\t\tparent::__construct();\n";
            $phpcode .= Util::createParamString($primary, "\t\t\$this->% = \$%;", "\n");
            $phpcode .= "\n";
            $phpcode .= "\t}\n";
            $phpcode .= "}\n";
            $phpcode .= "?>";

            file_put_contents($this->entity_directory . "/" . ucfirst($tablename) . ".php", str_replace("\t", "    ", $phpcode));
        }
    }
}
