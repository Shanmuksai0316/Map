# 🚀 Hostinger Deployment Guide

## Quick Deploy - Subdomain Fix

### Prerequisites
- SSH access to Coolify server (72.62.79.173)
- `hostinger/hostinger.pem` key file
- Server username

### Option 1: Automated Deployment

```bash
cd /Users/paragmasteh/Downloads/MAP

# Set your Hostinger username and deploy
./scripts/deploy-to-hostinger.sh
```

**Common Hostinger usernames:**
- `root`
- `ubuntu`  
- `u123456789` (Hostinger format)
- Your custom username

### Option 2: Manual Deployment

If automated script fails, deploy manually:

```bash
# 1. Connect to Hostinger
ssh -i hostinger/hostinger.pem root@72.62.79.173

# 2. Navigate to project
cd /var/www/map-hms  # Or your project path

# 3. Pull latest code
git pull origin main

# 4. Deploy subdomain fix
cd api
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan cache:clear

# 5. FIX EXISTING TENANTS (CRITICAL!)
php artisan tenants:fix-domains

# 6. Optimize
php artisan config:cache
php artisan route:cache

# 7. Restart services
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

### Verification

After deployment:

```bash
# On server
cd /var/www/map-hms/api

# Check tenants have domains (should be 0)
php artisan tinker --execute="echo \App\Models\Tenant::doesntHave('domains')->count();"

# List all tenants with domains
php artisan tinker
```

```php
\App\Models\Tenant::with('domains')->get()->each(function($t) {
    echo "{$t->code}: " . ($t->domains->first()?->domain ?? '❌ NO DOMAIN') . "\n";
});
```

Then test in browser:
1. **https://admin.mapservices.in** - Super Admin login
2. Create new tenant with subdomain "testdeploy"
3. **https://testdeploy.mapservices.in/campus-manager** - Should load ✅

---

## Server Details

**Current Configuration:**
- **Server IP**: 72.62.79.173 (Coolify server)
- **SSH Key**: `hostinger/hostinger.pem`
- **SSH User**: root
- **Domain**: mapservices.in (behind Cloudflare)
- **Container**: map-hms-app (Docker)
- **App Path in Container**: /var/www/html

**DNS Setup:**
- Primary: admin.mapservices.in → 104.21.16.23 (Cloudflare)
- Origin: 161.97.104.192 (Hostinger VPS)
- Wildcard: *.mapservices.in → Cloudflare

---

## What Was Deployed

**Commit**: `9bf2fcf` - fix(tenant): create domain records during tenant onboarding

**Changes:**
- ✅ Automatic domain creation for new tenants
- ✅ Repair command for existing tenants: `php artisan tenants:fix-domains`
- ✅ 8 comprehensive test cases
- ✅ Complete documentation

**Files Changed:**
1. `api/app/Filament/Pages/Admin/TenantOnboardingWizard.php`
2. `api/config/app.php`
3. `api/app/Console/Commands/FixTenantDomains.php`
4. `api/tests/Feature/Tenancy/TenantDomainCreationTest.php`

---

## Troubleshooting

### Cannot Connect via SSH

```bash
# Check permissions
chmod 400 hostinger/hostinger.pem

# Try with verbose mode
ssh -i hostinger/hostinger.pem -v root@72.62.79.173

# Common issues:
# 1. Wrong username
# 2. Key not authorized on server
# 3. Firewall blocking port 22
```

### Fix Command Not Found

```bash
# On server
cd /var/www/map-hms/api
composer dump-autoload
php artisan config:clear
php artisan tenants:fix-domains
```

### Subdomain Still Not Working

1. Check domain exists in database
2. Verify DNS propagation
3. Check Cloudflare SSL settings
4. Clear browser cache

---

## Rollback

If deployment causes issues:

```bash
ssh -i hostinger/hostinger.pem root@72.62.79.173
cd /var/www/map-hms
git reset --hard HEAD~1
cd api
php artisan config:cache
sudo systemctl restart php-fpm nginx
```

---

## Migration from AWS Complete

✅ **AWS references removed**
✅ **Hostinger deployment configured**
✅ **Scripts updated for Hostinger**

**Old AWS deployment script deleted**: `scripts/deploy-main-to-production.sh`
**New Hostinger script created**: `scripts/deploy-to-hostinger.sh`

---

## Quick Command Reference

```bash
# Deploy (from project root)
./scripts/deploy-to-hostinger.sh

# Connect to server
ssh -i hostinger/hostinger.pem root@72.62.79.173

# Run artisan command in container
ssh -i hostinger/hostinger.pem root@72.62.79.173 "docker exec map-hms-app php artisan --version"

# Clear caches
ssh -i hostinger/hostinger.pem root@72.62.79.173 "docker exec map-hms-app php artisan config:clear && docker exec map-hms-app php artisan cache:clear"

# Monitor logs
ssh -i hostinger/hostinger.pem root@72.62.79.173 "docker logs -f map-hms-app"

# Fix tenants
ssh -i hostinger/hostinger.pem root@72.62.79.173 "docker exec map-hms-app php artisan tenants:fix-domains"

# Restart container
ssh -i hostinger/hostinger.pem root@72.62.79.173 "docker restart map-hms-app"

# Interactive shell in container
ssh -i hostinger/hostinger.pem root@72.62.79.173 "docker exec -it map-hms-app sh"
```

---

**Last Updated**: January 8, 2026
**Status**: Ready for deployment
**Server**: Coolify on Hostinger (72.62.79.173)
