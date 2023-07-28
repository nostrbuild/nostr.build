#ifndef HEX_CONVERSIONS_H
#define HEX_CONVERSIONS_H

/*
    The native C functions for hex2bin() and bin2hex() had to be yanked out
    of the PHP codebase because they are declared as static functions that
    aren't visible outside of their own source file.

    hex_conversions.c is essentially a stripped down version of
    php-src/ext/standard/string.c, taken from PHP version 8.2.8.
*/

zend_string *php_bin2hex(const unsigned char *old, const size_t oldlen);
zend_string *php_hex2bin(const unsigned char *old, const size_t oldlen);

#endif /* HEX_CONVERSIONS_H */
