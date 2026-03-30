# 🏗️ MAP-HMS Infrastructure Guide

**Last Updated:** January 2026  
**Infrastructure:** Hostinger VPS with Coolify

---

## 📊 Architecture Overview

```
                         ┌─────────────────┐
                         │   Cloudflare    │
                         │   (DNS + SSL)   │
                         └────────┬────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    HOSTINGER VPS (72.62.79.173)                     │
│                    8 vCPU | 32GB RAM | 400GB NVMe                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐           │
│   │  Coolify    │    │   Traefik   │    │  Coolify    │           │
│   │  (Mgmt UI)  │    │  (Proxy)    │    │  Services   │           │
│   │  :8000      │    │  :80/:443   │    │             │           │
│   └─────────────┘    └──────┬──────┘    └─────────────┘           │
│                             │                                       │
│         ┌───────────────────┼───────────────────┐                  │
│         │                   │                   │                  │
│         ▼                   ▼                   ▼                  │
│   ┌───────────┐      ┌───────────┐      ┌───────────┐             │
│   │Production │      │  Staging  │      │ PostgreSQL│             │
│   │map-hms-app│      │map-hms-   │      │map-hms-db │             │
│   │  :8081    │      │staging    │      │  :5432    │             │
│   │           │      │  :8082    │      │           │             │
│   └───────────┘      └───────────┘      └───────────┘             │
│         │                   │                  │                   │
│         └───────────────────┼──────────────────┘                   │
│                             │                                       │
│                      ┌──────┴──────┐                               │
│                      │    Redis    │                               │
│                      │map-hms-redis│                               │
│                      │   :6379     │                               │
│                      └─────────────┘                               │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🌐 Environment URLs

| Environment | Domain | Purpose | Branch |
|-------------|--------|---------|--------|
| **Production** | `*.mapservices.in` | Live system | `main` |
| **Staging** | `*.staging.mapservices.in` | QA/Testing | `staging` |
| **Local** | `*.localhost:8000` | Development | `feature/*` |

### Key URLs

| Service | Production | Staging |
|---------|------------|---------|
| Admin Panel | https://admin.mapservices.in | https://admin.staging.mapservices.in |
| API | https://api.mapservices.in | https://api.staging.mapservices.in |
| Health Check | https://api.mapservices.in/healthz | https://api.staging.mapservices.in/healthz |
| Tenant Example | https://map-demo-college.mapservices.in | https://map-demo-college.staging.mapservices.in |

---

## 🔧 Server Specifications

| Resource | Specification |
|----------|---------------|
| **Provider** | Hostinger VPS |
| **IP Address** | 72.62.79.173 |
| **OS** | Ubuntu 24.04 LTS |
| **CPU** | 8 vCPU |
| **RAM** | 32 GB |
| **Storage** | 400 GB NVMe |
| **Bandwidth** | 32 TB/month |

---

## 📦 Running Containers

| Container | Image | Purpose | Port |
|-----------|-------|---------|------|
| `map-hms-app` | `map-hms-app:latest` | Production Laravel app | 8081 |
| `map-hms-staging` | `map-hms-app:latest` | Staging Laravel app | 8082 |
| `map-hms-db` | `postgres:16-alpine` | PostgreSQL database | 5432 |
| `map-hms-redis` | `redis:7-alpine` | Cache & Queue | 6379 |
| `coolify-proxy` | `traefik:v3.6` | Reverse proxy | 80, 443 |
| `coolify` | `coolify:4.x` | Management UI | 8000 |

---

## 🗄️ Database Architecture

### Production Database
- **Host:** `map-hms-db` (internal)
- **Database:** `maphms`
- **User:** `maphms`
- **Tables:** 91

### Staging Database
- **Host:** `map-hms-db` (internal)
- **Database:** `maphms_staging`
- **User:** `maphms_staging`
- **Tables:** 91

### Redis Configuration
- **Host:** `map-hms-redis` (internal)
- **Port:** 6379
- **Production Prefix:** (none)
- **Staging Prefix:** `staging_`

---

## 🚀 Deployment Workflow

### Git Branch Strategy

```
feature/* ──┬──> develop ──> staging ──> main
fix/*       │
hotfix/* ───┴────────────────────────────> main
```

### Deployment Process

1. **Push to `staging` branch** → Auto-deploys to staging environment
2. **Test on staging** → Verify at https://admin.staging.mapservices.in
3. **Merge to `main`** → Manually deploy to production

### Manual Deployment Commands

```bash
# SSH into server
ssh -i ~/Downloads/MAP/hostinger.pem root@72.62.79.173

# Deploy Production
cd /opt/map-hms/app
git pull origin main
docker exec map-hms-app composer install --no-dev
docker exec map-hms-app php artisan migrate --force
docker exec map-hms-app php artisan config:clear
docker restart map-hms-app

# Deploy Staging
cd /opt/map-hms-staging/app
git pull origin staging
docker exec map-hms-staging composer install --no-dev
docker exec map-hms-staging php artisan migrate --force
docker exec map-hms-staging php artisan config:clear
docker restart map-hms-staging
```

---

## 🔐 Security Configuration

### Cloudflare Settings
- **SSL Mode:** Full (strict)
- **Proxy Status:** Proxied (orange cloud)
- **Firewall:** Enabled

### Traefik Configuration
- **Location:** `/data/coolify/proxy/dynamic/map-hms.yml`
- **Features:**
  - Wildcard subdomain routing
  - Automatic HTTP → HTTPS redirect (via Cloudflare)
  - Load balancing between environments

### Application Security
- **OTP Authentication:** Phone + OTP login
- **Rate Limiting:** 5 attempts/minute on auth routes
- **Tenant Isolation:** RLS + Application-level scoping

---

## 📊 Monitoring

### Health Checks

```bash
# Production
curl https://api.mapservices.in/healthz
# Expected: {"ok":true,"checks":{"db":"ok","cache":"ok","queue":"ok"}}

# Staging
curl https://api.staging.mapservices.in/healthz
```

### Container Status

```bash
ssh -i hostinger.pem root@72.62.79.173 "docker ps --filter name=map-hms"
```

### Logs

```bash
# Production logs
docker logs map-hms-app --tail 100

# Staging logs
docker logs map-hms-staging --tail 100

# Laravel logs
docker exec map-hms-app tail -100 storage/logs/laravel.log
```

---

## 💾 Backup Strategy

### Database Backups

```bash
# Manual backup
docker exec map-hms-db pg_dump -U maphms maphms > backup_$(date +%Y%m%d).sql

# Restore
cat backup_20260103.sql | docker exec -i map-hms-db psql -U maphms -d maphms
```

### Recommended Backup Schedule
- **Daily:** Database dump to local storage
- **Weekly:** Full backup to external storage
- **Monthly:** Archive old backups

---

## 🆘 Troubleshooting

### Container Won't Start

```bash
# Check logs
docker logs map-hms-app

# Check disk space
df -h

# Restart container
docker restart map-hms-app
```

### Database Connection Issues

```bash
# Test database
docker exec map-hms-db psql -U maphms -c "SELECT 1"

# Check network
docker network inspect coolify
```

### High Memory Usage

```bash
# Check memory
free -h

# Restart all services
docker restart map-hms-app map-hms-staging
```

---

## 📞 Support

- **SSH Access:** `ssh -i hostinger.pem root@72.62.79.173`
- **Coolify UI:** http://72.62.79.173:8000
- **Repository:** https://github.com/paragmasteh/mapmars

---

*Last Updated: January 2026*

