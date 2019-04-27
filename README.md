# aftermarketpl-php2js

This project aims to create a fairly complete PHP to JavaScript transpiler.

Its primary purpose is to aid internal development of [AfterMarket.pl](https://www.aftermarket.pl/), but since it may also be of an interest to the general public, it is released under Apache license and available as a composer package.

## Project aims 
The most important design goals of the converter are as follows:
* It is intended for development of new code, rather than converting existing code. It will **not** magically convert all your existing legacy PHP code to JavaScript, because it would require effort that is well beyond the scope of the project. But if you write your PHP code bearing in mind the transpiler's limitations, it should be robust enough for most of your needs.
* The resulting JavaScript code is meant to be run in a browser, not in a server environment. This means that it does not use any fancy Node.js modules or features, which also further limits the range of accepted PHP constructs and features.
* The resulting JavaScript code should be human-readable and easily correspond to the input PHP code, so that it can be easily inspected and debugged by humans.

## Limitations
Since it is not a general purpose transpiler, there are limits on what the transpiler accepts. If it encounters PHP code which it cannot transpile properly, it will normally throw an exception, although in some edge cases it can produce JavaScript code from PHP code it should theoretically not support; such code is not guaranteed to run properly.

At this point the transpiler will convert most of **inline** PHP code, which does not contain any function or class definitions. Thus, the following sample PHP code will translate nicely to JavaScript:
```php
$d = $a ? $b + $c : $b ** $c;
```
The resulting JavaScript is:
```JavaScript
d = a ? (b + c) : (Math.pow(b, c));
```

### PHP syntax not yet accepted:

The following PHP constructs are not supported at the moment, and will likely not be supported at all, although we may implement some of them if time permits:

**Computed variable and function names**

Seriously, you really shouldn't be doing this anyway.
```php
$a = $$b;
$a = $b(); // May be supported in the future
```

**String subscripting with brackets**

This mainly stems from the fact that the transpiler does not know whether the variable is an array or a string. With PHP 7 type hinting, this may be improved in the future if the type of the variable can be deduced.
```php
$a = "string";
$b = $a[1]; // Use substr() instead
```
**Some array operations**

Again, with the plus operator the transpiler does not know if the variable is a numeric or an array. PHP 7 type hinting may improve this as well.
```php
$a = $array + $array2;
```

**Some binary and assigmment operators**

Some of these operators may be supported with ugly JavaScript, so the situation may improve.
```php
$a = $b <=> $c; // May be supported in the future
$a = $b xor $c;
$a = $b ?: $c; // May be supported in the future
$a ??= $b;
$a **= $c;
```

**Variable references**

They cannot be easily reproduced in JavaScript in general.
```php
$a = &$b;
function(&$a) {}
```

**Multi-leve break and continue**

They cannot be easily reproduced in JavaScript in general.
```php
continue 2;
break $a;
```

### Functions, classes and exceptions:

These are at the moment not supported at all, but we intend to work on them so you can expect a fairly broad support in the future.

## PHP standard library

PHP is not just a language; it is also a quite extensive library of standard functions such as `substr()` or `preg_match()`. We intend to implement a broad range of these functions, but since it requires a significant effort, it will take us some time to get there. Some functions will never be supported because they make no sense in a browser environment (such as file I/O). Some functions require too much effort compared to expected gain, so we may choose not to implement them (although you can do it, and we will gladly incpororate your code!). In general, we want to focus on string and array functions first because that's what we use in our own code.

As a teaser, a few string functions are implemented already, so you can try code such as this:
```php
$a = strlen($b);
$a = substr($b, $c, 1);
$a = strtolower($b);
```
The resulting JavaScript will be:
```JavaScript
a = (b).length;
a = (b).charAt(c);
a = (b).toLowerCase();
```
