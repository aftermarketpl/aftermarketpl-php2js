<?php

namespace Aftermarketpl\PHP2JS\Context;

use Aftermarketpl\PHP2JS\Context;

class GlobalContext extends Context
{
    protected $functions;

    public function __construct(Context $global = null, Context $parent = null)
    {
        parent::__construct($global, $parent);
        $this->functions = array();
    }
    
    public function addFunction(string $name, int $type = Context::UNKNOWN) : void
    {
        if(!isset($this->functions[$name]))
        {
            $this->functions[$name] = array("return" => $type);
            if($type != Context::UNKNOWN)
                $this->dirty = true;
        }
        else if($type != Context::UNKNOWN)
        {
            if($this->functions[$name]["return"] == Context::UNKNOWN)
                $this->dirty = true;
            $this->functions[$name]["return"] = $type;
        }
    }
    
    public function hasFunction(string $name) : bool
    {
        return isset($this->functions[$name]);
    }
    
    public function functionReturnType(string $name) : int
    {
        if(!isset($this->functions[$name]))
            return Context::UNKNOWN;
        else
            return $this->functions[$name]["return"];
    }
}

?>