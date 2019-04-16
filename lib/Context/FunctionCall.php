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
            $this->args[] = $this->result;
            $this->result = "";
        }
        else parent::handleCharacter($char);
    }
    
    protected function afterLastBracket()
    {
        $this->args[] = $this->result;
        $this->args[0] = substr($this->args[0], 1);
        $this->args[count($this->args)-1] = substr($this->args[count($this->args)-1], 0, -1);
        $result = $this->replace;
        for($i = 0; $i < 10; $i++)
        {
            $result = str_replace("%" . ($i+1), $this->args[$i] ? $this->args[$i] : "undefined", $result);
        }
        $this->result = "(" . $result . ")";
        $this->translator->popContext();
    }
}

?>