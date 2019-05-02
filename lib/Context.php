<?php

namespace Aftermarketpl\PHP2JS;

abstract class Context
{
    protected $variables;
    protected $parent;
    protected $global;
    protected $dirty;
    
    const UNKNOWN = 0;
    const NUMBER = 1;
    const STRING = 2;
    const ARRAY = 3;
    const OBJECT = 4;
    
    public function __construct(Context $global = null, Context $parent = null)
    {
        $this->parent = $parent;
        $this->global = $global;
        $this->variables = array();
        $this->dirty = false;
    }
    
    public function getParent()
    {
        return $this->parent ?? $this;
    }
    
    public function getGlobal()
    {
        return $this->global ?? $this;
    }
    
    public function clearDirty() : void
    {
        $this->dirty = false;
    }
    
    public function isDirty() : bool
    {
        return $this->dirty;
    }
    
    public function addVariable(string $name, bool $initialized = false, int $type = self::UNKNOWN) : void
    {
        if(!isset($this->variables[$name]))
        {
            $this->variables[$name] = array(
                "initialized" => $initialized,
                "type" => $type,
            );
            $this->dirty = true;
        }
        else
        {
            if($initialized)
            {
                if(!$this->variables[$name]["initialized"])
                    $this->dirty = true;
                $this->variables[$name]["initialized"] = true;
            }
            if($type != self::UNKNOWN)
            {
                if($this->variables[$name]["type"] == self::UNKNOWN)
                    $this->dirty = true;
                $this->variables[$name]["type"] = $type;
            }
        }
    }
    
    public function initializeVariable(string $name) : void
    {
        if(!isset($this->variables[$name]))
        {
            $this->addVariable($name);
        }
        if(!$this->variables[$name]["initialized"])
            $this->dirty = true;
        $this->variables[$name]["initialized"] = true;
    }
    
    public function typeVariable(string $name, int $type) : void
    {
        if(!isset($this->variables[$name]))
        {
            $this->addVariable($name);
        }
        if($type != self::UNKNOWN)
        {
            if($this->variables[$name]["type"] == self::UNKNOWN)
                $this->dirty = true;
            $this->variables[$name]["type"] = $type;
        }
    }
    
    protected function findVariable(string $name) : array
    {
        return $this->variables[$name] ?? array();
    }
    
    public function getType(string $name) : int
    {
        $var = $this->findVariable($name);
        return $var["type"] ?? self::UNKNOWN;
    }
    
    public function getInitialized(string $name) : bool
    {
        $var = $this->findVariable($name);
        return $var["initialized"] ?? false;
    }
    
    public function getVariables() : array
    {
        return array_keys($this->variables);
    }

    public function setReturnType(int $type) : void
    {
    }
}

?>