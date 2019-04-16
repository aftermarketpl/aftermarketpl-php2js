<?php

namespace Aftermarketpl\PHP2JS;

class Context
{
    protected $translator;
    protected $result;
    
    protected $beforeSemicolon;
    
    protected $curlyLevel;
    protected $bracketLevel;
    protected $holdLevel;
    
    protected $curlyActions;
    protected $bracketActions;
    protected $holdActions;

    public function __construct($translator)
    {
        $this->translator = $translator;
        $this->result = "";

        $this->curlyLevel = $this->bracketLevel = $this->holdLevel = 0;
        $this->curlyActions = $this->bracketActions = $this->holdActions = array();
    }
    
    public function getResult()
    {
        return $this->result;
    }
    
    public function emit($text)
    {
        if($this->holdLevel > 0)
        {
            $function = $this->holdActions[$this->holdLevel-1]["fnEmit"];
            $this->$function($text);
        }
        else
        {
            $this->result .= $text;
        }
    }
    
    public function holdAction($fnEmit = "holdCopy", $content = array())
    {
        $this->holdActions[$this->holdLevel] = $content;
        $this->holdActions[$this->holdLevel]["fnEmit"] = $fnEmit;
        $this->holdActions[$this->holdLevel]["content"] = "";
        $this->holdLevel++;
    }
    
    public function holdCopy($text)
    {
        $this->holdActions[$this->holdLevel-1]["content"] .= $text;
    }
    
    public function holdPop()
    {
        return $this->holdActions[--$this->holdLevel]["content"];
    }
    
    public function holdFinalize()
    {
        $this->emit($this->holdPop());
    }
    
    public function bracketAction($fnFinish, $content = array())
    {
        $this->bracketActions[$this->bracketLevel] = $content;
        $this->bracketActions[$this->bracketLevel]["fnFinish"] = $fnFinish;
    }
    
    public function curlyOpen()
    {
        $this->curlyLevel++;
        $this->emit("{");
    }
    
    public function curlyClose()
    {
        $this->emit("}");
        $this->curlyLevel--;
    }
    
    public function bracketOpen()
    {
        $this->bracketLevel++;
        $this->emit("(");
    }
    
    public function bracketClose()
    {
        $this->emit(")");
        $this->bracketLevel--;
        if($this->bracketActions[$this->bracketLevel])
        {
            $function = $this->bracketActions[$this->bracketLevel]["fnFinish"];
            $this->$function($this->bracketActions[$this->bracketLevel]);
            unset($this->bracketActions[$this->bracketLevel]);
        }
    }
    
    public function handleCharacter($char)
    {
        switch($char)
        {
            case ";":
                $this->emit($this->beforeSemicolon);
                $this->beforeSemicolon = "";
                break;
            case ".":
                $this->emit("+");
                return;
            case "$":
                throw new \Exception("Cannot handle \$");
        }

        $this->emit($char);
    }

    public function handleToken($line, $token, $contents)
    {
        $this->line = $line;

        $method = "handle_" . $token;
        if(method_exists($this, $method))
        {
            $this->$method($contents);
        }
        else
        {
            throw new \Exception("Cannot handle token T_" . $token . " at line " . $line);
        }
    }
    
    public function handle_OPEN_TAG($text)
    {
        // Do nothing
    }

    public function handle_CLOSE_TAG($text)
    {
        // Do nothing
    }

    public function handle_COMMENT($text)
    {
        // Do nothing
    }

    public function handle_LNUMBER($text)
    {
        $this->emit(intval($text));
    }

    public function handle_DNUMBER($text)
    {
        $this->emit(floatval($text));
    }

    public function handle_CONSTANT_ENCAPSED_STRING($text)
    {
        $this->emit($text);
    }

    public function handle_VARIABLE($text)
    {
        $this->emit(substr($text, 1));
    }
    
    public function handle_STRING($text)
    {
        $text = $this->translateString($text);
        if($text)
            $this->emit($text);
    }
    
    public function translateString($text)
    {
        switch($text)
        {
            case "intval":
                return "parseInt";
            case "floatval":
                return "parseFloat";
            case "stringval":
                return "String";
            case "boolval":
                return "Boolean";
        }
    }

    public function handle_IS_EQUAL($text)
    {
        $this->emit("==");
    }

    public function handle_IS_NOT_EQUAL($text)
    {
        $this->emit("!=");
    }

    public function handle_INC($text)
    {
        $this->emit("++");
    }

    public function handle_DEC($text)
    {
        $this->emit("--");
    }

    public function handle_IF($text)
    {
        $this->emit("if");
    }

    public function handle_ELSE($text)
    {
        $this->emit("else");
    }

    public function handle_ELSEIF($text)
    {
        $this->emit("else if");
    }

    public function handle_ECHO($text)
    {
        $this->emit("console.log(");
        $this->beforeSemicolon = ")";
    }

    public function handle_PRINT($text)
    {
        $this->handle_ECHO($text);
    }

    public function handle_ISSET($text)
    {
        $this->holdAction();
        $this->bracketAction("finish_ISSET");
    }
    
    public function finish_ISSET($elem)
    {
        $expr = $this->holdPop();
        $this->emit("(typeof " . $expr . " !== 'undefined')");
    }

    public function handle_EMPTY($text)
    {
        $this->emit("Boolean");
    }

    public function handle_UNSET($text)
    {
        $this->emit("delete ");
    }
}

?>