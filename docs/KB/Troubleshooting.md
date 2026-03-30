# MAP-HMS Troubleshooting Guide

Common issues and their solutions for MAP-HMS development.

## Database Issues

### Issue: "SQLite database not found"
**Symptoms**: Migration errors, database connection failures
**Solution**:
```bash
cd api
touch database/database.sqlite
php artisan migrate
```
**Prevention**: Always create database.sqlite file before running migrations

### Issue: "Foreign key constraint fails"
**Symptoms**: Cannot delete records, foreign key errors
**Solution**:
```bash
# Enable foreign key constraints in SQLite
# Add to database.php config:
'foreign_key_constraints' => true,

# Or disable temporarily for migration
DB::statement('PRAGMA foreign_keys=OFF;');
// Your migration code
DB::statement('PRAGMA foreign_keys=ON;');
```

### Issue: "Migration rollback fails"
**Symptoms**: Cannot rollback migrations, database inconsistency
**Solution**:
```bash
# Manual rollback
php artisan migrate:rollback --step=1

# If that fails, manually fix database
php artisan migrate:status
# Check which migrations are marked as ran but don't exist
```

### Issue: "Database locked"
**Symptoms**: Concurrent access errors, slow queries
**Solution**:
```bash
# Check for long-running processes
ps aux | grep php

# Kill stuck processes
pkill -f "php artisan"

# Restart database connection
php artisan migrate:status
```

## Cache and Configuration Issues

### Issue: "Configuration not updated"
**Symptoms**: Changes to config files not reflected
**Solution**:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# For production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Issue: "Feature flags not working"
**Symptoms**: Feature flags returning wrong values
**Solution**:
```bash
# Check environment variables
php artisan tinker
>>> config('features.laundry_module')

# Clear config cache
php artisan config:clear

# Verify .env file has correct values
grep FEATURE_ .env
```

### Issue: "Routes not found"
**Symptoms**: 404 errors for valid routes
**Solution**:
```bash
# Clear route cache
php artisan route:clear

# List all routes
php artisan route:list

# Check route registration
php artisan route:list --name=your.route.name
```

## Queue and Job Issues

### Issue: "Queue jobs not processing"
**Symptoms**: Jobs stuck in pending state
**Solution**:
```bash
# Check queue worker status
ps aux | grep "queue:work"

# Start queue worker
php artisan queue:work

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Issue: "Jobs failing silently"
**Symptoms**: Jobs disappear without error logs
**Solution**:
```bash
# Check job logs
tail -f storage/logs/laravel.log

# Enable verbose logging
php artisan queue:work --verbose

# Check Redis connection
php artisan tinker
>>> Redis::ping()
```

### Issue: "Memory limit exceeded in jobs"
**Symptoms**: Jobs fail with memory errors
**Solution**:
```php
// In your job class
class YourJob implements ShouldQueue
{
    public $timeout = 300;
    public $tries = 3;
    
    public function handle()
    {
        // Process in chunks
        Model::chunk(100, function ($models) {
            // Process chunk
        });
    }
}
```

## Authentication Issues

### Issue: "JWT token expired"
**Symptoms**: 401 Unauthorized errors
**Solution**:
```bash
# Check token expiration settings
php artisan tinker
>>> config('sanctum.expiration')

# Clear expired tokens
php artisan sanctum:prune-expired

# Check user tokens
php artisan tinker
>>> User::find(1)->tokens
```

### Issue: "CORS errors"
**Symptoms**: Cross-origin request blocked
**Solution**:
```php
// Check CORS configuration in config/cors.php
'allowed_origins' => ['*'], // Development only
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
'allowed_headers' => ['*'],
```

### Issue: "Policy authorization fails"
**Symptoms**: 403 Forbidden errors
**Solution**:
```bash
# Check user permissions
php artisan tinker
>>> $user = User::find(1);
>>> $user->getAllPermissions();

# Check policy registration
php artisan route:list --middleware=auth
```

## Mobile App Issues

### Issue: "App won't connect to API"
**Symptoms**: Network errors, connection timeouts
**Solution**:
```typescript
// Check app.config.json
{
  "API_BASE": "http://10.0.2.2:8000/api/v1" // Android emulator
  // or "http://localhost:8000/api/v1" for iOS simulator
}

// Test API connectivity
curl http://10.0.2.2:8000/api/health
```

### Issue: "Metro bundler issues"
**Symptoms**: App won't start, bundling errors
**Solution**:
```bash
# Clear Metro cache
npx react-native start --reset-cache

# Clear node modules
rm -rf node_modules package-lock.json
npm install

# Clear React Native cache
npx react-native clean
```

### Issue: "iOS build fails"
**Symptoms**: Xcode build errors, pod issues
**Solution**:
```bash
# Update pods
cd ios
pod deintegrate
pod install

# Clean Xcode build
xcodebuild clean -workspace mobile.xcworkspace -scheme mobile

# Check iOS deployment target
# Ensure it matches in Podfile and Xcode project
```

### Issue: "Android build fails"
**Symptoms**: Gradle build errors, dependency conflicts
**Solution**:
```bash
# Clean Gradle cache
cd android
./gradlew clean

# Check Gradle version compatibility
./gradlew --version

# Update dependencies
./gradlew app:dependencies
```

## File Upload Issues

### Issue: "File upload fails"
**Symptoms**: Upload requests timeout or fail
**Solution**:
```bash
# Check file size limits
php -i | grep upload_max_filesize
php -i | grep post_max_size

# Check S3 configuration
php artisan tinker
>>> config('filesystems.disks.s3')
```

### Issue: "Presigned URL generation fails"
**Symptoms**: S3 URL generation errors
**Solution**:
```bash
# Check AWS credentials
aws configure list

# Test S3 connection
php artisan tinker
>>> Storage::disk('s3')->exists('test.txt')
```

## Testing Issues

### Issue: "Tests fail with database errors"
**Symptoms**: Test database not found, foreign key errors
**Solution**:
```bash
# Use in-memory database for tests
# In phpunit.xml:
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>

# Or create test database
touch database/testing.sqlite
```

### Issue: "Feature tests timeout"
**Symptoms**: Tests hang or timeout
**Solution**:
```php
// In your test
test('your test', function () {
    // Mock external API calls
    Http::fake([
        'external-api.com/*' => Http::response(['status' => 'ok']),
    ]);
    
    // Your test code
})->timeout(10);
```

### Issue: "Mobile tests fail on CI"
**Symptoms**: Tests pass locally but fail in CI
**Solution**:
```bash
# Check Jest configuration
# Ensure proper test environment setup

# For Detox E2E tests
detox build --configuration ios.sim.debug
detox test --configuration ios.sim.debug
```

## Performance Issues

### Issue: "Slow API responses"
**Symptoms**: High response times, timeouts
**Solution**:
```bash
# Check slow queries
# Enable query logging
DB::enableQueryLog();
// Your code
dd(DB::getQueryLog());

# Add database indexes
php artisan make:migration add_indexes_to_table
```

### Issue: "Memory usage high"
**Symptoms**: Out of memory errors, slow performance
**Solution**:
```php
// Use chunking for large datasets
Model::chunk(1000, function ($models) {
    // Process chunk
});

// Unload relationships
$models = Model::with('relation')->get();
$models->each->unsetRelation('relation');
```

### Issue: "Mobile app slow"
**Symptoms**: App lag, slow navigation
**Solution**:
```typescript
// Optimize FlatList
<FlatList
  data={data}
  renderItem={renderItem}
  keyExtractor={(item) => item.id}
  removeClippedSubviews={true}
  maxToRenderPerBatch={10}
  windowSize={10}
  getItemLayout={(data, index) => ({
    length: ITEM_HEIGHT,
    offset: ITEM_HEIGHT * index,
    index,
  })}
/>
```

## External Service Issues

### Issue: "SMS not sending"
**Symptoms**: MSG91 integration fails
**Solution**:
```bash
# Check MSG91 credentials
php artisan tinker
>>> config('services.msg91')

# Test SMS sending
php artisan tinker
>>> app(\App\Services\SmsService::class)->send('+1234567890', 'Test message')
```

### Issue: "Email not sending"
**Symptoms**: SendGrid integration fails
**Solution**:
```bash
# Check mail configuration
php artisan tinker
>>> config('mail')

# Test email sending
php artisan tinker
>>> Mail::raw('Test email', function ($message) {
    $message->to('test@example.com')->subject('Test');
});
```

### Issue: "Push notifications not working"
**Symptoms**: FCM integration fails
**Solution**:
```bash
# Check FCM configuration
php artisan tinker
>>> config('services.fcm')

# Test notification sending
php artisan tinker
>>> app(\App\Services\NotificationService::class)->sendPush('token', 'title', 'body')
```

## Deployment Issues

### Issue: "Deployment fails"
**Symptoms**: Build errors, deployment timeouts
**Solution**:
```bash
# Check environment variables
php artisan config:show

# Verify file permissions
chmod -R 755 storage bootstrap/cache

# Check disk space
df -h

# Check logs
tail -f storage/logs/laravel.log
```

### Issue: "Queue workers not starting"
**Symptoms**: Jobs not processing after deployment
**Solution**:
```bash
# Check supervisor configuration
sudo supervisorctl status

# Restart queue workers
sudo supervisorctl restart laravel-worker:*

# Check worker logs
tail -f storage/logs/worker.log
```

## Debugging Tools

### Laravel Debugging
```bash
# Enable debug mode
# In .env: APP_DEBUG=true

# Use Laravel Telescope (development)
composer require laravel/telescope
php artisan telescope:install

# Use Ray for debugging
composer require spatie/laravel-ray
```

### Mobile Debugging
```bash
# React Native debugging
npx react-native log-android
npx react-native log-ios

# Flipper debugging
# Install Flipper desktop app
# Enable in React Native app
```

### Database Debugging
```bash
# Query logging
DB::enableQueryLog();
// Your code
dd(DB::getQueryLog());

# Database browser
# Use SQLite browser for database.sqlite
```

## Getting More Help

### Log Files
- **Laravel**: `storage/logs/laravel.log`
- **Queue**: `storage/logs/worker.log`
- **Mobile**: Device logs via Flipper or console

### Common Commands
```bash
# Clear all caches
php artisan optimize:clear

# Check system status
php artisan about

# Database status
php artisan migrate:status

# Queue status
php artisan queue:work --once
```

### Emergency Recovery
```bash
# If everything is broken
git stash
git pull origin main
composer install
php artisan migrate:fresh --seed
php artisan cache:clear
```

---

*Troubleshooting guide version: v1.0*
*Owner: MAP Co-Pilot*
