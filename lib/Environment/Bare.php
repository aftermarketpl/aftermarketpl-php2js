<?php

namespace Aftermarketpl\PHP2JS\Environment;

use Aftermarketpl\PHP2JS\Environment;

class Bare extends Environment
{
    const FUNCTIONS = [

        "intval" => "parseInt",
        "floatval" => "parseFloat",
        "stringval" => "String",
        "boolval" => "Boolean",

        "isset" => "(typeof %1 !== 'undefined')",
        "empty" => "Boolean",
        "unset" => "delete ",
    ];
}

?>