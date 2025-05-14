/*
   +----------------------------------------------------------------------+
   | Copyright (c) The PHP Group                                          |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | https://www.php.net/license/3_01.txt                                 |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Authors: Frank Denis <jedisct1@php.net>                              |
   +----------------------------------------------------------------------+
*/

#include "php.h"

#include "sodium.h"

/* {{{ php_bin2hex */
zend_string *php_bin2hex(const unsigned char *bin, const size_t bin_len)
{
	zend_string *hex;
	size_t hex_len;

	hex_len = bin_len * 2U;
	hex = zend_string_alloc(hex_len, 0);

	sodium_bin2hex(ZSTR_VAL(hex), hex_len + 1U, bin, bin_len);
	ZSTR_VAL(hex)[hex_len] = 0;

	return hex;
}
/* }}} */

/* {{{ php_hex2bin */
zend_string *php_hex2bin(const unsigned char *hex, const size_t hex_len)
{
	zend_string *bin;
	size_t bin_len;

	bin_len = hex_len/2;
	bin = zend_string_alloc(bin_len, 0);

	if (sodium_hex2bin((unsigned char *) ZSTR_VAL(bin), bin_len, hex, hex_len, NULL, NULL, NULL) != 0) {
		zend_string_efree(bin);
		return NULL;
	}

	ZSTR_VAL(bin)[bin_len] = 0;

	return bin;
}
/* }}} */
