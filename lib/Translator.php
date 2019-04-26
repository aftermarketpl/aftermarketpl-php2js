<?php

namespace Aftermarketpl\PHP2JS;

class Translator
{
    public static function translate($code)
    {
        $parser = new Parser($code);
        return $parser->parse();
    }
}
