
console.log( "A" + "Test");
console.log( 1 + 2);

b = 3;
b++;

if(b == 4)
{
    a = "A";
}
else if(b != 5)
{
    a = "B";
}
else
{
    a = "C";
}

console.log( (typeof a !== 'undefined'));
console.log( (Boolean(a)?true:false));
delete (a);

console.log( (("Jeden").substr( 2, undefined)));
console.log( (("Jeden").substr( 2,  1)));

