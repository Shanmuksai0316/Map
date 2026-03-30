# Deployment Checklist - MAP-HMS

## Pre-Deployment

- [ ] Run database migrations: `php artisan migrate`
- [ ] Run database seeders if needed: `php artisan db:seed`
- [ ] Clear config cache: `php artisan config:cache`
- [ ] Clear route cache: `php artisan route:cache`
- [ ] Clear view cache: `php artisan view:cache`
- [ ] Create storage link: `php artisan storage:link`
- [ ] Verify environment variables are set (.env file)
- [ ] Run tests: `php artisan test`

## Queue Configuration

### Development
```bash
php artisan queue:work --tries=3 --timeout=90
```

### Production
Configure a process manager like Supervisor or Horizon to run queue workers:

```ini
[program:map-hms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-jobs=1000
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

### Horizon (Recommended)
```bash
php artisan horizon:install
php artisan horizon:publish
```

## Storage Configuration

### Local Storage
Run the following command to create the symbolic link:
```bash
php artisan storage:link
```

This creates a symlink from `public/storage` to `storage/app/public`, enabling public access to uploaded files.

### S3 Storage (Production)
Update `.env`:
```
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

## Reports Configuration

Reports are generated via queued jobs. Ensure:
- Queue worker is running
- Storage is configured (for download links)
- `FEATURE_SUPER_ADMIN_REPORTS=true` in config/features.php

## Health Check

After deployment, verify:
- [ ] Health endpoint: `curl https://your-domain.com/healthz`
- [ ] Admin panel accessible: `https://your-domain.com/admin`
- [ ] Queue jobs process successfully
- [ ] Reports can be generated and downloaded
- [ ] Storage uploads/downloads work

## Rollback

If issues occur:
1. Revert code: `git revert <commit-hash>`
2. Run migrations rollback if needed: `php artisan migrate:rollback`
3. Clear all caches: `php artisan optimize:clear`
4. Restart queue workers

## Monitoring

Monitor the following:
- Queue depth (number of pending jobs)
- Failed jobs count
- Storage disk usage
- Response times
- Error logs in `storage/logs/laravel.log`




