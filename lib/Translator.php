<?php

namespace Aftermarketpl\PHP2JS;

class Translator
{
    public static function translate($code, $env = null)
    {
        if(!$env) $env = new Environment();
        $parser = new Parser($code, $env);
        return $parser->parse();
    }
}
