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
   | Authors: Rasmus Lerdorf <rasmus@php.net>                             |
   |          Stig Sæther Bakken <ssb@php.net>                          |
   |          Zeev Suraski <zeev@php.net>                                 |
   +----------------------------------------------------------------------+
 */

#include "php.h"

/* this is read-only, so it's ok */
ZEND_SET_ALIGNED(16, static const char hexconvtab[]) = "0123456789abcdef";

/* {{{ php_bin2hex */
zend_string *php_bin2hex(const unsigned char *old, const size_t oldlen)
{
	zend_string *result;
	size_t i, j;

	result = zend_string_safe_alloc(oldlen, 2 * sizeof(char), 0, 0);

	for (i = j = 0; i < oldlen; i++) {
		ZSTR_VAL(result)[j++] = hexconvtab[old[i] >> 4];
		ZSTR_VAL(result)[j++] = hexconvtab[old[i] & 15];
	}
	ZSTR_VAL(result)[j] = '\0';

	return result;
}
/* }}} */

/* {{{ php_hex2bin */
zend_string *php_hex2bin(const unsigned char *old, const size_t oldlen)
{
	size_t target_length = oldlen >> 1;
	zend_string *str = zend_string_alloc(target_length, 0);
	unsigned char *ret = (unsigned char *)ZSTR_VAL(str);
	size_t i, j;

	for (i = j = 0; i < target_length; i++) {
		unsigned char c = old[j++];
		unsigned char l = c & ~0x20;
		int is_letter = ((unsigned int) ((l - 'A') ^ (l - 'F' - 1))) >> (8 * sizeof(unsigned int) - 1);
		unsigned char d;

		/* basically (c >= '0' && c <= '9') || (l >= 'A' && l <= 'F') */
		if (EXPECTED((((c ^ '0') - 10) >> (8 * sizeof(unsigned int) - 1)) | is_letter)) {
			d = (l - 0x10 - 0x27 * is_letter) << 4;
		} else {
			zend_string_efree(str);
			return NULL;
		}
		c = old[j++];
		l = c & ~0x20;
		is_letter = ((unsigned int) ((l - 'A') ^ (l - 'F' - 1))) >> (8 * sizeof(unsigned int) - 1);
		if (EXPECTED((((c ^ '0') - 10) >> (8 * sizeof(unsigned int) - 1)) | is_letter)) {
			d |= l - 0x10 - 0x27 * is_letter;
		} else {
			zend_string_efree(str);
			return NULL;
		}
		ret[i] = d;
	}
	ret[i] = '\0';

	return str;
}
/* }}} */
