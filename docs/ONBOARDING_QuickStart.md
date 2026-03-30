# MAP-HMS Quick Start Guide

Get up and running with MAP-HMS development in 60 minutes.

## Prerequisites

### Required Software
- **PHP 8.2+** with extensions: `php-sqlite3`, `php-curl`, `php-zip`, `php-xml`
- **Composer** 2.x
- **Node.js** 18+ and **npm** 9+
- **Git**
- **SQLite** (included with PHP)
- **Android Studio** (for mobile development)
- **Xcode** (for iOS development, macOS only)

### Optional but Recommended
- **Laravel Valet** or **Docker Desktop**
- **VS Code** with PHP, TypeScript extensions
- **Postman** or **Insomnia** for API testing

## Step 1: Repository Setup (5 minutes)

```bash
# Clone and navigate
git clone <repository-url>
cd MAP

# Install PHP dependencies
cd api
composer install

# Install Node dependencies for mobile
cd ../mobile
npm install
```

## Step 2: Environment Configuration (10 minutes)

### API Configuration
```bash
# Copy and configure API environment
cd api
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database (SQLite by default)
touch database/database.sqlite
```

### Mobile Configuration
```bash
# Copy mobile config
cd ../mobile
cp app.config.sample.json app.config.json
```

## Step 3: Database Setup (5 minutes)

```bash
# Run migrations and seeders
cd api
php artisan migrate
php artisan db:seed

# Create demo tenant (optional)
php artisan make:demo-tenant
```

## Step 4: Start Development Servers (10 minutes)

### Terminal 1: API Server
```bash
cd api
php artisan serve
# API available at http://localhost:8000
```

### Terminal 2: Mobile Development
```bash
cd mobile

# For Android
npx react-native run-android

# For iOS (macOS only)
npx react-native run-ios
```

### Terminal 3: Queue Worker (Optional)
```bash
cd api
php artisan queue:work
```

## Step 5: Demo Credentials (5 minutes)

### API Admin Panel
- **URL**: http://localhost:8000/admin
- **Email**: `admin@demo.com`
- **Password**: `password`

### Mobile App Login
- **Student Email**: `student1@demo.com`
- **Student Password**: `password`
- **Guard Email**: `guard1@demo.com`
- **Guard Password**: `password`

### API Testing
```bash
# Test API health
curl http://localhost:8000/api/health

# Test authentication
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"student1@demo.com","password":"password","device_name":"test"}'
```

## Step 6: Verify Installation (5 minutes)

### Check API Health
```bash
cd api
php artisan route:list
php artisan tinker
>>> App\Models\User::count()
```

### Check Mobile App
- Launch app on device/emulator
- Login with demo credentials
- Navigate through screens
- Test offline functionality

### Run Tests
```bash
# API tests
cd api
vendor/bin/pest

# Mobile tests
cd ../mobile
npm test
```

## Step 7: Development Tools Setup (15 minutes)

### VS Code Extensions
```json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "ms-vscode.vscode-typescript-next",
    "bradlc.vscode-tailwindcss",
    "ms-vscode.vscode-json"
  ]
}
```

### Git Hooks (Optional)
```bash
# Install pre-commit hooks
cd api
composer require --dev brianium/paratest
```

### Postman Collection
- Import `docs/postman/MAP-HMS_Demo.postman_collection.json`
- Set environment variables for local development

## Common Issues & Solutions

### Issue: "SQLite database not found"
```bash
cd api
touch database/database.sqlite
php artisan migrate
```

### Issue: "Mobile app won't connect to API"
- Check `mobile/app.config.json` has correct `API_BASE` URL
- Ensure API server is running on correct port
- For Android emulator, use `http://10.0.2.2:8000`

### Issue: "Composer dependencies fail"
```bash
cd api
composer install --ignore-platform-reqs
```

### Issue: "Node modules issues"
```bash
cd mobile
rm -rf node_modules package-lock.json
npm install
```

## Next Steps

1. **Explore the Codebase**: Read [ARCHITECTURE_Overview.md](./ARCHITECTURE_Overview.md)
2. **Understand Development**: Follow [DEVELOPMENT_Guide.md](./DEVELOPMENT_Guide.md)
3. **Learn Security**: Review [SECURITY_Practices.md](./SECURITY_Practices.md)
4. **Run Demo Scenarios**: See [DemoScenarios_v1.3.md](../docs/demo/DemoScenarios_v1.3.md)

## Getting Help

- **Documentation**: Browse this knowledge base
- **FAQ**: Check [KB/FAQ.md](./KB/FAQ.md)
- **Troubleshooting**: See [KB/Troubleshooting.md](./KB/Troubleshooting.md)
- **Code Issues**: Review existing tests and policies

---

*Setup time: ~60 minutes*
*Owner: MAP Co-Pilot*
