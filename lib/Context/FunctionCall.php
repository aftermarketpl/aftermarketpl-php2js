<?php

namespace Aftermarketpl\PHP2JS\Context;

use Aftermarketpl\PHP2JS\Context;

class FunctionCall extends Context
{
    protected $replace;

    public function __construct($translator, $replace)
    {
        parent::__construct($translator);
        
        $this->replace = $replace;
    }
    
    protected function afterLastBracket()
    {
        $this->result = str_replace("%1", $this->result, $this->replace);
        $this->translator->popContext();
    }
}

?>