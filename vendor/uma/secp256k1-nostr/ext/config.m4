PHP_ARG_ENABLE([secp256k1_nostr],
  [whether to enable secp256k1_nostr support],
  [AS_HELP_STRING([--enable-secp256k1_nostr],
    [Enable secp256k1_nostr support])],
  [no])

if test "$PHP_SECP256K1_NOSTR" != "no"; then
  PKG_CHECK_MODULES([LIBSODIUM], [libsodium >= 1.0.8])
  PHP_EVAL_INCLINE([$LIBSODIUM_CFLAGS])
  PHP_EVAL_LIBLINE([$LIBSODIUM_LIBS], [SECP256K1_NOSTR_SHARED_LIBADD])

  PKG_CHECK_MODULES([LIBSECP256K1], [libsecp256k1 >= 0.2.0])
  PHP_EVAL_INCLINE([$LIBSECP256K1_CFLAGS])
  PHP_EVAL_LIBLINE([$LIBSECP256K1_LIBS], [SECP256K1_NOSTR_SHARED_LIBADD])

  PHP_SUBST([SECP256K1_NOSTR_SHARED_LIBADD])

  AC_DEFINE(HAVE_SECP256K1_NOSTR, 1, [ Have secp256k1_nostr support ])

  PHP_NEW_EXTENSION(secp256k1_nostr, hex_conversions.c secp256k1_nostr.c, $ext_shared)

  PHP_ADD_EXTENSION_DEP(secp256k1_nostr, spl)
  PHP_ADD_EXTENSION_DEP(secp256k1_nostr, standard)
fi
