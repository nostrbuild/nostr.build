/* secp256k1_nostr extension for PHP */

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "php.h"
#include "ext/spl/spl_exceptions.h"
#include "ext/standard/info.h"
#include "zend_exceptions.h"

#if PHP_VERSION_ID >= 80200
#include "ext/random/php_random.h"
#else
#include "ext/standard/php_random.h"
#endif

#include "secp256k1_nostr_arginfo.h"
#include "php_secp256k1_nostr.h"

#include "hex_conversions.h"

#include "secp256k1.h"
#include "secp256k1_extrakeys.h"
#include "secp256k1_schnorrsig.h"

/* For compatibility with older PHP versions */
#ifndef ZEND_PARSE_PARAMETERS_NONE
#define ZEND_PARSE_PARAMETERS_NONE() \
	ZEND_PARSE_PARAMETERS_START(0, 0) \
	ZEND_PARSE_PARAMETERS_END()
#endif

PHP_FUNCTION(secp256k1_nostr_derive_pubkey)
{
	zend_string* in_seckey;

	zend_string* binary_seckey;

	secp256k1_context* ctx;
	secp256k1_keypair keypair;
	secp256k1_xonly_pubkey xonly_pubkey;
	unsigned char tmp[32];

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(in_seckey)
	ZEND_PARSE_PARAMETERS_END();

	if (ZSTR_LEN(in_seckey) != 64) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_derive_pubkey(): Parameter 1 is not a hex-encoded 32 byte string");
		return;
	}

	binary_seckey = php_hex2bin((unsigned char *)ZSTR_VAL(in_seckey), ZSTR_LEN(in_seckey));
	if (binary_seckey == NULL) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_derive_pubkey(): Parameter 1 is not a hex-encoded 32 byte string");
		return;
	}

	if (php_random_bytes_throw(tmp, sizeof(tmp)) == FAILURE) {
		zend_string_efree(binary_seckey);
		zend_throw_exception_ex(spl_ce_RuntimeException, 0, "secp256k1_nostr_derive_pubkey(): Error fetching entropy for context randomization");
		return;
	}

	ctx = secp256k1_context_create(SECP256K1_CONTEXT_NONE);
	if (!secp256k1_context_randomize(ctx, tmp)) {
		zend_string_efree(binary_seckey);
		secp256k1_context_destroy(ctx);
		zend_throw_exception_ex(spl_ce_RuntimeException, 0, "secp256k1_nostr_derive_pubkey(): Error randomizing secp256k1 context");
		return;
	}

	if (!secp256k1_keypair_create(ctx, &keypair, (unsigned char *)ZSTR_VAL(binary_seckey))) {
		zend_string_efree(binary_seckey);
		secp256k1_context_destroy(ctx);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_derive_pubkey(): Parameter 1 is not a valid private key");
		return;
	}

	zend_string_efree(binary_seckey);

	// secp256k1_keypair_xonly_pub() always returns 1. The variable only exists to please GCC 11.3
	int one = secp256k1_keypair_xonly_pub(ctx, &xonly_pubkey, NULL, &keypair);
	secp256k1_xonly_pubkey_serialize(ctx, tmp, &xonly_pubkey);
	secp256k1_context_destroy(ctx);

	RETURN_STR(php_bin2hex(tmp, sizeof(tmp)));
}

PHP_FUNCTION(secp256k1_nostr_sign)
{
	zend_string* in_seckey;
	zend_string* in_hash;

	zend_string* binary_seckey;
	zend_string* binary_hash;

	secp256k1_context* ctx;
	secp256k1_keypair keypair;
	unsigned char auxiliary_rand[32];
	unsigned char signature[64];

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(in_seckey)
		Z_PARAM_STR(in_hash)
	ZEND_PARSE_PARAMETERS_END();

	if (ZSTR_LEN(in_seckey) != 64) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_sign(): Parameter 1 is not a hex-encoded 32 byte string");
		return;
	}

	binary_seckey = php_hex2bin((unsigned char *)ZSTR_VAL(in_seckey), ZSTR_LEN(in_seckey));
	if (binary_seckey == NULL) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_sign(): Parameter 1 is not a hex-encoded 32 byte string");
		return;
	}

	if (ZSTR_LEN(in_hash) != 64) {
		zend_string_efree(binary_seckey);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_sign(): Parameter 2 is not a hex-encoded 32 byte string");
		return;
	}

	binary_hash = php_hex2bin((unsigned char *)ZSTR_VAL(in_hash), ZSTR_LEN(in_hash));
	if (binary_hash == NULL) {
		zend_string_efree(binary_seckey);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_sign(): Parameter 2 is not a hex-encoded 32 byte string");
		return;
	}

	if (php_random_bytes_throw(auxiliary_rand, sizeof(auxiliary_rand)) == FAILURE) {
		zend_string_efree(binary_seckey);
		zend_string_efree(binary_hash);
		zend_throw_exception_ex(spl_ce_RuntimeException, 0, "secp256k1_nostr_sign(): Error fetching entropy for context randomization");
		return;
	}

	ctx = secp256k1_context_create(SECP256K1_CONTEXT_NONE);
	if (!secp256k1_context_randomize(ctx, auxiliary_rand)) {
		zend_string_efree(binary_seckey);
		zend_string_efree(binary_hash);
		secp256k1_context_destroy(ctx);
		zend_throw_exception_ex(spl_ce_RuntimeException, 0, "secp256k1_nostr_sign(): Error randomizing secp256k1 context");
		return;
	}

	if (!secp256k1_keypair_create(ctx, &keypair, (unsigned char *)ZSTR_VAL(binary_seckey))) {
		zend_string_efree(binary_seckey);
		zend_string_efree(binary_hash);
		secp256k1_context_destroy(ctx);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_sign(): Parameter 1 is not a valid private key");
		return;
	}

	if (php_random_bytes_throw(auxiliary_rand, sizeof(auxiliary_rand)) == FAILURE) {
		zend_string_efree(binary_seckey);
		zend_string_efree(binary_hash);
		secp256k1_context_destroy(ctx);
		zend_throw_exception_ex(spl_ce_RuntimeException, 0, "secp256k1_nostr_sign(): Error fetching entropy for Schnorr signature");
		return;
	}

	if (!secp256k1_schnorrsig_sign32(ctx, signature, (unsigned char *)ZSTR_VAL(binary_hash), &keypair, auxiliary_rand)) {
		zend_string_efree(binary_seckey);
		zend_string_efree(binary_hash);
		secp256k1_context_destroy(ctx);
		zend_throw_exception_ex(spl_ce_RuntimeException, 0, "secp256k1_nostr_sign(): Unexpected error generating signature. Please open an issue at https://github.com/1ma/secp256k1-nostr-php-ext/issues");
		return;
	}

	zend_string_efree(binary_seckey);
	zend_string_efree(binary_hash);
	secp256k1_context_destroy(ctx);

	RETURN_STR(php_bin2hex(signature, sizeof(signature)));
}

PHP_FUNCTION(secp256k1_nostr_verify)
{
	zend_string* in_pubkey;
	zend_string* in_hash;
	zend_string* in_signature;

	zend_string* binary_pubkey;
	zend_string* binary_hash;
	zend_string* binary_signature;

	secp256k1_xonly_pubkey xonly_pubkey;
	int result;

	ZEND_PARSE_PARAMETERS_START(3, 3)
		Z_PARAM_STR(in_pubkey)
		Z_PARAM_STR(in_hash)
		Z_PARAM_STR(in_signature)
	ZEND_PARSE_PARAMETERS_END();

	if (ZSTR_LEN(in_pubkey) != 64) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_verify(): Parameter 1 is not a hex-encoded 32 byte string");
		return;
	}

	binary_pubkey = php_hex2bin((unsigned char *)ZSTR_VAL(in_pubkey), ZSTR_LEN(in_pubkey));
	if (binary_pubkey == NULL) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_verify(): Parameter 1 is not a hex-encoded 32 byte string");
		return;
	}

	if (ZSTR_LEN(in_hash) != 64) {
		zend_string_efree(binary_pubkey);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_verify(): Parameter 2 is not a hex-encoded 32 byte string");
		return;
	}

	binary_hash = php_hex2bin((unsigned char *)ZSTR_VAL(in_hash), ZSTR_LEN(in_hash));
	if (binary_hash == NULL) {
		zend_string_efree(binary_pubkey);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_verify(): Parameter 2 is not a hex-encoded 32 byte string");
		return;
	}

	if (ZSTR_LEN(in_signature) != 128) {
		zend_string_efree(binary_pubkey);
		zend_string_efree(binary_hash);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_verify(): Parameter 3 is not a hex-encoded 64 byte string");
		return;
	}

	binary_signature = php_hex2bin((unsigned char *)ZSTR_VAL(in_signature), ZSTR_LEN(in_signature));
	if (binary_signature == NULL) {
		zend_string_efree(binary_pubkey);
		zend_string_efree(binary_hash);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_verify(): Parameter 3 is not a hex-encoded 64 byte string");
		return;
	}

	if (!secp256k1_xonly_pubkey_parse(secp256k1_context_static, &xonly_pubkey, (unsigned char *)ZSTR_VAL(binary_pubkey))) {
		zend_string_efree(binary_pubkey);
		zend_string_efree(binary_hash);
		zend_string_efree(binary_signature);
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "secp256k1_nostr_verify(): Parameter 1 is not a valid public key");
		return;
	}

	result = secp256k1_schnorrsig_verify(secp256k1_context_static, (unsigned char *)ZSTR_VAL(binary_signature), (unsigned char *)ZSTR_VAL(binary_hash), ZSTR_LEN(binary_hash), &xonly_pubkey);

	zend_string_efree(binary_pubkey);
	zend_string_efree(binary_hash);
	zend_string_efree(binary_signature);

	if (result) {
		RETURN_TRUE;
	}

	RETURN_FALSE;
}

/* {{{ PHP_MINIT_FUNCTION */
PHP_MINIT_FUNCTION(secp256k1_nostr)
{
#if defined(ZTS) && defined(COMPILE_DL_SECP256K1_NOSTR)
	ZEND_TSRMLS_CACHE_UPDATE();
#endif

	secp256k1_selftest();

	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION */
PHP_MINFO_FUNCTION(secp256k1_nostr)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "secp256k1_nostr support", "enabled");
	php_info_print_table_end();
}
/* }}} */

/* {{{ secp256k1_nostr_module_entry */
zend_module_entry secp256k1_nostr_module_entry = {
	STANDARD_MODULE_HEADER,
	"secp256k1_nostr",					/* Extension name */
	ext_functions,					/* zend_function_entry */
	PHP_MINIT(secp256k1_nostr),			/* PHP_MINIT - Module initialization */
	NULL,							/* PHP_MSHUTDOWN - Module shutdown */
	NULL,			/* PHP_RINIT - Request initialization */
	NULL,							/* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(secp256k1_nostr),			/* PHP_MINFO - Module info */
	PHP_SECP256K1_NOSTR_VERSION,		/* Version */
	STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_SECP256K1_NOSTR
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(secp256k1_nostr)
#endif
