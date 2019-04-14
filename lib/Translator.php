<?php

namespace Aftermarketpl\PHP2JS;

class Translator
{
    protected $tokens;
    
    protected $result;
    
    protected $line;
    
    protected $beforeSemicolon;

    public function __construct($source)
    {
        $this->tokens = token_get_all($source);
    }
    
    public function getResult()
    {
        $this->result = "";
        $this->beforeSemicolon = "";

        foreach($this->tokens as $token)
        {
            if(is_array($token))
            {
                $this->handleToken($token[2], token_name($token[0]), $token[1]);
            }
            else
            {
                $this->handleCharacter($token);
            }
        }
        
        return $this->result;
    }
    
    protected function emit($code)
    {
        $this->result .= $code;
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

        $method = $token;
        if(method_exists($this, $method))
        {
            $this->$method($contents);
        }
        else
        {
            throw new \Exception("Cannot handle token " . $token . " at line " . $line);
        }
    }
    
    protected function T_OPEN_TAG($text)
    {
        // Do nothing
    }

    protected function T_CLOSE_TAG($text)
    {
        // Do nothing
    }

    protected function T_COMMENT($text)
    {
        // Do nothing
    }

    protected function T_WHITESPACE($text)
    {
        $this->emit($text);
    }

    protected function T_LNUMBER($text)
    {
        $this->emit(intval($text));
    }

    protected function T_DNUMBER($text)
    {
        $this->emit(floatval($text));
    }

    protected function T_CONSTANT_ENCAPSED_STRING($text)
    {
        $this->emit($text);
    }

    protected function T_VARIABLE($text)
    {
        $this->emit(substr($text, 1));
    }

    protected function T_INC($text)
    {
        $this->emit("++");
    }

    protected function T_DEC($text)
    {
        $this->emit("--");
    }

    protected function T_ECHO($text)
    {
        $this->emit("console.log(");
        $this->beforeSemicolon = ")";
    }

    protected function T_PRINT($text)
    {
        return $this->T_ECHO($text);
    }
}

?>