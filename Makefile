.PHONY: up down logs sync test shell fresh

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f

sync:
	docker compose exec app php artisan sheets:sync --now

test:
	docker compose exec app composer test

shell:
	docker compose exec app sh

fresh:
	docker compose exec app php artisan migrate:fresh --seed --force