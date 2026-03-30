# MAP-HMS Release Checklist

Pre-deployment checklist and procedures for MAP-HMS releases.

## Pre-Release Checklist

### Code Quality & Testing
- [ ] **All tests passing**
  ```bash
  vendor/bin/pest                    # API tests
  cd mobile && npm test              # Mobile tests
  npm run test:e2e                   # E2E tests (if applicable)
  ```

- [ ] **Code style compliance**
  ```bash
  ./vendor/bin/pint --test           # PHP code style
  cd mobile && npm run lint          # TypeScript linting
  ```

- [ ] **Static analysis clean**
  ```bash
  ./vendor/bin/phpstan analyse       # PHP static analysis
  cd mobile && npm run type-check    # TypeScript type checking
  ```

- [ ] **Security audit**
  ```bash
  composer audit                     # PHP dependencies
  cd mobile && npm audit             # Node dependencies
  ```

### Documentation & Configuration
- [ ] **Documentation updated**
  - [ ] API documentation (OpenAPI/Swagger)
  - [ ] README / release notes updated
  - [ ] Migration notes documented (manual payments, schema alignment)

- [ ] **Environment variables verified**
  ```bash
  # Check all required environment variables
  php artisan config:show | grep -E "(API_|DB_|MAIL_|CACHE_)"
  ```

- [ ] **Step-up OTP coverage confirmed**
  - [ ] Tenant activation/rollback flows require OTP
  - [ ] Manual payment edit endpoints require OTP
  - [ ] CSV/export endpoints require OTP

### Database & Migrations
- [ ] **Migrations tested**
  ```bash
  php artisan migrate:status         # Check migration status
  php artisan migrate:fresh --seed   # Test fresh installation
  ```

- [ ] **Database backup created**
  ```bash
  ./scripts/db-dump.sh               # Create backup
  ```

- [ ] **Seeders updated** (if applicable)
  ```bash
  php artisan db:seed --class=DemoTenantSeeder   # Ensures MAP-STXAV demo tenant exists
  ```

### Performance & Monitoring
- [ ] **Performance benchmarks**
  - [ ] API response times < 500ms
  - [ ] Database query performance
  - [ ] Mobile app startup time
  - [ ] Memory usage within limits

- [ ] **Monitoring configured**
  - [ ] Sentry error tracking
  - [ ] Application performance monitoring
  - [ ] Database monitoring
  - [ ] Queue monitoring

## Deployment Procedures

### Staging Deployment
```bash
# 1. Deploy to staging
git checkout main
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 2. Run post-deployment tests
php artisan test
curl https://staging-api.map-hms.com/v1/central-healthz

# 3. Verify integrations
php artisan integration:test --env=staging
```

### Production Deployment
```bash
# 1. Pre-deployment backup
./scripts/db-dump.sh
./scripts/storage-backup.sh

# 2. Deploy code
git checkout main
git pull origin main
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Restart services
sudo supervisorctl restart laravel-worker:*
sudo systemctl reload nginx

# 6. Verify deployment
curl https://api.map-hms.com/v1/central-healthz
```

## Post-Deployment Verification

### Health Checks
- [ ] **API Health**
  ```bash
  curl https://api.map-hms.com/v1/central-healthz
  curl https://tenant-subdomain.map-hms.com/v1/healthz   # tenant-scoped
  ```

- [ ] **Database connectivity**
  ```bash
  php artisan tinker
  >>> DB::connection()->getPdo()
  >>> User::count()
  ```

- [ ] **Cache functionality**
  ```bash
  php artisan tinker
  >>> Cache::put('test', 'value', 60)
  >>> Cache::get('test')
  ```

- [ ] **Queue processing**
  ```bash
  php artisan queue:monitor
  php artisan horizon:status
  ```

### Integration Tests
- [ ] **SMS Service (MSG91)**
  ```bash
  php artisan tinker
  >>> app(\App\Services\SmsService::class)->send('+1234567890', 'Test message')
  ```

- [ ] **Email Service (SendGrid)**
  ```bash
  php artisan tinker
  >>> Mail::raw('Test email', function ($message) {
      $message->to('test@example.com')->subject('Test');
  });
  ```

- [ ] **Push Notifications (FCM)**
  ```bash
  php artisan tinker
  >>> app(\App\Services\NotificationService::class)->sendPush('test_token', 'Title', 'Body')
  ```

- [ ] **Step-up OTP Service**
  ```bash
  php artisan tinker
  >>> $user = \App\Models\User::role('Rector')->first();
  >>> $svc = app(\App\Services\StepUpOtpService::class);
  >>> $svc->startOtp($user, \App\Services\StepUpOtpService::PURPOSE_RECTOR_APPROVAL);
  ```

- [ ] **Manual Payment Tracking**
  ```bash
  php artisan tinker
  >>> $cm = \App\Models\User::role('Campus Manager')->first();
  >>> \Illuminate\Support\Facades\Auth::login($cm);
  >>> app(\App\Http\Controllers\Api\V1\PaymentController::class)->getPaymentSummary(request());
  ```

- [ ] **File Storage (S3)**
  ```bash
  php artisan tinker
  >>> Storage::disk('s3')->put('test.txt', 'Hello World')
  >>> Storage::disk('s3')->get('test.txt')
  ```

### Mobile App Verification
- [ ] **Android App**
  - [ ] App builds successfully
  - [ ] Login functionality works
  - [ ] API connectivity verified
  - [ ] Push notifications working
  - [ ] Connectivity loss banner displays when network disabled

- [ ] **iOS App**
  - [ ] App builds successfully
  - [ ] Login functionality works
  - [ ] API connectivity verified
  - [ ] Push notifications working
  - [ ] Connectivity loss banner displays when network disabled

## Rollback Procedures

### Emergency Rollback
```bash
# 1. Stop services
sudo supervisorctl stop laravel-worker:*
sudo systemctl stop nginx

# 2. Restore code
git checkout previous-stable-tag
composer install --no-dev --optimize-autoloader

# 3. Restore database (if needed)
cp backups/latest-backup.sqlite database/database.sqlite

# 4. Clear caches
php artisan optimize:clear

# 5. Restart services
sudo systemctl start nginx
sudo supervisorctl start laravel-worker:*

# 6. Verify rollback
curl https://api.map-hms.com/v1/central-healthz
```

### Database Rollback
```bash
# Rollback specific migration
php artisan migrate:rollback --step=1

# Rollback to specific batch
php artisan migrate:rollback --batch=5

# Check migration status
php artisan migrate:status
```

## Monitoring & Alerts

### Post-Deployment Monitoring
- [ ] **Error rates** < 1%
- [ ] **Response times** < 500ms average
- [ ] **Queue processing** no backlog
- [ ] **Database performance** normal
- [ ] **Memory usage** within limits
- [ ] **Disk space** sufficient

### Alert Configuration
- [ ] **Sentry alerts** configured
- [ ] **Performance monitoring** alerts
- [ ] **Database monitoring** alerts
- [ ] **Queue monitoring** alerts
- [ ] **Uptime monitoring** configured

### Log Monitoring
```bash
# Monitor application logs
tail -f storage/logs/laravel.log

# Monitor error logs
tail -f storage/logs/laravel.log | grep ERROR

# Monitor queue logs
tail -f storage/logs/worker.log
```

> **Note:** MAP-HMS v1.x ships with all core modules (Security, Laundry, Sports) enabled for every tenant. No runtime feature flags are used; changes require PRD updates.

## Security Verification

### Security Checklist
- [ ] **SSL certificates** valid and not expiring soon
- [ ] **Security headers** configured
- [ ] **Rate limiting** working
- [ ] **Authentication** functioning
- [ ] **Authorization** policies enforced
- [ ] **Input validation** working
- [ ] **File uploads** secure
- [ ] **API endpoints** protected

### Security Testing
```bash
# Check for security vulnerabilities
composer audit
npm audit

# Test authentication
curl -X POST https://api.map-hms.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Test authorization
curl -H "Authorization: Bearer invalid-token" \
  https://api.map-hms.com/api/v1/outpasses
```

## Performance Optimization

### Post-Deployment Optimization
```bash
# Optimize autoloader
composer dump-autoload --optimize

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize database
php artisan tinker
>>> DB::statement('VACUUM;')
>>> DB::statement('ANALYZE;')
```

### Performance Monitoring
- [ ] **API response times** monitored
- [ ] **Database query performance** tracked
- [ ] **Memory usage** monitored
- [ ] **Queue processing times** tracked
- [ ] **File upload/download** performance

## Communication & Documentation

### Release Communication
- [ ] **Release notes** prepared
- [ ] **Stakeholders notified** of deployment
- [ ] **Support team briefed** on changes
- [ ] **Documentation updated**

### Post-Release Documentation
- [ ] **Deployment log** updated
- [ ] **Issues encountered** documented
- [ ] **Performance metrics** recorded
- [ ] **Lessons learned** documented

## Emergency Procedures

### Incident Response
1. **Assess impact** of the issue
2. **Implement immediate fix** or rollback
3. **Notify stakeholders** of the incident
4. **Document incident** details
5. **Conduct post-incident review**

### Emergency Contacts
- **Development Team**: [contact information]
- **DevOps Team**: [contact information]
- **Support Team**: [contact information]
- **Management**: [contact information]

---

*Release checklist version: v1.0*
*Owner: MAP Co-Pilot*
