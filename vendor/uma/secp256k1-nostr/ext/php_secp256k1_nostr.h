/* secp256k1_nostr extension for PHP */

#ifndef PHP_SECP256K1_NOSTR_H
# define PHP_SECP256K1_NOSTR_H

extern zend_module_entry secp256k1_nostr_module_entry;
# define phpext_secp256k1_nostr_ptr &secp256k1_nostr_module_entry

# define PHP_SECP256K1_NOSTR_VERSION "0.1.0"

# if defined(ZTS) && defined(COMPILE_DL_SECP256K1_NOSTR)
ZEND_TSRMLS_CACHE_EXTERN()
# endif

#endif	/* PHP_SECP256K1_NOSTR_H */
