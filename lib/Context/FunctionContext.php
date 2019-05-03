<?php

namespace Aftermarketpl\PHP2JS\Context;

use Aftermarketpl\PHP2JS\Context;
use PhpParser\Node\Stmt\Function_;

class FunctionContext extends Context
{
    protected $node;

    protected $parameters;

    protected $globals;
    
    protected $returnType;

    public function __construct(Function_ $node = null, Context $parent = null)
    {
        parent::__construct($parent->getGlobal(), $parent);
        $this->node = $node;
        $this->parameters = array();
        $this->globals = array();
        $this->returnType = Context::UNKNOWN;
    }
    
    public function getNode()
    {
        return $this->node;
    }

    public function addParameter(string $name, int $type = self::UNKNOWN) : int
    {
        if(!in_array($name, $this->parameters))
            $this->parameters[] = $name;
        $this->addVariable($name, true, $type);
        return count($this->parameters);
    }
    
    public function getParameters()
    {
        return $this->parameters;
    }

    public function addGlobal(string $name) : void
    {
        if(!in_array($name, $this->globals))
            $this->globals[] = $name;
    }

    public function addVariable(string $name, bool $initialized = false, int $type = self::UNKNOWN) : void
    {
        if(in_array($name, $this->globals))
            $this->global->addVariable($name, $initialized, $type);
        else
            parent::addVariable($name, $initialized, $type);
    }

    public function initializeVariable(string $name) : void
    {
        if(in_array($name, $this->globals))
            $this->global->initializeVariable($name);
        else
            parent::initializeVariable($name);
    }

    public function typeVariable(string $name, int $type) : void
    {
        if(in_array($name, $this->globals))
            $this->global->typeVariable($name, $type);
        else
            parent::typeVariable($name, $type);
    }

    protected function findVariable(string $name) : array
    {
        if(in_array($name, $this->globals))
            return $this->global->findVariable($name);
        else
            return parent::findVariable($name);
    }
    
    public function setReturnType(int $type) : void
    {
        if($type != Context::UNKNOWN)
        {
            if($this->returnType == Context::UNKNOWN)
                $this->dirty = true;
            $this->returnType = $type;
            $this->global->addFunction($this->node->name->name, $type);
        }
    }

    public function getReturnType() : int
    {
        return $this->returnType;
    }
}

?>