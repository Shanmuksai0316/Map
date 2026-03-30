# Tenant Subdomain Setup Guide

**Purpose**: Configure local development environment for multi-tenant panel access  
**Status**: Required for UAT and E2E testing  
**Est. Time**: 5 minutes

---

## Quick Setup

### 1. Add Hosts Entries

#### macOS / Linux
```bash
sudo nano /etc/hosts
```

Add these lines:
```
127.0.0.1 admin.localhost
127.0.0.1 demo-college.localhost
127.0.0.1 another-college.localhost
```

Save and exit (Ctrl+X, Y, Enter)

#### Windows
1. Open Notepad as Administrator
2. Open file: `C:\Windows\System32\drivers\etc\hosts`
3. Add the same lines as above
4. Save and close

### 2. Verify DNS Resolution

```bash
ping admin.localhost
# Should respond from 127.0.0.1

ping demo-college.localhost
# Should respond from 127.0.0.1
```

---

## Panel Access URLs

Once hosts are configured and API server is running (`php artisan serve`):

### Super Admin Panel
- **URL**: http://admin.localhost:8000/admin
- **Tenant**: Central (no tenant context)
- **Role**: super_admin
- **Features**: Tenant management, system settings

### Campus Manager Panel
- **URL**: http://demo-college.localhost:8000/campus-manager
- **Tenant**: demo-college
- **Role**: campus_manager
- **Features**: Student management, imports, hostel management

### Rector Panel
- **URL**: http://demo-college.localhost:8000/rector
- **Tenant**: demo-college
- **Role**: rector
- **Features**: Approvals, reports, oversight

### College Management Panel
- **URL**: http://demo-college.localhost:8000/college-mgmt
- **Tenant**: demo-college (multi-campus)
- **Role**: college_mgmt
- **Features**: Multi-campus dashboard, analytics

---

## Test Credentials

### Getting OTP
All panels use OTP-based login. To get OTP codes:

**Option 1: Check logs**
```bash
cd /Users/paragmasteh/Downloads/MAP/api
tail -f storage/logs/laravel.log | grep OTP
```

**Option 2: Use test phone numbers** (if configured in `.env`)
```
TEST_OTP=123456
```

### Test Users

**Super Admin**:
- Phone: `+919999999999`
- OTP: Check logs or use TEST_OTP

**Rector**:
- Phone: `+919876543210`  
- OTP: Check logs
- Tenant: demo-college

**Campus Manager**:
- Phone: `+919876543211`
- OTP: Check logs
- Tenant: demo-college

---

## Troubleshooting

### "Unable to resolve host"
**Problem**: Browser can't find `*.localhost`

**Solutions**:
1. Verify `/etc/hosts` has entries
2. Flush DNS cache:
   - macOS: `sudo dscacheutil -flushcache; sudo killall -HUP mDNSResponder`
   - Linux: `sudo systemctl restart systemd-resolved`
   - Windows: `ipconfig /flushdns`
3. Try different browser
4. Restart browser

### "Connection refused" or "Unable to connect"
**Problem**: API server not running

**Solution**:
```bash
cd /Users/paragmasteh/Downloads/MAP/api
php artisan serve
# Server should start on http://localhost:8000
```

### "404 Not Found" on panel URL
**Problem**: Panel route not registered or wrong URL

**Check**:
1. Panel providers registered in `api/bootstrap/providers.php`
2. Using correct URL (see Panel Access URLs above)
3. Tenant exists in database

**Verify**:
```bash
php artisan route:list | grep rector
# Should show rector panel routes
```

### "500 Internal Server Error"
**Problem**: Configuration issue or missing tenant

**Debug**:
```bash
tail -f storage/logs/laravel.log
# Check for errors when accessing panel
```

**Common causes**:
- Tenant database doesn't exist
- Tenant not initialized properly
- Missing environment variables

### "401 Unauthorized" after OTP login
**Problem**: Session or authentication issue

**Solutions**:
1. Clear browser cookies/cache
2. Check `.env` has `SESSION_DRIVER=database` or `file`
3. Verify user has correct role in database

---

## Database Verification

### Check tenants exist
```bash
cd /Users/paragmasteh/Downloads/MAP/api
php artisan tinker
```

```php
// In tinker
\App\Models\Tenant::all();
// Should show demo-college and other tenants

\App\Models\User::where('phone', '+919876543210')->first();
// Should show rector user
```

### Create test tenant if missing
```php
// In tinker
$tenant = \App\Models\Tenant::create([
    'id' => 'demo-college',
    'name' => 'Demo College',
    'email' => 'admin@demo-college.edu',
]);

$domain = $tenant->domains()->create([
    'domain' => 'demo-college.localhost',
]);
```

---

## E2E Testing with Playwright

### Prerequisites
- ✅ Hosts configured
- ✅ API server running
- ✅ Tenants exist in database
- ✅ Test users created

### Run tests
```bash
cd /Users/paragmasteh/Downloads/MAP/api/tests/e2e

# Run specific test
npx playwright test rector-uat-test.spec.ts --headed

# Run all panel tests
npx playwright test --headed

# Run in debug mode
npx playwright test --debug
```

---

## Manual UAT Checklist

### Rector Panel Walkthrough
1. [ ] Navigate to http://demo-college.localhost:8000/rector
2. [ ] Login with rector phone number
3. [ ] Enter OTP from logs
4. [ ] Verify dashboard loads (stats, charts)
5. [ ] Navigate to "Approvals" section
6. [ ] View pending gate passes
7. [ ] Attempt approval (step-up OTP when backend ready)
8. [ ] Navigate to "Reports"
9. [ ] Export a report
10. [ ] Logout successfully

### Campus Manager Panel Walkthrough
1. [ ] Navigate to http://demo-college.localhost:8000/campus-manager
2. [ ] Login with campus manager credentials
3. [ ] View dashboard
4. [ ] Navigate to "Students" section
5. [ ] Attempt CSV import (verify wizard)
6. [ ] Navigate to "Hostels"
7. [ ] Create/edit a hostel
8. [ ] View room allocation
9. [ ] Check analytics
10. [ ] Logout

### Evidence Collection
For each test:
- Take screenshots at key steps
- Note any errors or unexpected behavior
- Record response times
- Check browser console for errors

Save evidence in: `docs/UAT_evidence.md`

---

## Production Considerations

### DNS Configuration
In production, configure actual DNS records:
```
A     admin.yourdomain.com     → Your-Server-IP
CNAME *.yourdomain.com         → admin.yourdomain.com
```

### SSL Certificates
Use wildcard SSL certificate:
```
*.yourdomain.com
```

Recommended: Use Let's Encrypt with Certbot

### Load Balancing
For multi-tenant at scale:
- Use subdomain-based routing at load balancer level
- Consider tenant database connection pooling
- Implement caching per tenant

---

**Setup Status**: ⏸️ Pending configuration  
**Next Step**: Add hosts entries and start API server  
**Documentation**: Complete
