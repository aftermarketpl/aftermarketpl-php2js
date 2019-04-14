<?php

spl_autoload_register(function ($class)
{
    $prefix = "Aftermarketpl\\PHP2JS\\";
    if(substr($class, 0, strlen($prefix)) != $prefix) return;
    
    $file = __DIR__ . DIRECTORY_SEPARATOR
        . str_replace("\\", DIRECTORY_SEPARATOR, substr($class, strlen($prefix)))
        . ".php";
    if(is_file($file))
        require($file);
});

?>