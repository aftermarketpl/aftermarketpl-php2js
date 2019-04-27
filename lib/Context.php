<?php

namespace Aftermarketpl\PHP2JS;

abstract class Context
{
    protected $variables;
    
    protected $parent;
    
    protected $global;
    
    public function __construct(Context $parent = null, Context $global = null)
    {
        $this->parent = $parent;
        $this->global = $global;
        $this->variables = array();
    }
    
    public function addVariable(string $name, bool $initialized = false, bool $type = null) : void
    {
        $this->variables[$name] = array(
            "initialized" => $initialized,
            "type" => $type,
        );
    }
    
    public function getVariables() : array
    {
        return array_keys($this->variables);
    }
}

?>