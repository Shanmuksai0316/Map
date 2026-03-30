# Deploy Laundry Controller Fix to Server
# This script deploys the fixed LaundryController.php directly to the server without git

$ErrorActionPreference = "Stop"

# Configuration
$PEM_FILE = "hostinger\hostinger.pem"
$SERVER_USER = "root"
$SERVER_HOST = "72.62.79.173"
$DOCKER_CONTAINER = "map-hms-app"
$PROJECT_PATH = "/var/www/html"

Write-Host "Deploying Laundry Controller Fix..." -ForegroundColor Blue
Write-Host ""

# Function to run command on server
function Run-Remote {
    param([string]$Command)
    ssh -i $PEM_FILE -o StrictHostKeyChecking=no "${SERVER_USER}@${SERVER_HOST}" $Command
}

# Function to run command in Docker
function Run-Docker {
    param([string]$Command)
    ssh -i $PEM_FILE -o StrictHostKeyChecking=no "${SERVER_USER}@${SERVER_HOST}" "docker exec $DOCKER_CONTAINER $Command"
}

# Function to copy file to server
function Copy-FileToServer {
    param(
        [string]$LocalFile,
        [string]$RemotePath
    )
    $fileName = Split-Path $LocalFile -Leaf
    Write-Host "Copying $fileName to $RemotePath" -ForegroundColor Yellow
    
    # Copy to temp on server
    scp -i $PEM_FILE -o StrictHostKeyChecking=no $LocalFile "${SERVER_USER}@${SERVER_HOST}:/tmp/$fileName"
    
    # Copy from temp to Docker container
    Run-Remote "docker cp /tmp/$fileName ${DOCKER_CONTAINER}:${RemotePath}"
    
    # Clean up temp file
    Run-Remote "rm /tmp/$fileName"
}

# Test connection
Write-Host "Testing connection..." -ForegroundColor Blue
try {
    Run-Remote "echo 'Connected'" | Out-Null
    Write-Host "✓ Connected" -ForegroundColor Green
} catch {
    Write-Host "Connection failed" -ForegroundColor Red
    exit 1
}
Write-Host ""

# Deploy LaundryController.php
Write-Host "Deploying LaundryController.php..." -ForegroundColor Blue

$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$localFile = Join-Path $scriptPath "api\app\Http\Controllers\Api\V1\Mobile\LaundryController.php"

Copy-FileToServer `
    $localFile `
    "$PROJECT_PATH/app/Http/Controllers/Api/V1/Mobile/LaundryController.php"

Write-Host "✓ File deployed" -ForegroundColor Green
Write-Host ""

# Clear caches
Write-Host "Clearing caches..." -ForegroundColor Blue
Run-Docker "php artisan config:clear"
Run-Docker "php artisan route:clear"
Run-Docker "php artisan view:clear"
Run-Docker "php artisan cache:clear"
Write-Host "✓ Caches cleared" -ForegroundColor Green
Write-Host ""

# Restart queue workers (if any)
Write-Host "Restarting queue workers..." -ForegroundColor Blue
Run-Docker "php artisan queue:restart" 2>$null
Write-Host "✓ Queue workers restarted" -ForegroundColor Green
Write-Host ""

Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "  ✅ Deployment Complete!" -ForegroundColor Green
Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""
Write-Host "The Laundry Controller fix has been deployed." -ForegroundColor Cyan
Write-Host "Student search should now work with case-insensitive matching." -ForegroundColor Cyan
Write-Host ""
