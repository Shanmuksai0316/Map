#!/usr/bin/env bash
set -e

# Seed outpass, guest entry, and checklist data on the Hostinger server.
# Usage: ./scripts/seed-server-demo-data.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PEM="$PROJECT_DIR/hostinger.pem"
SERVER="root@72.62.79.173"
CONTAINER="map-hms-app"
# App path inside container (or on host if bind-mounted)
API_PATH="/var/www/html"

if [[ ! -f "$PEM" ]]; then
  echo "❌ PEM key not found at $PEM"
  exit 1
fi

chmod 600 "$PEM" 2>/dev/null || true

echo "📤 Copying SeedStxavDemoData command to server..."
scp -i "$PEM" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 \
  "$PROJECT_DIR/api/app/Console/Commands/SeedStxavDemoData.php" \
  "$SERVER:/tmp/SeedStxavDemoData.php"

echo ""
echo "🔐 Pushing command into container and running seeds..."

ssh -i "$PEM" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 "$SERVER" bash -s << 'REMOTE'
set -e
# Copy into container (Coolify uses /var/www/html)
docker cp /tmp/SeedStxavDemoData.php map-hms-app:/var/www/html/app/Console/Commands/ 2>/dev/null || \
docker cp /tmp/SeedStxavDemoData.php map-hms-app:/app/app/Console/Commands/ 2>/dev/null || true

# Try STXAV first (demo), then MAP-PPCU (common tenant on server)
for TENANT in STXAV MAP-PPCU MAP-DEMO-COLLEGE MAP-NITK; do
  echo "📋 Seeding Outpass, Leave, Guest Entry for $TENANT..."
  if docker exec map-hms-app php artisan seed:stxav-demo --count=5 --tenant="$TENANT" 2>/dev/null; then
    echo "  ✓ Demo data seeded for $TENANT"
    break
  fi
done

echo ""
echo "📋 Seeding checklist templates..."
docker exec map-hms-app php artisan db:seed --class=ProductionChecklistsSeeder --force 2>/dev/null || true

echo ""
echo "✅ Done."
REMOTE

echo ""
echo "Done. Refresh the staff app to see new data."
