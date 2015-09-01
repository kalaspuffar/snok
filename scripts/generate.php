<?php
    require_once(__DIR__ . "/../vendor/autoload.php");
    require_once(__DIR__ . "/../src/classes/EntityGenerator.php");

    $generator = new \Snok\EntityGenerator();
    $generator->generateAll();
