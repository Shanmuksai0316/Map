# MAP-HMS Integrations & Credentials

Required credentials and setup instructions for external service integrations.

## Overview

MAP-HMS integrates with several external services for notifications, payments, and file storage. This document outlines what credentials are needed and how to obtain them.

## SMS Integration (MSG91)

### Purpose
- Send SMS notifications for outpass approvals/rejections
- Send attendance alerts to parents
- Send emergency notifications

### Required Credentials
```bash
# Environment variables needed
MSG91_AUTH_KEY=your_auth_key_here
MSG91_SENDER_ID=your_sender_id_here
MSG91_ROUTE_ID=your_route_id_here
MSG91_DLT_TEMPLATE_ID=your_dlt_template_id_here
```

### How to Obtain
1. **Sign up** at [MSG91.com](https://msg91.com)
2. **Get Auth Key**:
   - Go to API section in dashboard
   - Copy your authentication key
3. **Get Sender ID**:
   - Apply for sender ID (6 characters max)
   - Usually your organization name (e.g., "MAPHMS")
4. **Get Route ID**:
   - Choose route type (Promotional/Transactional)
   - Transactional recommended for notifications
5. **DLT Registration**:
   - Register your sender ID for DLT compliance
   - Get template ID for your message templates

### Configuration
```php
// config/services.php
'msg91' => [
    'auth_key' => env('MSG91_AUTH_KEY'),
    'sender_id' => env('MSG91_SENDER_ID'),
    'route_id' => env('MSG91_ROUTE_ID'),
    'dlt_template_id' => env('MSG91_DLT_TEMPLATE_ID'),
],
```

### Testing
```bash
# Test SMS sending
php artisan tinker
>>> app(\App\Services\SmsService::class)->send('+1234567890', 'Test message')
```

## Email Integration (SendGrid)

### Purpose
- Send email notifications
- Send reports and exports
- Send system alerts

### Required Credentials
```bash
# Environment variables needed
SENDGRID_API_KEY=your_api_key_here
SENDGRID_FROM_EMAIL=noreply@yourdomain.com
SENDGRID_FROM_NAME=MAP-HMS
```

### How to Obtain
1. **Sign up** at [SendGrid.com](https://sendgrid.com)
2. **Get API Key**:
   - Go to Settings > API Keys
   - Create new API key with "Full Access"
3. **Verify Sender**:
   - Go to Settings > Sender Authentication
   - Verify single sender or domain
4. **Create Templates** (optional):
   - Design email templates for notifications
   - Get template IDs for use in code

### Configuration
```php
// config/mail.php
'driver' => env('MAIL_MAILER', 'sendgrid'),
'sendgrid' => [
    'api_key' => env('SENDGRID_API_KEY'),
    'from' => [
        'email' => env('SENDGRID_FROM_EMAIL'),
        'name' => env('SENDGRID_FROM_NAME'),
    ],
],
```

### Testing
```bash
# Test email sending
php artisan tinker
>>> Mail::raw('Test email', function ($message) {
    $message->to('test@example.com')->subject('Test');
});
```

## Push Notifications (FCM)

### Purpose
- Send real-time notifications to mobile apps
- Notify students of outpass status changes
- Send emergency alerts

### Required Credentials
```bash
# Environment variables needed
FCM_SERVER_KEY=your_server_key_here
FCM_SENDER_ID=your_sender_id_here
```

### How to Obtain
1. **Create Firebase Project**:
   - Go to [Firebase Console](https://console.firebase.google.com)
   - Create new project
2. **Add Android App**:
   - Add Android app with package name
   - Download `google-services.json`
3. **Add iOS App**:
   - Add iOS app with bundle ID
   - Download `GoogleService-Info.plist`
4. **Get Server Key**:
   - Go to Project Settings > Cloud Messaging
   - Copy Server Key
5. **Get Sender ID**:
   - Copy Sender ID from same page

### Mobile Configuration
```json
// android/app/google-services.json
{
  "project_info": {
    "project_id": "your-project-id"
  }
}
```

```xml
<!-- ios/mobile/GoogleService-Info.plist -->
<key>GOOGLE_APP_ID</key>
<string>your-app-id</string>
```

### Testing
```bash
# Test push notification
php artisan tinker
>>> app(\App\Services\NotificationService::class)->sendPush('device_token', 'Title', 'Body')
```

## Payment Integration (Razorpay)

### Purpose
- Process hostel fee payments
- Handle refunds
- Generate payment reports

### Required Credentials
```bash
# Environment variables needed
RAZORPAY_KEY_ID=your_key_id_here
RAZORPAY_KEY_SECRET=your_key_secret_here
RAZORPAY_WEBHOOK_SECRET=your_webhook_secret_here
```

### How to Obtain
1. **Sign up** at [Razorpay.com](https://razorpay.com)
2. **Get API Keys**:
   - Go to Settings > API Keys
   - Generate Test/Live keys
3. **Setup Webhooks**:
   - Go to Settings > Webhooks
   - Add webhook URL: `https://yourdomain.com/api/webhooks/razorpay`
   - Copy webhook secret
4. **Configure Products**:
   - Create products for different fee types
   - Set up payment methods

### Configuration
```php
// config/services.php
'razorpay' => [
    'key_id' => env('RAZORPAY_KEY_ID'),
    'key_secret' => env('RAZORPAY_KEY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
],
```

### Testing
```bash
# Test payment creation
php artisan tinker
>>> app(\App\Services\PaymentService::class)->createOrder(1000, 'INR')
```

## File Storage (AWS S3)

### Purpose
- Store uploaded documents
- Store profile pictures
- Store generated reports

### Required Credentials
```bash
# Environment variables needed
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here
AWS_DEFAULT_REGION=your_region_here
AWS_BUCKET=your_bucket_name_here
```

### How to Obtain
1. **Create AWS Account**:
   - Sign up at [AWS Console](https://aws.amazon.com)
2. **Create IAM User**:
   - Go to IAM > Users
   - Create user with S3 permissions
3. **Create S3 Bucket**:
   - Go to S3 service
   - Create bucket with appropriate permissions
4. **Get Access Keys**:
   - Go to IAM > Users > Security Credentials
   - Create access key
5. **Configure CORS** (for web uploads):
   ```json
   [
     {
       "AllowedHeaders": ["*"],
       "AllowedMethods": ["GET", "POST", "PUT"],
       "AllowedOrigins": ["*"],
       "ExposeHeaders": []
     }
   ]
   ```

### Configuration
```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
],
```

### Testing
```bash
# Test S3 connection
php artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'Hello World')
>>> Storage::disk('s3')->get('test.txt')
```

## Analytics (Segment.com)

### Purpose
- Track user behavior
- Monitor app performance
- Generate usage reports

### Required Credentials
```bash
# Environment variables needed
SEGMENT_WRITE_KEY=your_write_key_here
```

### How to Obtain
1. **Sign up** at [Segment.com](https://segment.com)
2. **Create Workspace**:
   - Create new workspace
3. **Add Source**:
   - Add React Native source
   - Get write key
4. **Configure Destinations**:
   - Connect to analytics tools (Google Analytics, Mixpanel, etc.)

### Mobile Configuration
```typescript
// mobile/src/lib/analytics.ts
import { Analytics } from '@segment/analytics-react-native';

const analytics = new Analytics({
  writeKey: Config.SEGMENT_WRITE_KEY,
});
```

## Error Monitoring (Sentry)

### Purpose
- Track application errors
- Monitor performance
- Get error alerts

### Required Credentials
```bash
# Environment variables needed
SENTRY_DSN=your_dsn_here
SENTRY_ENVIRONMENT=production
```

### How to Obtain
1. **Sign up** at [Sentry.io](https://sentry.io)
2. **Create Project**:
   - Create Laravel project
   - Create React Native project
3. **Get DSN**:
   - Copy DSN from project settings
4. **Configure Alerts**:
   - Set up error rate alerts
   - Configure notification channels

### Configuration
```php
// config/sentry.php
'dsn' => env('SENTRY_DSN'),
'environment' => env('SENTRY_ENVIRONMENT', 'production'),
```

## Development vs Production

### Development Environment
```bash
# Use test credentials for development
MSG91_AUTH_KEY=test_auth_key
SENDGRID_API_KEY=test_api_key
RAZORPAY_KEY_ID=test_key_id
AWS_BUCKET=map-hms-dev
```

### Production Environment
```bash
# Use live credentials for production
MSG91_AUTH_KEY=live_auth_key
SENDGRID_API_KEY=live_api_key
RAZORPAY_KEY_ID=live_key_id
AWS_BUCKET=map-hms-prod
```

## Security Best Practices

### Credential Storage
- **Never commit credentials to version control**
- Use environment variables for all credentials
- Use different credentials for dev/staging/production
- Rotate credentials regularly

### Access Control
- Use IAM roles with minimal required permissions
- Enable MFA on all service accounts
- Monitor credential usage and access logs
- Use webhook signatures for verification

### Testing Credentials
```bash
# Test all integrations
php artisan integration:test

# Test specific service
php artisan integration:test --service=sms
php artisan integration:test --service=email
php artisan integration:test --service=payment
```

## Troubleshooting

### Common Issues
1. **SMS not sending**: Check DLT registration and template approval
2. **Email bouncing**: Verify sender authentication in SendGrid
3. **Push notifications failing**: Check FCM configuration and device tokens
4. **Payment failures**: Verify Razorpay webhook configuration
5. **File upload issues**: Check S3 bucket permissions and CORS

### Debug Commands
```bash
# Check credential configuration
php artisan config:show services

# Test service connectivity
php artisan tinker
>>> config('services.msg91')
>>> config('services.sendgrid')
>>> config('services.razorpay')
```

---

*Integrations guide version: v1.0*
*Owner: MAP Co-Pilot*
