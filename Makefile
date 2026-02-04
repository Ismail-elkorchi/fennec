PHP ?= php
COMPOSER ?= composer

.PHONY: lint test stan

lint:
	$(PHP) -l public/index.php

test:
	@if [ ! -d vendor ]; then $(COMPOSER) install; fi
	./vendor/bin/phpunit

stan:
	@if [ ! -d vendor ]; then $(COMPOSER) install; fi
	./vendor/bin/phpstan analyse -c phpstan.neon
