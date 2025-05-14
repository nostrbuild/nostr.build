#ifndef HEX_CONVERSIONS_H
#define HEX_CONVERSIONS_H

/*
   Loosely based on sodium_bin2hex() and sodium_hex2bin() from
   php-src/ext/sodium/libsodium.c at PHP version 8.4.4
*/

zend_string *php_bin2hex(const unsigned char *bin, const size_t bin_len);
zend_string *php_hex2bin(const unsigned char *hex, const size_t hex_len);

#endif /* HEX_CONVERSIONS_H */
