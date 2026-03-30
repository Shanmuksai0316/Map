# MAP-HMS Observability Guide v1.0

This guide covers the observability features implemented in MAP-HMS v1.0, including monitoring, logging, and metrics collection.

## Overview

MAP-HMS v1.0 includes comprehensive observability features to monitor application health, track performance, and collect business metrics.

## Components

### 1. Sentry Integration

**Purpose**: Error tracking and performance monitoring

**Configuration**:
```bash
# .env
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
SENTRY_TRACES_SAMPLE_RATE=0.2
```

**Features**:
- Automatic error capture with PII redaction
- Performance tracing (20% sample rate)
- 404 errors ignored
- Custom before_send callback for data sanitization

**PII Redaction**: Automatically removes sensitive data like emails, phone numbers, and tokens from error reports.

### 2. Laravel Horizon

**Purpose**: Queue monitoring and management

**Access**: `http://localhost:8000/horizon` (Super Admin only)

**Configuration**:
- Queues: `default`, `emails`, `notifications`
- Recent jobs retention: 1 day
- Job tags for categorization

**Security**: Protected by Super Admin role authentication with testing environment bypass.

### 3. Structured JSON Logging

**Purpose**: Centralized logging with PII protection

**Channels**:
- `stack`: Default Laravel logging
- `cloudwatch`: JSON formatted for CloudWatch Logs

**PII Redaction**: Automatic masking of:
- Email addresses
- Phone numbers (10-digit and 12-digit)
- API tokens
- Credit card numbers
- Aadhaar numbers

**CloudWatch Configuration**:
```bash
# .env
LOG_CHANNEL=stack
AWS_REGION=ap-south-1
CW_LOG_GROUP=map-hms-api
CW_LOG_STREAM=api-default
```

### 4. Request ID Middleware

**Purpose**: Request tracing across services

**Features**:
- Generates UUIDv4 for each request
- Propagates to response headers (`X-Request-Id`)
- Adds to log context automatically
- Propagates to Sentry scope and queue jobs

**Usage**: Automatically applied to all requests.

### 5. Health Check Endpoint

**Purpose**: Application health monitoring

**Endpoint**: `GET /healthz`

**Response Format**:
```json
{
  "ok": true,
  "checks": {
    "db": "ok",
    "cache": "ok", 
    "queue": "ok"
  },
  "version": {
    "app": "v1.0",
    "git": "abc1234"
  },
  "time": "2025-10-01T18:57:19.798688+05:30"
}
```

**Checks**:
- Database connectivity (`SELECT 1`)
- Cache put/get cycle
- Queue connection status

### 6. Custom Metrics

**Purpose**: Business metrics collection

**Service**: `App\Services\Metrics\Metrics`

**Configuration**:
```bash
# .env
AWS_REGION=ap-south-1
CW_METRICS_NAMESPACE=MAP-HMS
```

**Available Metrics**:
- `TicketCreated`: New ticket creation
- `TicketResolved`: Ticket resolution/closure
- `GateIn`: Student entry with late tracking
- `GateOut`: Student exit
- `AttendanceClosed`: Attendance session closure
- `ChecklistSubmitted`: Daily checklist completion

**Dimensions**: All metrics include `tenant_id`, `hostel_id`, `environment` dimensions.

**Implementation**: Currently logs metrics (v1.0), ready for CloudWatch API integration.

## Usage

### Running Observability Tests

```bash
make observability
```

This command:
1. Runs all observability-related tests
2. Optimizes the application
3. Tests the health endpoint

### Starting Horizon

```bash
make horizon
```

This command:
1. Installs Horizon configuration
2. Starts the Horizon process
3. Provides access URL

### Manual Health Check

```bash
curl http://localhost:8000/healthz | jq .
```

### Viewing Logs

```bash
# Laravel logs
tail -f api/storage/logs/laravel.log

# CloudWatch logs (when configured)
# Use AWS CLI or CloudWatch console
```

## Monitoring Best Practices

### 1. Error Monitoring
- Monitor Sentry dashboard for error trends
- Set up alerts for critical errors
- Review PII redaction effectiveness

### 2. Performance Monitoring
- Track response times in Sentry
- Monitor queue processing times in Horizon
- Watch for memory usage patterns

### 3. Business Metrics
- Track ticket creation/resolution rates
- Monitor gate entry/exit patterns
- Watch attendance completion rates

### 4. Health Monitoring
- Set up health check monitoring
- Alert on health check failures
- Monitor response times

## Troubleshooting

### Common Issues

1. **Sentry not capturing errors**
   - Check DSN configuration
   - Verify environment settings
   - Check PII redaction logs

2. **Horizon not accessible**
   - Verify Super Admin role
   - Check authentication middleware
   - Confirm Redis connection

3. **Metrics not appearing**
   - Check AWS configuration
   - Verify namespace settings
   - Review log output

4. **Health check failures**
   - Check database connectivity
   - Verify cache configuration
   - Test queue connection

### Debug Commands

```bash
# Test Sentry
php artisan tinker
\Sentry\captureException(new \Exception('Test'));

# Test metrics
php artisan tinker
\App\Services\Metrics\Metrics::count('TestMetric', 1);

# Test health check
curl -v http://localhost:8000/healthz
```

## Future Enhancements

### v1.1 Planned Features
- Real CloudWatch API integration for metrics
- Advanced alerting rules
- Custom dashboard creation
- Performance profiling integration
- Log aggregation improvements

### v1.2 Planned Features
- Distributed tracing
- Advanced metrics visualization
- Automated anomaly detection
- Custom monitoring dashboards

## Security Considerations

- All PII is automatically redacted from logs and error reports
- Horizon access is restricted to Super Admin role
- Request IDs are UUIDs (non-sequential)
- Metrics include only business-relevant dimensions
- Health checks don't expose sensitive information

## Support

For observability-related issues:
1. Check application logs
2. Review Sentry dashboard
3. Test health endpoint
4. Verify configuration
5. Contact development team

---

*Last updated: October 2025*
*Version: 1.0*
