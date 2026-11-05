# VulnMart developer tasks
#
# `make help` lists everything. Requires Docker for the container targets and
# PHP + curl for the local ones.

SHELL := /bin/bash
APP_URL ?= http://localhost:8080
PHP ?= php

.DEFAULT_GOAL := help
.PHONY: help up down build logs shell reset serve lint test clean

help: ## Show this help
	@echo "VulnMart"
	@echo
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-8s\033[0m %s\n", $$1, $$2}'
	@echo
	@echo "App URL: $(APP_URL)"

up: ## Build and start the stack in the background
	docker compose up --build -d
	@echo "Waiting for the app…"
	@for i in $$(seq 1 30); do \
		curl -s -o /dev/null "$(APP_URL)/" && break; \
		sleep 1; \
	done
	@echo "VulnMart is up on $(APP_URL) (open $(APP_URL)/init_db.php to reseed)"

down: ## Stop the stack
	docker compose down

build: ## Rebuild the image without starting it
	docker compose build

logs: ## Follow the container logs
	docker compose logs -f web

shell: ## Open a shell in the web container
	docker compose exec web bash

reset: ## Reset and reseed the database of the running stack
	curl -s -X POST -d "action=reset" "$(APP_URL)/init_db.php" > /dev/null
	@echo "Database reset."

serve: ## Run the app locally with the PHP built-in server
	$(PHP) -S 127.0.0.1:8080 -t src

lint: ## Syntax check every PHP file
	@find src tools -name '*.php' -print0 | xargs -0 -n1 $(PHP) -l > /dev/null \
		&& echo "PHP syntax OK"

test: ## Run the smoke test (starts its own server on :8099)
	bash tools/smoke_test.sh

clean: ## Remove the database, the cache and seller uploads
	rm -f data/*.db data/*.db-journal data/*.db-wal data/*.db-shm
	rm -rf data/cache
	find src/uploads -type f ! -name '.gitkeep' -delete
	@echo "Local state cleaned."
