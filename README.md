# aftermarketpl-php2js

This project aims to create a quick and dirty PHP to JavaScript converter.

Its primary purpose is to aid internal development of AfterMarket.pl,
but since it may also be of an interest to the general public,
it is released under Apache license and available as a composer package.

This project **intentionally** does not use any PHP to AST translation,
which would be a theoretically correct but very tedious process.
Instead, because PHP and JavaScript are syntactically very close,
the `token_get_all()` function is used, and PHP tokens are converted directly
to appropriate JavaScript tokens with clever hacks where the syntac differs.
It's called "quick and dirty" for a reason.

At the moment, the project is hopelessly incomplete, but in the future
you can reasonably expect it to convert a broad range of PHP scripts to
valid JavaScript. However, some PHP language constructs may not translate to
JavaScript easily, and we may choose not to support them if we don't
consider the effort worthwile. Stay tuned.
