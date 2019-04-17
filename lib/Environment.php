<?php

namespace Aftermarketpl\PHP2JS;

abstract class Environment
{
    const BASE_FUNCTIONS = [

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
    const FUNCTIONS = [];
    
    public function translateFunction($name)
    {
        if(isset($this::FUNCTIONS[$name]))
            return $this::FUNCTIONS[$name];
        elseif(isset($this::BASE_FUNCTIONS[$name]))
            return $this::BASE_FUNCTIONS[$name];
    }
}

?>