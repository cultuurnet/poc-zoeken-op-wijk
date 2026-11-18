PORT ?= 8088
REF ?= master

export PORT

PHP = docker compose run --rm --no-deps app php

.PHONY: help start down logs geoshapes lookup lint

help: ## Toon deze hulp
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  make %-10s %s\n", $$1, $$2}'

start: data/wijken.json ## Start de POC in Docker (PORT=8088)
	docker compose up -d
	@echo "POC draait op http://127.0.0.1:$(PORT)"

down: ## Stop de Docker-container
	docker compose down

logs: ## Volg de logs van de webserver
	docker compose logs -f app

geoshapes: ## Haal de wijk- en deelgemeentegrenzen (opnieuw) op (REF=master|<sha>)
	$(PHP) bin/fetch-geoshapes.php --ref=$(REF)

data/wijken.json:
	$(PHP) bin/fetch-geoshapes.php --ref=$(REF)

lookup: data/wijken.json ## Zoek de wijk van een event of place: make lookup EVENT=<url|uuid>
	@test -n "$(EVENT)" || (echo "Gebruik: make lookup EVENT=<url|uuid>" && exit 1)
	$(PHP) bin/lookup.php "$(EVENT)"

lint: ## Controleer de PHP-syntax
	@$(PHP) -r 'foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(".", FilesystemIterator::SKIP_DOTS)) as $$f) { if ($$f->getExtension() === "php") { passthru("php -l " . escapeshellarg((string) $$f) . " | grep -v \"^No syntax errors\""); } }'
