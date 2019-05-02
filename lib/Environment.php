<?php

namespace Aftermarketpl\PHP2JS;

class Environment
{
    const BASE_FUNCTIONS = [

        "intval" => "parseInt",
        "floatval" => "parseFloat",
        "stringval" => "String",
        "boolval" => "Boolean",

        "chr" => "String.fromCharCode",
        "chop" => "(%1).trimEnd()",
        "ltrim" => "(%1).trimStart()",
        "rtrim" => "(%1).trimEnd()",
        "strlen" => "(%1).length",
        "substr" => "?",
        "strtolower" => "(%1).toLowerCase()",
        "strtoupper" => "(%1).toUpperCase()",
        "strrev" => "(%1).split('').reverse().join('')",
        "trim" => "(%1).trim()",

        "mb_strlen" => "(%1).length",
        "mb_substr" => "?",
        "mb_strtolower" => "(%1).toLocaleLowerCase()",
        "mb_strtoupper" => "(%1).toLocaleUpperCase()",
    ];
    const FUNCTIONS = [];
    
    public function translateFunction($name, $args)
    {
        if(isset($this::FUNCTIONS[$name]))
            $name2 = $this::FUNCTIONS[$name];
        elseif(isset($this::BASE_FUNCTIONS[$name]))
            $name2 = $this::BASE_FUNCTIONS[$name];
        if($name2 == "?")
        {
            return $this->generateFunction($name, $args);
        }
        else if(strpos($name2, "%") === false)
        {
            return $name2 . "(" . join(", ", $args) . ")";
        }
        else
        {
            $ret = preg_split("/(%[0-9]+)/", $name2, 0, PREG_SPLIT_DELIM_CAPTURE);
            $ret2 = array();
            foreach($ret as $item)
            {
                if(!preg_match("/^%([0-9]+)$/", $item, $a))
                {
                    $ret2[] = $item;
                }
                else
                {
                    $ret2[] = isset($args[$a[1]-1]) ? $args[$a[1]-1] : "undefined";
                }
            }
            return join("", $ret2);
        }
    }
    
    protected function generateFunction($name, $args)
    {
        switch($name)
        {
            case "substr": case "mb_substr":
                if(count($args) <= 1)
                {
                    throw new \Exception("Invalid number of arguments to " . $name);
                }
                else if(count($args) == 2)
                {
                    return "(" . $args[0] . ").substr(" . $args[1] . ")";
                }
                else
                {
                    if(is_numeric($args[2]))
                    {
                        if($args[2] == 1)
                            return "(" . $args[0] . ").charAt(" . $args[1] . ")";
                        elseif($args[2] >= 0)
                            return "(" . $args[0] . ").substr(" . $args[1] . ", " . $args[2] . ")";
                    }
                    return "(function(s, x, y){ if(y < 0) return s.substring(x, s.length+y); else return s.substr(x, y); })(" . $args[0] . ", " . $args[1] . ", " . $args[2] . ")";
                }
        }
    }
}

?>