<?php

namespace Aftermarketpl\PHP2JS\Environment;

use Aftermarketpl\PHP2JS\Environment;

class Bare extends Environment
{
    const FUNCTIONS = [

        "intval" => "parseInt",
        "floatval" => "parseFloat",
        "stringval" => "String(%1).toString()",
        "boolval" => "Boolean",

        "isset" => "typeof %1 !== 'undefined'",
        "empty" => "Boolean(%1)?true:false",
        "unset" => "delete ",
        
        "strlen" => "(%1).length",
        "substr" => "(%1).substr(%2, %3)",
        "strtolower" => "(%1).toLowerCase()",
        "strtoupper" => "(%1).toUpperCase()",

        "mb_strlen" => "(%1).length",
        "mb_substr" => "(%1).substr(%2, %3)",
        "mb_strtolower" => "(%1).toLowerCase()",
        "mb_strtoupper" => "(%1).toUpperCase()",
    ];
}

?>