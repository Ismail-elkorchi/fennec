COMPOSE ?= docker compose
PHP_SERVICE ?= php85

.PHONY: lint test stan test-php84

lint:
	$(COMPOSE) run --rm $(PHP_SERVICE) php -l public/index.php

test:
	$(COMPOSE) run --rm $(PHP_SERVICE) composer install --no-interaction --prefer-dist
	$(COMPOSE) run --rm $(PHP_SERVICE) vendor/bin/phpunit --colors=always
	$(COMPOSE) run --rm $(PHP_SERVICE) vendor/bin/phpstan analyse -c phpstan.neon
	$(COMPOSE) run --rm --workdir /app/agent go go test ./...

stan:
	$(COMPOSE) run --rm $(PHP_SERVICE) composer install --no-interaction --prefer-dist
	$(COMPOSE) run --rm $(PHP_SERVICE) vendor/bin/phpstan analyse -c phpstan.neon

test-php84:
	$(COMPOSE) run --rm php84 composer install --no-interaction --prefer-dist
	$(COMPOSE) run --rm php84 vendor/bin/phpunit --colors=always
	$(COMPOSE) run --rm php84 vendor/bin/phpstan analyse -c phpstan.neon
