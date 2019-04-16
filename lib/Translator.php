<?php

namespace Aftermarketpl\PHP2JS;

class Translator
{
    protected $tokens;
    
    protected $line;
    
    protected $contexts;

    public function __construct($source)
    {
        $this->tokens = token_get_all($source);
        
        $this->contexts = array();
    }

    public function getResult()
    {
        $this->contexts[] = new Context($this);

        foreach($this->tokens as $token)
        {
            $context = $this->contexts[count($this->contexts)-1];
            if(is_array($token))
            {
                if($token[0] == T_WHITESPACE)
                {
                    $context->emit($token[1]);
                }
                else
                {
                    $context->handleToken($token[2], substr(token_name($token[0]), 2), $token[1]);
                }
            }
            else
            {
                switch($token)
                {
                    case "{":
                        $context->curlyOpen();
                        break;
                    case "}":
                        $context->curlyClose();
                        break;
                    case "(":
                        $context->bracketOpen();
                        break;
                    case ")":
                        $context->bracketClose();
                        break;
                    default:
                        $context->handleCharacter($token);
                }
            }
        }
        
        return $this->contexts[0]->getResult();
    }
    
}

?>