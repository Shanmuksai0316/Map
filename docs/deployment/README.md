# Deployment Documentation

This directory contains deployment guides for MAP-HMS on **Hostinger VPS**.

---

## 📚 Documentation Overview

### Primary Guide

1. **[INFRASTRUCTURE.md](./INFRASTRUCTURE.md)** ⭐ **START HERE**
   - Complete Hostinger VPS architecture
   - All service configurations
   - Monitoring and troubleshooting

### Supporting Documentation

2. **[DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)**
   - Pre-deployment verification
   - Post-deployment validation

---

## 🏗️ Infrastructure Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                 HOSTINGER VPS (72.62.79.173)                        │
│                 8 vCPU | 32GB RAM | 400GB NVMe                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐      ┌─────────────┐      ┌─────────────┐         │
│  │  Traefik    │      │ Production  │      │  Staging    │         │
│  │  (Proxy)    │──────│ map-hms-app │      │ map-hms-    │         │
│  │  :80/:443   │      │   :8081     │      │ staging     │         │
│  └─────────────┘      └─────────────┘      │   :8082     │         │
│         │                    │             └─────────────┘         │
│         │                    │                    │                 │
│         │             ┌──────┴────────────────────┘                 │
│         │             │                                             │
│         │      ┌──────┴──────┐      ┌─────────────┐                │
│         │      │ PostgreSQL  │      │   Redis     │                │
│         │      │ map-hms-db  │      │ map-hms-    │                │
│         │      │   :5432     │      │ redis:6379  │                │
│         │      └─────────────┘      └─────────────┘                │
│         │                                                          │
└─────────┴──────────────────────────────────────────────────────────┘
```

---

## 🚀 Quick Start

### Deploy Code Changes

```bash
# SSH into server
ssh -i hostinger.pem root@72.62.79.173

# Deploy to Production
cd /opt/map-hms/app
git pull origin main
docker exec map-hms-app composer install --no-dev
docker exec map-hms-app php artisan migrate --force
docker exec map-hms-app php artisan config:clear
docker restart map-hms-app

# Deploy to Staging
cd /opt/map-hms-staging/app
git pull origin staging
docker exec map-hms-staging composer install --no-dev
docker exec map-hms-staging php artisan migrate --force
docker exec map-hms-staging php artisan config:clear
docker restart map-hms-staging
```

### Verify Deployment

```bash
# Health checks
curl https://api.mapservices.in/healthz
curl https://api.staging.mapservices.in/healthz
```

---

## 🌐 Environment URLs

| Environment | URL | Branch | SSL |
|-------------|-----|--------|-----|
| **Production** | https://admin.mapservices.in | `main` | Cloudflare |
| **Staging** | https://admin.staging.mapservices.in | `staging` | Let's Encrypt |
| **Local** | http://admin.localhost:8000 | `feature/*` | None |

---

## 📋 Deployment Workflow

```
┌──────────────────────────────────────────────────────────────┐
│                    GIT BRANCH STRATEGY                        │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  feature/*  ──┬──>  develop  ──>  staging  ──>  main        │
│  fix/*        │                                              │
│  hotfix/*  ───┴────────────────────────────>  main          │
│                                                              │
│  Branch        Deploy To              How                    │
│  ──────────    ─────────────────      ─────────────────     │
│  feature/*     Local only             Automatic (npm start)  │
│  staging       staging.mapservices    Manual (git pull)      │
│  main          mapservices.in         Manual (git pull)      │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## 🔑 Access

### SSH Access
```bash
ssh -i ~/Downloads/MAP/hostinger.pem root@72.62.79.173
```

### Coolify Dashboard
```
URL: http://72.62.79.173:8000
```

### Databases
```bash
# Production
docker exec -it map-hms-db psql -U maphms -d maphms

# Staging
docker exec -it map-hms-db psql -U maphms_staging -d maphms_staging
```

---

## 📞 Troubleshooting

### Container Issues
```bash
# Check status
docker ps --filter name=map-hms

# View logs
docker logs map-hms-app --tail 100

# Restart
docker restart map-hms-app
```

### Database Issues
```bash
# Test connection
docker exec map-hms-db psql -U maphms -c "SELECT 1"
```

### SSL Issues
```bash
# Check certificates
docker exec coolify-proxy cat /traefik/acme.json | jq '.letsencrypt'
```

---

## ✅ Deployment Checklist

### Before Deploy
- [ ] All tests passing locally
- [ ] Code reviewed and merged to target branch
- [ ] Database migrations are backward-compatible

### After Deploy
- [ ] Health check returns `{"ok":true}`
- [ ] Admin panel accessible
- [ ] Mobile app can connect
- [ ] No errors in logs

---

**Detailed Guide:** [INFRASTRUCTURE.md](./INFRASTRUCTURE.md)
