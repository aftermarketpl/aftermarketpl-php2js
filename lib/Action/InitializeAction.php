<?php

namespace Aftermarketpl\PHP2JS\Action;

use Aftermarketpl\PHP2JS\Action;
use Aftermarketpl\PHP2JS\Context;

class InitializeAction extends Action
{
    public function onVariable(string $name)
    {
        $this->context->initializeVariable($name);
    }
}

?>