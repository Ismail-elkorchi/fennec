COMPOSE ?= docker compose
PHP_SERVICE ?= php85
UID := $(shell id -u)
GID := $(shell id -g)
DB_NAME ?= fennec
DB_USER ?= fennec
DB_PASSWORD ?= fennec-dev
DB_DSN ?= pgsql:host=db;port=5432;dbname=$(DB_NAME)
DB_ENV := -e FENNEC_DB_DSN="$(DB_DSN)" -e FENNEC_DB_USER="$(DB_USER)" -e FENNEC_DB_PASSWORD="$(DB_PASSWORD)"
DCRUN_PHP85 := $(COMPOSE) run --rm --user $(UID):$(GID) -e HOME=/tmp -e COMPOSER_CACHE_DIR=/tmp/composer-cache php85
DCRUN_PHP84 := $(COMPOSE) run --rm --user $(UID):$(GID) -e HOME=/tmp -e COMPOSER_CACHE_DIR=/tmp/composer-cache php84
DCRUN_GO := $(COMPOSE) run --rm --user $(UID):$(GID) -e HOME=/tmp
DCRUN_PHP85_DB := $(COMPOSE) run --rm --user $(UID):$(GID) -e HOME=/tmp -e COMPOSER_CACHE_DIR=/tmp/composer-cache $(DB_ENV) php85

.PHONY: lint lint-md lint-openapi lint-php test stan test-php84 db-up db-wait db-down db-reset migrate test-db create-agent enqueue-noop requeue-expired dev-up dev-down agent-run-once

lint: lint-md lint-openapi lint-php

lint-md:
	./tools/dev/lint-md.sh

lint-openapi:
	./tools/dev/lint-openapi.sh

lint-php:
	$(DCRUN_PHP85) php -l public/index.php

test:
	$(DCRUN_PHP85) composer install --no-interaction --prefer-dist
	$(DCRUN_PHP85) vendor/bin/phpunit --colors=always --exclude-group db
	$(DCRUN_PHP85) vendor/bin/phpstan analyse -c phpstan.neon
	$(DCRUN_GO) --workdir /app/agent go go test ./...

stan:
	$(DCRUN_PHP85) composer install --no-interaction --prefer-dist
	$(DCRUN_PHP85) vendor/bin/phpstan analyse -c phpstan.neon

test-php84:
	$(DCRUN_PHP84) composer install --no-interaction --prefer-dist
	$(DCRUN_PHP84) vendor/bin/phpunit --colors=always --exclude-group db
	$(DCRUN_PHP84) vendor/bin/phpstan analyse -c phpstan.neon

db-up:
	COMPOSE_PROFILES=db $(COMPOSE) up -d db

db-wait:
	@echo "Waiting for database to become ready..."
	@for i in $$(seq 1 30); do \
		if COMPOSE_PROFILES=db $(COMPOSE) exec -T db pg_isready -U $(DB_USER) -d $(DB_NAME) >/dev/null 2>&1; then \
			echo "Database is ready."; \
			exit 0; \
		fi; \
		sleep 1; \
	done; \
	echo "Database did not become ready in time."; \
	exit 1

db-down:
	COMPOSE_PROFILES=db $(COMPOSE) down

db-reset:
	COMPOSE_PROFILES=db $(COMPOSE) down -v

dev-up:
	UID=$(UID) GID=$(GID) COMPOSE_PROFILES=dev,db $(COMPOSE) up -d db controller

dev-down:
	UID=$(UID) GID=$(GID) COMPOSE_PROFILES=dev,db $(COMPOSE) down

migrate:
	COMPOSE_PROFILES=db $(DCRUN_PHP85_DB) php bin/fennec migrate

create-agent:
	@if [ -z "$$AGENT_NAME" ]; then echo "AGENT_NAME is required"; exit 1; fi
	COMPOSE_PROFILES=db $(DCRUN_PHP85_DB) php bin/fennec create-agent --name="$$AGENT_NAME"

enqueue-noop:
	COMPOSE_PROFILES=db $(DCRUN_PHP85_DB) php bin/fennec enqueue-noop

requeue-expired:
	COMPOSE_PROFILES=db $(DCRUN_PHP85_DB) php bin/fennec requeue-expired

agent-run-once:
	@if [ -z "$$FENNEC_AGENT_TOKEN" ]; then echo "FENNEC_AGENT_TOKEN is required"; exit 1; fi
	@if [ -z "$$FENNEC_CONTROLLER_URL" ]; then echo "FENNEC_CONTROLLER_URL is required"; exit 1; fi
	$(DCRUN_GO) --workdir /app/agent -e FENNEC_AGENT_TOKEN -e FENNEC_CONTROLLER_URL -e FENNEC_HEARTBEAT_INTERVAL_SECONDS go go run . run-once

test-db: db-up db-wait migrate
	COMPOSE_PROFILES=db $(DCRUN_PHP85_DB) vendor/bin/phpunit --colors=always --group db
	COMPOSE_PROFILES=db $(COMPOSE) down
