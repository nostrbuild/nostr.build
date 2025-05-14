.PHONY: ext libsodium secp256k1 check check-valgrind install clean

ext:
	cd ext && \
	phpize && \
	./configure
	make -C ext

ext-with-deps: libsodium secp256k1
	cd ext && \
	phpize && \
	./configure PKG_CONFIG_PATH=$(shell pwd)/build/lib/pkgconfig
	make -C ext

libsodium:
	cd vendor/libsodium && \
	./autogen.sh && \
	./configure \
		--prefix=$(shell pwd)/build \
		--with-pic
	make -C vendor/libsodium -j$(shell nproc)
	make -C vendor/libsodium install

secp256k1:
	cd vendor/secp256k1 && \
	./autogen.sh && \
	./configure \
		--disable-benchmark \
		--disable-ctime-tests \
		--disable-examples \
		--disable-exhaustive-tests \
		--disable-shared \
		--disable-tests \
		--prefix=$(shell pwd)/build \
		--with-pic
	make -C vendor/secp256k1 -j$(shell nproc)
	make -C vendor/secp256k1 install

check:
	make -C ext test TESTS="-q --show-diff"

check-valgrind:
	make -C ext test TESTS="-q -m --show-diff --show-mem"

install:
	make -C ext install

clean:
	make -C ext clean
	make -C vendor/libsodium clean
	make -C vendor/secp256k1 clean
	rm -rf build
