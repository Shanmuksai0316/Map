# Test Push Notifications (Staff / Rector app)

Quick steps to see a push on your phone.

## 1. Get your user ID

From the **API** directory (project root: `api/`):

```bash
cd api
php artisan push:list-devices
```

Note the **user_id** for your user (the one you’re logged in as on the Staff app).

## 2. Send a test push

```bash
php artisan push:send-test <user_id>
```

Example: `php artisan push:send-test 5`

## 3. On the device

- Put the app in the **background** (or lock the phone).
- You should get a notification; **tap it** → app opens to the **Notifications** screen.

## If nothing appears

- **FCM not configured:** Run `php artisan push:check-config` and set `FCM_ENABLED=true` and FCM credentials in `api/.env` (see [PUSH_NOTIFICATIONS_SETUP.md](./PUSH_NOTIFICATIONS_SETUP.md)).
- **No token:** Log in again on the Staff app so it can register the FCM token; then run `push:list-devices` again.
- **Send to all:** `php artisan push:send-test-all` (optional: `--title="My title"` `--body="My body"`).
