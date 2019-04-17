<?php

namespace Aftermarketpl\PHP2JS\Context;

use Aftermarketpl\PHP2JS\Context;

class ArrayDefinition extends Context
{
    protected $isObject;

    public function __construct($translator)
    {
        parent::__construct($translator);
        
        $this->isObject = false;
    }
    
    public function handle_DOUBLE_ARROW()
    {
        $this->emit(":");
        $this->isObject = true;
    }
    
    protected function afterFirstBracket()
    {
        $this->popElement();
    }

    protected function afterLastBracket()
    {
        $this->popElement();
        $result = $this->getResult();
        $this->clear();
        if($this->isObject)
            $this->emit("{" . $result . "}");
        else
            $this->emit("[" . $result . "]");
        $this->translator->popContext();
    }
}

?>