# MAP-HMS FAQ

Frequently asked questions for developers working on MAP-HMS.

## General Questions

### Q: What is MAP-HMS?
**A**: MAP-HMS (Management and Access Portal - Hostel Management System) is a multi-tenant hostel management system built with Laravel API, Filament admin panel, and React Native mobile apps.

### Q: What are the main components?
**A**: 
- **Laravel API**: RESTful backend with multi-tenant architecture
- **Filament Admin**: Web-based admin panel for campus managers
- **React Native Mobile**: Student and Guard mobile applications
- **Multi-tenant Support**: Campus/Hostel isolation with role-based access

### Q: How do I get started?
**A**: Follow the [QuickStart Guide](../ONBOARDING_QuickStart.md) for a complete setup in 60 minutes.

## Development Questions

### Q: How do I add a new feature?
**A**: 
1. Create feature branch: `feature/your-feature-name`
2. Add database migration if needed
3. Create model with proper tenant scoping
4. Add policy for authorization
5. Create controller with validation
6. Add routes to `api.php`
7. Write tests (Pest for API, Jest for mobile)
8. Update documentation

### Q: How do I handle multi-tenancy?
**A**: 
- All business tables must include `tenant_id`
- Use global scopes for automatic tenant filtering
- Policies should check tenant membership
- Middleware validates tenant access

### Q: Where do I put secrets?
**A**: 
- **API**: Use `.env` file (never commit this file)
- **Mobile**: Use `app.config.json` for non-sensitive config
- **Production**: Use environment variables or secret management services
- **Never**: Commit secrets to version control

### Q: How do feature flags work?
**A**: 
Feature flags are defined in `config/features.php`:
```php
'laundry_module' => env('FEATURE_LAUNDRY', false),
```
Use in controllers:
```php
abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);
```

### Q: How do I add a new API endpoint?
**A**: 
1. Create controller method
2. Add request validation class
3. Create resource for response formatting
4. Add route to `api.php`
5. Add policy for authorization
6. Write feature tests

## Mobile Development Questions

### Q: How does offline support work?
**A**: 
- Actions are queued when offline
- Data is cached locally using AsyncStorage
- Automatic sync when connection restored
- Visual indicators show offline status

### Q: How do I add a new mobile screen?
**A**: 
1. Create screen component in appropriate directory
2. Add navigation type to stack param list
3. Add route to navigator
4. Create store actions if needed
5. Add API endpoints
6. Write component tests

### Q: How do push notifications work?
**A**: 
- FCM (Firebase Cloud Messaging) integration
- Token registration on app launch
- Server sends notifications via FCM API
- Handles both foreground and background messages

## Database Questions

### Q: How do I create a new migration?
**A**: 
```bash
php artisan make:migration create_your_table_name
```
Always include `tenant_id`, `created_at`, `updated_at` for business tables.

### Q: How do I run migrations?
**A**: 
```bash
# Development
php artisan migrate

# Fresh install with seeders
php artisan migrate:fresh --seed
```

### Q: How do I add indexes for performance?
**A**: 
Add indexes in migrations for frequently queried columns:
```php
$table->index(['tenant_id', 'status']);
$table->index(['hostel_id', 'created_at']);
```

## Testing Questions

### Q: How do I run tests?
**A**: 
```bash
# API tests
cd api && vendor/bin/pest

# Mobile tests
cd mobile && npm test

# Specific test file
vendor/bin/pest tests/Feature/OutPassTest.php
```

### Q: How do I write a good test?
**A**: 
- Test happy path and error cases
- Use factories for test data
- Mock external services
- Assert both response and database state
- Include tenant isolation tests

### Q: How do I test multi-tenancy?
**A**: 
```php
test('user cannot access other tenant data', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $user1 = User::factory()->for($tenant1)->create();
    $data2 = SomeModel::factory()->for($tenant2)->create();
    
    $this->actingAs($user1)
        ->getJson("/api/v1/some-model/{$data2->id}")
        ->assertForbidden();
});
```

## Deployment Questions

### Q: How do I deploy to production?
**A**: 
Follow the [Release Checklist](../RELEASE_Checklist.md):
1. Run all tests
2. Check environment variables
3. Run migrations
4. Clear caches
5. Update feature flags
6. Deploy code
7. Monitor logs

### Q: How do I handle database migrations in production?
**A**: 
- Always test migrations on staging first
- Use `--force` flag only when necessary
- Have rollback plan ready
- Consider zero-downtime migrations for large tables

### Q: How do I monitor the application?
**A**: 
- **Errors**: Sentry integration
- **Performance**: Laravel Telescope (dev), monitoring tools (prod)
- **Queues**: Laravel Horizon
- **Logs**: Application logs + centralized logging

## Troubleshooting Questions

### Q: Tests are failing, what do I do?
**A**: 
1. Check [Troubleshooting Guide](./Troubleshooting.md)
2. Ensure database is clean: `php artisan migrate:fresh --seed`
3. Check environment variables
4. Clear caches: `php artisan cache:clear`
5. Check test data setup

### Q: Mobile app won't connect to API?
**A**: 
1. Check `app.config.json` API_BASE URL
2. Ensure API server is running
3. For Android emulator, use `http://10.0.2.2:8000`
4. Check network connectivity
5. Verify CORS settings

### Q: Queue jobs are not processing?
**A**: 
1. Check queue worker is running: `php artisan queue:work`
2. Check Redis connection
3. Check job logs in storage/logs
4. Verify job class exists and is properly namespaced

### Q: Feature flag not working?
**A**: 
1. Check `config/features.php` configuration
2. Verify environment variable is set
3. Clear config cache: `php artisan config:clear`
4. Check feature flag usage in code

## Security Questions

### Q: How do I handle sensitive data?
**A**: 
- Encrypt PII at rest
- Use HTTPS for all communications
- Implement proper authentication/authorization
- Follow [Security Practices](../SECURITY_Practices.md)
- Audit log all sensitive operations

### Q: How do I prevent data leaks between tenants?
**A**: 
- Always use global scopes
- Validate tenant access in policies
- Test tenant isolation thoroughly
- Use middleware to validate tenant membership

### Q: How do I handle file uploads securely?
**A**: 
- Use presigned S3 URLs for uploads
- Validate file types and sizes
- Scan uploaded files for malware
- Strip EXIF data from images
- Never accept base64 uploads

## Performance Questions

### Q: How do I optimize slow queries?
**A**: 
- Add proper database indexes
- Use eager loading to prevent N+1 queries
- Implement query result caching
- Use database query profiling tools

### Q: How do I handle high traffic?
**A**: 
- Implement rate limiting
- Use Redis for caching and sessions
- Optimize database queries
- Consider read replicas for heavy read operations
- Use CDN for static assets

### Q: How do I optimize mobile app performance?
**A**: 
- Implement lazy loading for lists
- Optimize images and assets
- Use FlatList for large datasets
- Implement proper caching strategies
- Monitor memory usage

## Integration Questions

### Q: How do I add a new external service?
**A**: 
1. Add service configuration to `config/services.php`
2. Create service class for API integration
3. Add webhook handling if needed
4. Implement proper error handling and retries
5. Add monitoring and logging

### Q: How do I handle webhooks?
**A**: 
- Verify HMAC signatures
- Store event IDs to prevent duplicates
- Implement idempotency
- Handle failures gracefully
- Log all webhook activities

### Q: How do I integrate with payment systems?
**A**: 
- Use Razorpay SDK for payments
- Implement webhook handling for payment events
- Store payment references securely
- Handle payment failures and refunds
- Comply with PCI DSS requirements

## Getting Help

### Q: Where can I find more information?
**A**: 
- **Documentation**: Browse the [docs/](../README.md) directory
- **API Spec**: See `api_spec_v_1_1.md`
- **Architecture**: Read [ARCHITECTURE_Overview.md](../ARCHITECTURE_Overview.md)
- **Troubleshooting**: Check [Troubleshooting.md](./Troubleshooting.md)

### Q: How do I report bugs?
**A**: 
1. Check if it's already reported
2. Create detailed bug report with steps to reproduce
3. Include error logs and environment details
4. Test on latest version first

### Q: How do I suggest new features?
**A**: 
1. Check existing feature requests
2. Create detailed feature request
3. Explain use case and benefits
4. Consider implementation complexity

---

*FAQ version: v1.0*
*Owner: MAP Co-Pilot*
