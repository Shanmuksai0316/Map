# 📋 MAP-HMS Operations Runbook

Operational procedures for managing MAP-HMS in production.

---

## 🚀 Deployment

### Deploy to Production

```bash
# 1. SSH to server
ssh -i hostinger.pem root@72.62.79.173

# 2. Pull latest code
cd /opt/map-hms/app
git pull origin main

# 3. Install dependencies (if changed)
docker exec map-hms-app composer install --no-dev --optimize-autoloader

# 4. Run migrations
docker exec map-hms-app php artisan migrate --force

# 5. Clear caches
docker exec map-hms-app php artisan config:clear
docker exec map-hms-app php artisan route:clear
docker exec map-hms-app php artisan view:clear
docker exec map-hms-app php artisan cache:clear

# 6. Restart application
docker restart map-hms-app

# 7. Verify
curl https://api.mapservices.in/healthz
```

### Deploy to Staging

```bash
ssh -i hostinger.pem root@72.62.79.173

cd /opt/map-hms-staging/app
git pull origin staging
docker exec map-hms-staging composer install --no-dev
docker exec map-hms-staging php artisan migrate --force
docker exec map-hms-staging php artisan config:clear
docker restart map-hms-staging

curl https://api.staging.mapservices.in/healthz
```

### Rollback Deployment

```bash
ssh -i hostinger.pem root@72.62.79.173
cd /opt/map-hms/app

# Find previous commit
git log --oneline -10

# Rollback to specific commit
git checkout <commit-hash>

# Restart
docker restart map-hms-app
```

---

## 🔍 Monitoring

### Health Checks

```bash
# Production
curl -s https://api.mapservices.in/healthz | jq

# Staging
curl -s https://api.staging.mapservices.in/healthz | jq

# Expected response:
# {"ok":true,"checks":{"db":"ok","cache":"ok","queue":"ok"}}
```

### Container Status

```bash
ssh -i hostinger.pem root@72.62.79.173

# All containers
docker ps

# Specific app
docker ps --filter name=map-hms-app

# Resource usage
docker stats --no-stream
```

### Application Logs

```bash
# Real-time logs
docker logs -f map-hms-app

# Last 100 lines
docker logs --tail 100 map-hms-app

# Laravel logs
docker exec map-hms-app tail -100 storage/logs/laravel.log

# Search for errors
docker exec map-hms-app grep -i error storage/logs/laravel.log | tail -20
```

### Database Status

```bash
# Check connections
docker exec map-hms-db psql -U maphms -c "SELECT count(*) FROM pg_stat_activity;"

# Database size
docker exec map-hms-db psql -U maphms -c "SELECT pg_size_pretty(pg_database_size('maphms'));"

# Slow queries (if enabled)
docker exec map-hms-db psql -U maphms -c "SELECT * FROM pg_stat_statements ORDER BY total_time DESC LIMIT 10;"
```

### Queue Status

```bash
# Check Horizon (if running)
docker exec map-hms-app php artisan horizon:status

# Queue length
docker exec map-hms-app php artisan queue:work --once

# Failed jobs
docker exec map-hms-app php artisan queue:failed
```

---

## 🔧 Common Operations

### Clear All Caches

```bash
docker exec map-hms-app php artisan cache:clear
docker exec map-hms-app php artisan config:clear
docker exec map-hms-app php artisan route:clear
docker exec map-hms-app php artisan view:clear
docker exec map-hms-app php artisan event:clear

# Redis flush (caution!)
docker exec map-hms-redis redis-cli FLUSHALL
```

### Restart Services

```bash
# Application only
docker restart map-hms-app

# All services
docker restart map-hms-app map-hms-staging map-hms-db map-hms-redis

# Traefik (proxy)
docker restart coolify-proxy
```

### Database Operations

```bash
# Backup database
docker exec map-hms-db pg_dump -U maphms maphms > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore database
cat backup_file.sql | docker exec -i map-hms-db psql -U maphms -d maphms

# Run specific migration
docker exec map-hms-app php artisan migrate --path=database/migrations/2025_01_01_000000_create_xxx_table.php

# Rollback last migration
docker exec map-hms-app php artisan migrate:rollback --step=1
```

### User Management

```bash
# Create admin user
docker exec map-hms-app php artisan tinker --execute="
\$user = App\Models\User::create([
    'name' => 'Admin Name',
    'email' => 'admin@example.com',
    'phone' => '9999999999',
    'password' => bcrypt('password123')
]);
\$user->assignRole('Super Admin');
"

# Reset user password
docker exec map-hms-app php artisan tinker --execute="
\$user = App\Models\User::where('phone', '9999999999')->first();
\$user->password = bcrypt('newpassword');
\$user->save();
"
```

---

## 🚨 Incident Response

### Site Down

1. **Check health endpoint:**
   ```bash
   curl -v https://api.mapservices.in/healthz
   ```

2. **Check container status:**
   ```bash
   ssh -i hostinger.pem root@72.62.79.173 "docker ps"
   ```

3. **Check logs for errors:**
   ```bash
   docker logs --tail 50 map-hms-app
   ```

4. **Restart if needed:**
   ```bash
   docker restart map-hms-app
   ```

5. **Check Traefik proxy:**
   ```bash
   docker logs --tail 50 coolify-proxy
   ```

### Database Connection Issues

1. **Check PostgreSQL status:**
   ```bash
   docker exec map-hms-db pg_isready
   ```

2. **Check connections:**
   ```bash
   docker exec map-hms-db psql -U maphms -c "SELECT count(*) FROM pg_stat_activity;"
   ```

3. **Restart database:**
   ```bash
   docker restart map-hms-db
   ```

### High Memory Usage

1. **Check memory:**
   ```bash
   free -h
   docker stats --no-stream
   ```

2. **Restart heavy containers:**
   ```bash
   docker restart map-hms-app
   ```

3. **Clear caches:**
   ```bash
   docker exec map-hms-app php artisan cache:clear
   ```

### SSL Certificate Issues

1. **Check certificate:**
   ```bash
   openssl s_client -connect api.mapservices.in:443 -servername api.mapservices.in 2>/dev/null | openssl x509 -noout -dates
   ```

2. **For Let's Encrypt (staging):**
   ```bash
   docker exec coolify-proxy cat /traefik/acme.json | jq '.letsencrypt.Certificates'
   ```

3. **Force renewal:**
   ```bash
   docker restart coolify-proxy
   ```

---

## 📊 Performance Tuning

### PHP-FPM Settings

```bash
# Check current settings
docker exec map-hms-app cat /usr/local/etc/php-fpm.d/www.conf | grep -E "pm\."

# Recommended for 32GB RAM:
# pm = dynamic
# pm.max_children = 50
# pm.start_servers = 10
# pm.min_spare_servers = 5
# pm.max_spare_servers = 20
```

### PostgreSQL Tuning

```bash
# Check current settings
docker exec map-hms-db psql -U maphms -c "SHOW shared_buffers;"
docker exec map-hms-db psql -U maphms -c "SHOW work_mem;"

# Recommended for 32GB RAM server:
# shared_buffers = 8GB
# work_mem = 256MB
# maintenance_work_mem = 1GB
```

### Redis Memory

```bash
# Check memory usage
docker exec map-hms-redis redis-cli INFO memory | grep used_memory_human
```

---

## 📅 Scheduled Tasks

### Daily

- [ ] Check health endpoints
- [ ] Review error logs
- [ ] Check disk space

### Weekly

- [ ] Database backup verification
- [ ] Review failed jobs
- [ ] Check SSL certificate expiry

### Monthly

- [ ] Security updates
- [ ] Performance review
- [ ] Storage cleanup

---

## 📞 Contacts

| Role | Contact |
|------|---------|
| Server Admin | root@72.62.79.173 |
| Coolify Dashboard | http://72.62.79.173:8000 |

---

*Last Updated: January 2026*

