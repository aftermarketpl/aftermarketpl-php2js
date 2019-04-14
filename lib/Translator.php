<?php

namespace Aftermarketpl\PHP2JS;

class Translator
{
    protected $tokens;
    
    protected $result;
    
    protected $line;
    
    protected $beforeSemicolon;
    
    protected $curlyLevel;
    protected $bracketLevel;
    protected $holdLevel;
    
    protected $curlyActions;
    protected $bracketActions;
    protected $holdActions;

    public function __construct($source)
    {
        $this->tokens = token_get_all($source);
    }

    public function getResult()
    {
        $this->result = "";
        $this->beforeSemicolon = "";

        $this->curlyLevel = $this->bracketLevel = $this->holdLevel = 0;
        $this->curlyActions = $this->bracketActions = $this->holdActions = array();
        
        $this->holdAction();

        foreach($this->tokens as $token)
        {
            if(is_array($token))
            {
                if($token[0] == T_WHITESPACE)
                {
                    $this->emit($token[1]);
                }
                else
                {
                    $this->handleToken($token[2], substr(token_name($token[0]), 2), $token[1]);
                }
            }
            else
            {
                switch($token)
                {
                    case "{":
                        $this->curlyOpen();
                        break;
                    case "}":
                        $this->curlyClose();
                        break;
                    case "(":
                        $this->bracketOpen();
                        break;
                    case ")":
                        $this->bracketClose();
                        break;
                    default:
                        $this->handleCharacter($token);
                }
            }
        }
        
        $this->holdFinalize();
        
        return $this->result;
    }
    
    protected function emit($text)
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
    
    protected function holdAction($fnEmit = "holdCopy", $content = array())
    {
        $this->holdActions[$this->holdLevel] = $content;
        $this->holdActions[$this->holdLevel]["fnEmit"] = $fnEmit;
        $this->holdActions[$this->holdLevel]["content"] = "";
        $this->holdLevel++;
    }
    
    protected function holdCopy($text)
    {
        $this->holdActions[$this->holdLevel-1]["content"] .= $text;
    }
    
    protected function holdPop()
    {
        return $this->holdActions[--$this->holdLevel]["content"];
    }
    
    protected function holdFinalize()
    {
        $this->emit($this->holdPop());
    }
    
    protected function bracketAction($fnFinish, $content = array())
    {
        $this->bracketActions[$this->bracketLevel] = $content;
        $this->bracketActions[$this->bracketLevel]["fnFinish"] = $fnFinish;
    }
    
    protected function curlyOpen()
    {
        $this->curlyLevel++;
        $this->emit("{");
    }
    
    protected function curlyClose()
    {
        $this->emit("}");
        $this->curlyLevel--;
    }
    
    protected function bracketOpen()
    {
        $this->bracketLevel++;
        $this->emit("(");
    }
    
    protected function bracketClose()
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
    
    protected function handleCharacter($char)
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

    protected function handleToken($line, $token, $contents)
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
    
    protected function handle_OPEN_TAG($text)
    {
        // Do nothing
    }

    protected function handle_CLOSE_TAG($text)
    {
        // Do nothing
    }

    protected function handle_COMMENT($text)
    {
        // Do nothing
    }

    protected function handle_LNUMBER($text)
    {
        $this->emit(intval($text));
    }

    protected function handle_DNUMBER($text)
    {
        $this->emit(floatval($text));
    }

    protected function handle_CONSTANT_ENCAPSED_STRING($text)
    {
        $this->emit($text);
    }

    protected function handle_VARIABLE($text)
    {
        $this->emit(substr($text, 1));
    }
    
    protected function handle_STRING($text)
    {
        $text = $this->translateString($text);
        if($text)
            $this->emit($text);
    }
    
    protected function translateString($text)
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

    protected function handle_IS_EQUAL($text)
    {
        $this->emit("==");
    }

    protected function handle_IS_NOT_EQUAL($text)
    {
        $this->emit("!=");
    }

    protected function handle_INC($text)
    {
        $this->emit("++");
    }

    protected function handle_DEC($text)
    {
        $this->emit("--");
    }

    protected function handle_IF($text)
    {
        $this->emit("if");
    }

    protected function handle_ELSE($text)
    {
        $this->emit("else");
    }

    protected function handle_ELSEIF($text)
    {
        $this->emit("else if");
    }

    protected function handle_ECHO($text)
    {
        $this->emit("console.log(");
        $this->beforeSemicolon = ")";
    }

    protected function handle_PRINT($text)
    {
        $this->handle_ECHO($text);
    }

    protected function handle_ISSET($text)
    {
        $this->holdAction();
        $this->bracketAction("finish_ISSET");
    }
    
    protected function finish_ISSET($elem)
    {
        $expr = $this->holdPop();
        $this->emit("(typeof " . $expr . " !== 'undefined')");
    }

    protected function handle_EMPTY($text)
    {
        $this->emit("Boolean");
    }

    protected function handle_UNSET($text)
    {
        $this->emit("delete ");
    }
}

?>