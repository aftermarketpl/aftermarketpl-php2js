<?php

namespace Aftermarketpl\PHP2JS;

abstract class Environment
{
    const FUNCTIONS = [];
    
    public function translateFunction($name)
    {
        if(isset($this::FUNCTIONS[$name]))
            return $this::FUNCTIONS[$name];
    }
}

?>