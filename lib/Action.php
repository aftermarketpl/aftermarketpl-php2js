<?php

namespace Aftermarketpl\PHP2JS;

abstract class Action
{
    protected $context;
    
    public function __construct(Context $context)
    {
        $this->context = $context;
    }
    
    public abstract function onVariable(string $name);
}

?>