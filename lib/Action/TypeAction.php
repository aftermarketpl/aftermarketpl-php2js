<?php

namespace Aftermarketpl\PHP2JS\Action;

use Aftermarketpl\PHP2JS\Action;
use Aftermarketpl\PHP2JS\Context;

class TypeAction extends Action
{
    private $type;

    public function __construct(Context $context, int $type = Context::UNKNOWN)
    {
        parent::__construct($context);
        $this->type = $type;
    }

    public function onVariable(string $name)
    {
        if($this->type != Context::UNKNOWN) $this->context->typeVariable($name, $this->type);
    }
}

?>