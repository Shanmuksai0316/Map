# 🚨 Incident Response Playbook

How to handle incidents in MAP-HMS production.

---

## Severity Levels

| Level | Description | Response Time | Examples |
|-------|-------------|---------------|----------|
| **P0** | Complete outage | < 15 min | Site down, DB corruption |
| **P1** | Major functionality broken | < 1 hour | Login broken, payments failing |
| **P2** | Minor functionality affected | < 4 hours | Reports slow, minor UI bugs |
| **P3** | Cosmetic/Low impact | < 24 hours | Typos, styling issues |

---

## P0: Complete Outage

### Symptoms
- Health endpoint returns error
- Users cannot access site
- All API calls failing

### Immediate Actions (< 15 min)

```bash
# 1. Verify outage
curl -v https://api.mapservices.in/healthz

# 2. SSH to server
ssh -i hostinger.pem root@72.62.79.173

# 3. Check all containers
docker ps -a

# 4. Check recent logs
docker logs --tail 100 map-hms-app

# 5. Quick restart attempt
docker restart map-hms-app
sleep 10
curl https://api.mapservices.in/healthz
```

### If Restart Doesn't Help

```bash
# Check disk space
df -h

# Check memory
free -h

# Check if port is listening
netstat -tlnp | grep 80

# Check Traefik
docker logs --tail 50 coolify-proxy

# Full stack restart
docker restart map-hms-app map-hms-db map-hms-redis coolify-proxy
```

### If Database Issue

```bash
# Check PostgreSQL
docker exec map-hms-db pg_isready

# Check connections
docker exec map-hms-db psql -U maphms -c "SELECT count(*) FROM pg_stat_activity;"

# Kill idle connections
docker exec map-hms-db psql -U maphms -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE state = 'idle' AND query_start < now() - interval '1 hour';"

# Restart database
docker restart map-hms-db
sleep 10
docker restart map-hms-app
```

---

## P1: Login/Auth Issues

### Symptoms
- Users cannot login
- OTP not sending
- Session errors

### Diagnosis

```bash
# Check auth logs
docker exec map-hms-app grep -i "auth\|login\|otp" storage/logs/laravel.log | tail -50

# Check Redis (sessions)
docker exec map-hms-redis redis-cli PING

# Check MSG91 balance (SMS)
# Check external dashboard
```

### Resolution

```bash
# Clear session cache
docker exec map-hms-app php artisan cache:clear

# Restart Redis
docker restart map-hms-redis

# Check SMS config
docker exec map-hms-app php artisan tinker --execute="
dump(config('services.msg91'));
"
```

---

## P1: Database Performance

### Symptoms
- Slow page loads (> 5s)
- Timeouts on API calls
- High CPU on database

### Diagnosis

```bash
# Check active queries
docker exec map-hms-db psql -U maphms -c "SELECT pid, now() - pg_stat_activity.query_start AS duration, query FROM pg_stat_activity WHERE state = 'active' ORDER BY duration DESC LIMIT 10;"

# Check locks
docker exec map-hms-db psql -U maphms -c "SELECT * FROM pg_locks WHERE NOT granted;"

# Check table sizes
docker exec map-hms-db psql -U maphms -c "SELECT relname, pg_size_pretty(pg_total_relation_size(relid)) AS size FROM pg_catalog.pg_statio_user_tables ORDER BY pg_total_relation_size(relid) DESC LIMIT 10;"
```

### Resolution

```bash
# Kill long-running queries
docker exec map-hms-db psql -U maphms -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE state = 'active' AND query_start < now() - interval '5 minutes';"

# Analyze tables
docker exec map-hms-db psql -U maphms -c "ANALYZE;"

# Vacuum if needed
docker exec map-hms-db psql -U maphms -c "VACUUM ANALYZE;"
```

---

## P2: Queue Backup

### Symptoms
- Delayed notifications
- Jobs not processing
- Horizon showing pending jobs

### Diagnosis

```bash
# Check queue length
docker exec map-hms-app php artisan queue:monitor default --max=100

# Check failed jobs
docker exec map-hms-app php artisan queue:failed

# Check Horizon status
docker exec map-hms-app php artisan horizon:status
```

### Resolution

```bash
# Retry failed jobs
docker exec map-hms-app php artisan queue:retry all

# Clear failed jobs
docker exec map-hms-app php artisan queue:flush

# Restart queue workers
docker exec map-hms-app php artisan queue:restart
```

---

## SSL Certificate Expired

### Symptoms
- Browser shows "Not Secure"
- SSL handshake errors
- HTTPS not working

### For Production (Cloudflare)

1. Login to Cloudflare Dashboard
2. Check SSL/TLS → Edge Certificates
3. Verify Universal SSL is active
4. Check for any warnings

### For Staging (Let's Encrypt)

```bash
# Check certificate expiry
openssl s_client -connect staging.mapservices.in:443 -servername staging.mapservices.in 2>/dev/null | openssl x509 -noout -dates

# Force renewal
docker restart coolify-proxy
sleep 30

# Check ACME log
docker logs coolify-proxy 2>&1 | grep -i "acme\|certificate"
```

---

## Data Recovery

### Restore from Backup

```bash
# Stop application
docker stop map-hms-app

# Restore database
cat backup_20260103.sql | docker exec -i map-hms-db psql -U maphms -d maphms

# Start application
docker start map-hms-app

# Verify
curl https://api.mapservices.in/healthz
```

### Point-in-Time Recovery

```bash
# This requires WAL archiving to be enabled
# Contact database admin for PITR setup
```

---

## Communication Template

### Internal Alert

```
🚨 INCIDENT: [P0/P1/P2] - [Brief Description]

Time Detected: [HH:MM UTC]
Affected: [Production/Staging]
Impact: [Description]

Status: [Investigating/Identified/Mitigating/Resolved]

Current Actions:
- [Action 1]
- [Action 2]

ETA: [Estimated resolution time]
```

### Status Update

```
📊 INCIDENT UPDATE: [Brief Description]

Status: [Investigating/Identified/Mitigating/Resolved]
Duration: [X minutes/hours]

Update:
[What changed since last update]

Next Steps:
[What we're doing next]

ETA: [Updated estimate]
```

### Resolution

```
✅ INCIDENT RESOLVED: [Brief Description]

Duration: [Total time]
Root Cause: [Brief explanation]
Resolution: [What fixed it]

Follow-up Actions:
- [Post-mortem scheduled for X]
- [Preventive measures]
```

---

## Post-Incident

### After Every P0/P1

1. **Document the incident**
   - Timeline of events
   - Root cause
   - Resolution steps
   - What worked/didn't work

2. **Schedule post-mortem**
   - Within 48 hours for P0
   - Within 1 week for P1

3. **Create action items**
   - Preventive measures
   - Monitoring improvements
   - Documentation updates

---

*Last Updated: January 2026*

