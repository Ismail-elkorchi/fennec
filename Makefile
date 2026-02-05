COMPOSE ?= docker compose
PHP_SERVICE ?= php85
UID := $(shell id -u)
GID := $(shell id -g)
DCRUN_PHP85 := $(COMPOSE) run --rm --user $(UID):$(GID) -e HOME=/tmp -e COMPOSER_CACHE_DIR=/tmp/composer-cache php85
DCRUN_PHP84 := $(COMPOSE) run --rm --user $(UID):$(GID) -e HOME=/tmp -e COMPOSER_CACHE_DIR=/tmp/composer-cache php84
DCRUN_GO := $(COMPOSE) run --rm --user $(UID):$(GID) -e HOME=/tmp

.PHONY: lint lint-md lint-openapi lint-php test stan test-php84

lint: lint-md lint-openapi lint-php

lint-md:
	./tools/dev/lint-md.sh

lint-openapi:
	./tools/dev/lint-openapi.sh

lint-php:
	$(DCRUN_PHP85) php -l public/index.php

test:
	$(DCRUN_PHP85) composer install --no-interaction --prefer-dist
	$(DCRUN_PHP85) vendor/bin/phpunit --colors=always
	$(DCRUN_PHP85) vendor/bin/phpstan analyse -c phpstan.neon
	$(DCRUN_GO) --workdir /app/agent go go test ./...

stan:
	$(DCRUN_PHP85) composer install --no-interaction --prefer-dist
	$(DCRUN_PHP85) vendor/bin/phpstan analyse -c phpstan.neon

test-php84:
	$(DCRUN_PHP84) composer install --no-interaction --prefer-dist
	$(DCRUN_PHP84) vendor/bin/phpunit --colors=always
	$(DCRUN_PHP84) vendor/bin/phpstan analyse -c phpstan.neon
