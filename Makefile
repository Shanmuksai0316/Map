.PHONY: setup up down migrate seed fmt lint test api mobile review demo print-creds observability horizon

setup: ## First-time setup
	cp api/.env.example api/.env || true
	cp mobile/.env.example mobile/.env || true
	docker compose up -d
	cd api && composer install && php artisan key:generate && php artisan migrate --seed
	cd mobile && npm install

up: ## Start local services
	docker compose up -d

down: ## Stop and remove local services
	docker compose down -v

migrate:
	cd api && php artisan migrate

seed:
	cd api && php artisan db:seed

fmt:
	cd api && ./vendor/bin/pint
	cd mobile && npm run format

lint:
	cd api && ./vendor/bin/phpstan analyse --memory-limit=1G || true
	cd mobile && npm run lint

test:
	cd api && php artisan test
	cd mobile && npm test

api:
	cd api && php artisan serve --host=0.0.0.0 --port=8000

mobile:
	cd mobile && npm run start

review: ## Run comprehensive code review
	cd api && php artisan hms:review:status --format=md
	@echo "\n📊 Running tests..."
	cd api && vendor/bin/pest --parallel || true
	@echo "\n✅ Review complete. See docs/review/"

demo: ## Reset demo data and generate reports
	cd api && php artisan hms:demo:reset --fresh --force
	@echo "\n📊 Generating readiness reports..."
	cd api && php artisan hms:review:status --format=md
	@echo "\n✅ Demo ready!"

print-creds: ## Print demo login credentials
	cd api && php artisan hms:demo:print-creds

uat-refresh: ## Reset demo data for UAT (no confirmation prompts)
	cd api && php artisan hms:demo:reset --fresh --force
	@echo "\n🔑 Demo credentials:"
	cd api && php artisan hms:demo:print-creds

uat-health: ## Check system health status
	@echo "🔍 Checking system health..."
	@curl -s -o /dev/null -w "Health endpoint: %{http_code}\n" http://localhost:8001/healthz || echo "❌ Server not running on port 8001"

uat-open: ## Open admin and campus-manager panels in browser
	@echo "🌐 Opening UAT panels..."
	@if command -v open >/dev/null 2>&1; then \
		open http://localhost:8001/admin http://localhost:8001/campus-manager; \
	elif command -v xdg-open >/dev/null 2>&1; then \
		xdg-open http://localhost:8001/admin http://localhost:8001/campus-manager; \
	else \
		echo "Please open manually: http://localhost:8001/admin and http://localhost:8001/campus-manager"; \
	fi

observability: ## Run observability tests and optimize
	@echo "🔍 Running Observability tests..."
	cd api && vendor/bin/pest --filter=Observability || true
	@echo "⚡ Optimizing application..."
	cd api && php artisan optimize
	@echo "🏥 Testing health endpoint..."
	@curl -s http://localhost:8000/healthz | jq . || echo "Health endpoint test failed"
	@echo "✅ Observability check complete!"

horizon: ## Install and start Horizon
	@echo "📊 Setting up Horizon..."
	cd api && php artisan horizon:install || true
	@echo "🚀 Starting Horizon..."
	cd api && php artisan horizon &
	@echo "✅ Horizon started! Access at http://localhost:8000/horizon"
