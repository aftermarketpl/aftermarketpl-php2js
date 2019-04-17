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
    
    public function __construct($translator)
    {
        $this->translator = $translator;
        $this->result = "";

        $this->curlyLevel = $this->bracketLevel = $this->holdLevel = 0;
    }
    
    public function getResult()
    {
        return $this->result;
    }
    
    public function emit($text)
    {
        $this->result .= $text;
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
        if(!$this->bracketLevel)
            $this->afterLastBracket();
    }
    
    protected function afterLastBracket()
    {
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
        if($text != "true" && $text != "false" && $text != "null")
        {
            $this->translateFunction($text);
        }
        else
        {
            $this->emit($text);
        }
    }
    
    public function translateFunction($function)
    {
        $function = $this->translator->getEnvironment()->translateFunction($function);
        if(strpos($function, "%") === false)
        {
            $this->emit($function);
        }
        else
        {
            $this->translator->pushContext(new Context\FunctionCall($this->translator, $function));
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

    public function handle_IS_GREATER_OR_EQUAL($text)
    {
        $this->emit(">=");
    }

    public function handle_IS_SMALLER_OR_EQUAL($text)
    {
        $this->emit("<=");
    }

    public function handle_IS_IDENTICAL($text)
    {
        $this->emit("===");
    }

    public function handle_IS_NOT_IDENTICAL($text)
    {
        $this->emit("!==");
    }

    public function handle_INC($text)
    {
        $this->emit("++");
    }

    public function handle_DEC($text)
    {
        $this->emit("--");
    }

    public function handle_PLUS_EQUAL($text)
    {
        $this->emit("+=");
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

    public function handle_BREAK($text)
    {
        $this->emit("break");
    }

    public function handle_CASE($text)
    {
        $this->emit("case");
    }

    public function handle_CONTINUE($text)
    {
        $this->emit("continue");
    }

    public function handle_DEFAULT($text)
    {
        $this->emit("default");
    }

    public function handle_DO($text)
    {
        $this->emit("do");
    }

    public function handle_FOR($text)
    {
        $this->emit("for");
    }

    public function handle_FUNCTION($text)
    {
        $this->emit("function");
    }

    public function handle_RETURN($text)
    {
        $this->emit("return");
    }

    public function handle_SWITCH($text)
    {
        $this->emit("switch");
    }

    public function handle_WHILE($text)
    {
        $this->emit("while");
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
        $this->translateFunction("isset");
    }
    
    public function handle_EMPTY($text)
    {
        $this->translateFunction("empty");
    }

    public function handle_UNSET($text)
    {
        $this->translateFunction("unset");
    }
}

?>