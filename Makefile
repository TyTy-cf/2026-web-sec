DOCKER_COMPOSE ?= docker compose
DOCKER_USER ?= "$(shell id -u):$(shell id -g)"
ENV ?= "dev"
PREFIX ?= "fakeddit"
DB_NAME="fakeddit"

init:
	@cp .env .env.local
	@$(MAKE) up-build
	@echo "Waiting for the database to be ready..."
	@sleep 5
	@echo "Installing PHP dependencies..."
	@docker compose exec -T php composer install --no-scripts
	@$(MAKE) db
	@docker compose exec -T php rm -rf var/cache

db-reset:
	@echo "DELETE DB..."
	@docker compose exec -T mariadb mysql -uroot -proot -e "DROP database IF EXISTS fakeddit;"

	@echo "CREATE DB..."
	@docker compose exec -T php php bin/console doctrine:database:create
	@docker compose exec -T php php bin/console d:m:m -n

	@echo "Importing initial database structure and data..."
	@docker compose exec -T mariadb mysql -uroot -proot $(DB_NAME) < ./docker/fakeddit.sql
	@echo "Database import completed."

db:
	@echo "DELETE DB..."
	@docker compose exec -T mariadb mysql -uroot -proot -e "DROP database IF EXISTS fakeddit;"

	@echo "CREATE DB..."
	@docker compose exec -T php php bin/console doctrine:database:create
	@docker compose exec -T php php bin/console d:m:m -n

	@echo "Importing initial database structure and data..."
	@docker compose exec -T mariadb mysql -uroot -proot $(DB_NAME) < ./docker/fakeddit.sql
	@echo "Database import completed."

up:
	@docker compose up -d

up-build:
	@docker compose up -d --build

up-build-linux:
	@docker compose down
	@docker compose build --no-cache --build-arg USER_ID=$$(id -u) --build-arg GROUP_ID=$$(id -g) && docker compose up -d

down:
	@docker compose down

php:
	@docker compose exec php bash

node:
	@docker compose exec node bash

l-node:
	@docker compose logs node -f
