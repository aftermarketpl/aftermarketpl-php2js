<?php

echo "A" . "Test";
echo 1 + 2;

$b = 3;
$b++;

if($b == 4)
{
    $a = "A";
}
elseif($b != 5)
{
    $a = "B";
}
else
{
    $a = "C";
}

echo isset($a);
echo empty($a);
unset($a);

echo substr("Jeden", 2);
echo substr("Jeden", 2, 1);

?>