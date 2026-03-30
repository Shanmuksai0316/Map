PG_ENV=DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=map_hms_test DB_USERNAME=map DB_PASSWORD=map

.PHONY: up down migrate test testrun

up:
	docker compose up -d postgres

down:
	docker compose down

migrate:
	$(PG_ENV) php artisan migrate:fresh --force

test:
	$(PG_ENV) php artisan test --filter=AdminPanelAccessTest && \
	$(PG_ENV) php artisan test --filter=TenantPanelAccessTest && \
	$(PG_ENV) php artisan test --filter=TicketPolicyTenantDbTest

testrun: up migrate test



























