# PowerShell script to fully reset Metro bundler cache
# Usage: .\reset_metro.ps1

Write-Host "`n=== Resetting Metro Bundler Cache ===" -ForegroundColor Cyan

$cachePaths = @(
    "node_modules\.cache",
    ".metro",
    "android\.gradle",
    "android\app\build",
    "ios\build"
)

foreach ($path in $cachePaths) {
    if (Test-Path $path) {
        Write-Host "Removing: $path" -ForegroundColor Yellow
        Remove-Item -Recurse -Force $path -ErrorAction SilentlyContinue
    }
}

Write-Host "`n=== Clearing watchman cache ===" -ForegroundColor Cyan
if (Get-Command watchman -ErrorAction SilentlyContinue) {
    watchman watch-del-all 2>$null
}

Write-Host "`n=== Done! ===" -ForegroundColor Green
Write-Host "Now restart Metro with: npm start -- --reset-cache" -ForegroundColor Cyan
