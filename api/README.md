# MAP HMS API

Backend API for the MAP Hostel Management System, built with Laravel and Filament.

## Stack

- **PHP 8.3** with Laravel framework
- **PostgreSQL 16** database
- **Redis 7** for caching and queues
- **Filament** admin panels (multi-tenant)
- **Stancl/Tenancy** for domain-based multi-tenancy

## Multi-Tenancy

Each tenant is identified by a subdomain: `{tenant-code}.mapservices.in`

- Central domain: `api.mapservices.in`
- Tenant routes: `routes/tenant.php` (resolved via `InitializeTenancyByDomain`)
- Mobile API routes: `routes/api.php` (authenticated via Sanctum)

## Mobile API

Base URL: `https://{tenant-code}.mapservices.in/api/v1`

Authentication uses Laravel Sanctum with Bearer tokens. Two mobile apps consume this API:
- **Vidyarthi** (Student app)
- **Karta** (Staff app)

## Development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Production

Deployed via Docker on Hostinger VPS. The `api/` directory is mounted into the container at `/var/www/html`.

```bash
docker exec map-hms-app sh -c 'php artisan config:cache && php artisan route:cache && php artisan view:cache'
```
