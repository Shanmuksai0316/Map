# Build and Install Student App APK
# This script builds the student app APK and installs it on connected device

Write-Host "🔍 Checking for connected devices..." -ForegroundColor Cyan
$devices = adb devices
if ($devices -notmatch "device$") {
    Write-Host "❌ No Android device connected!" -ForegroundColor Red
    Write-Host "Please connect your device via USB and enable USB debugging" -ForegroundColor Yellow
    exit 1
}

Write-Host "✅ Device found!" -ForegroundColor Green

Write-Host "`n📦 Building Student App APK..." -ForegroundColor Cyan
Write-Host "This may take a few minutes..." -ForegroundColor Yellow

# Try using npm script first (recommended)
if (Test-Path "package.json") {
    Write-Host "Using npm script to build..." -ForegroundColor Cyan
    npm run android:student
} else {
    Write-Host "Using Gradle directly..." -ForegroundColor Cyan
    cd android
    .\gradlew.bat assembleStudentProductionDebug
    cd ..
}

# Find the APK
$apkPath = Get-ChildItem -Path "android\app\build\outputs\apk\studentProduction\debug" -Filter "*.apk" -Recurse | Select-Object -First 1

if (-not $apkPath) {
    Write-Host "❌ APK not found! Build may have failed." -ForegroundColor Red
    Write-Host "Please check the build output above for errors." -ForegroundColor Yellow
    exit 1
}

Write-Host "`n✅ APK built successfully: $($apkPath.FullName)" -ForegroundColor Green

Write-Host "`n📱 Installing APK on device..." -ForegroundColor Cyan
adb install -r $apkPath.FullName

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n✅ App installed successfully!" -ForegroundColor Green
    Write-Host "`n🚀 You can now run Maestro tests:" -ForegroundColor Cyan
    Write-Host "   cd maestro" -ForegroundColor Yellow
    Write-Host "   maestro test student/00-student-complete-test.yaml" -ForegroundColor Yellow
} else {
    Write-Host "`n❌ Installation failed!" -ForegroundColor Red
    Write-Host "Please check the error message above." -ForegroundColor Yellow
    exit 1
}
