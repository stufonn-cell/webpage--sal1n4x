.PHONY: up down install fresh logs shell db reset build test test-unit test-feature test-front lint prod

up: build
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose run --rm --no-deps frontend sh -c "npm install --no-audit --no-fund && npm run build"

install: build
	docker compose up -d
	docker compose exec app php bin/console install

fresh:
	docker compose exec app php bin/console fresh

logs:
	docker compose logs -f app web frontend

shell:
	docker compose exec app sh

db:
	docker compose exec mysql mysql -u root -p$${DB_ROOT_PASSWORD} $${DB_DATABASE}

test:
	docker compose exec app php bin/test

test-unit:
	docker compose exec app php bin/test unit

test-feature:
	docker compose exec app php bin/test feature

test-front:
	docker compose run --rm --no-deps frontend sh -c "npm install --no-audit --no-fund && npm run typecheck && npm test"

lint:
	docker compose exec app sh -c "find src public bin tests -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null && echo 'Sintaxis correcta'"

prod:
	docker compose -f docker-compose.prod.yml up -d --build

reset: down
	docker volume rm psiclinic_mysql_data || true
	$(MAKE) up
