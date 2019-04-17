<?php

namespace Aftermarketpl\PHP2JS\Context;

use Aftermarketpl\PHP2JS\Context;

class FunctionCall extends Context
{
    protected $replace;
    
    protected $args;

    public function __construct($translator, $replace)
    {
        parent::__construct($translator);
        
        $this->replace = $replace;
        $this->args = array();
    }
    
    public function handleCharacter($char)
    {
        if($char == ",")
        {
            $this->args[] = $this->getResult();
            $this->clear();
        }
        else parent::handleCharacter($char);
    }
    
    protected function afterFirstBracket()
    {
        $this->popElement();
    }

    protected function afterLastBracket()
    {
        $this->popElement();
        $this->args[] = $this->getResult();
        $result = $this->replace;
        for($i = 0; $i < 10; $i++)
        {
            $result = str_replace("%" . ($i+1), $this->args[$i] ? $this->args[$i] : "undefined", $result);
        }
        $this->result = array("(" . $result . ")");
        $this->translator->popContext();
    }
}

?>