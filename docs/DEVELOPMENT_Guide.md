# MAP-HMS Development Guide

Comprehensive guide for developers working on the MAP-HMS codebase.

## Repository Layout

```
MAP/
├── api/                     # Laravel API backend
│   ├── app/
│   │   ├── Domain/         # Business logic modules
│   │   ├── Http/           # Controllers, Requests, Resources
│   │   ├── Models/         # Eloquent models
│   │   ├── Policies/       # Authorization policies
│   │   ├── Jobs/           # Background jobs
│   │   ├── Services/       # Business services
│   │   └── Filament/       # Admin panel resources
│   ├── database/           # Migrations, seeders, factories
│   ├── tests/              # Pest test suites
│   └── config/             # Configuration files
├── mobile/                 # React Native mobile apps
│   ├── src/
│   │   ├── screens/        # App screens
│   │   ├── navigation/     # Navigation setup
│   │   ├── store/          # Zustand state management
│   │   ├── api/            # API client
│   │   └── components/     # Reusable components
│   └── android/ios/        # Platform-specific code
├── docs/                   # Documentation
└── scripts/                # Utility scripts
```

## Development Conventions

### PHP/Laravel Standards

#### Code Style
- **PSR-12**: Follow PSR-12 coding standards
- **Laravel Pint**: Automated code formatting
- **PHPStan**: Static analysis (level 8)
- **Conventional Commits**: Use conventional commit messages

#### Naming Conventions
```php
// Classes: PascalCase
class OutPassController {}

// Methods: camelCase
public function createOutPass() {}

// Variables: camelCase
$tenantId = $user->tenant_id;

// Constants: UPPER_SNAKE_CASE
const MAX_OUT_PASS_DURATION = 24;
```

#### File Organization
```php
// Controller structure
<?php
/**
 * Module: OutPass
 * Purpose: Handle student out-pass requests
 * Key routes: POST /outpasses, GET /outpasses
 * Policies: OutPassPolicy@create, OutPassPolicy@view
 * @tenant-scope: tenant_id enforced via policy
 * Owner: MAP Co-Pilot
 */

namespace App\Http\Controllers\Api\V1;

class OutPassController extends Controller
{
    /**
     * Create a new out-pass request
     * 
     * @param StoreOutPassRequest $request
     * @return JsonResponse
     * @tenant-scope: Automatically scoped to user's tenant
     */
    public function store(StoreOutPassRequest $request): JsonResponse
    {
        // @feature-flag: outpass_module
        // @policy: OutPassPolicy@create
        // @audit: OutPassCreated event logged
    }
}
```

### TypeScript/React Native Standards

#### Code Style
- **TypeScript**: Strict mode enabled
- **ESLint**: Airbnb configuration
- **Prettier**: Automated formatting
- **Conventional Commits**: Use conventional commit messages

#### Component Structure
```typescript
/**
 * Component: OutPassCard
 * Purpose: Display out-pass request information
 * Navigation params: None (display component)
 * API deps: None (receives data via props)
 * Offline behavior: Works with cached data
 */

import React from 'react';
import { View, Text } from 'react-native';

interface OutPassCardProps {
  outPass: OutPass;
  onPress?: () => void;
}

export function OutPassCard({ outPass, onPress }: OutPassCardProps): JSX.Element {
  // Component implementation
}
```

#### Store Structure
```typescript
/**
 * Store: OutPassStore
 * State shape: { outpasses: OutPass[], loading: boolean, error?: string }
 * Actions: fetchOutPasses, createOutPass, updateStatus
 * Persistence: Cached locally, synced on reconnect
 */

interface OutPassState {
  outpasses: OutPass[];
  loading: boolean;
  error?: string;
  
  // Actions
  fetchOutPasses: () => Promise<void>;
  createOutPass: (data: CreateOutPassData) => Promise<void>;
  updateStatus: (id: string, status: OutPassStatus) => Promise<void>;
}
```

## Development Commands

### API Development

```bash
# Start development server
cd api
php artisan serve

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed

# Run tests
vendor/bin/pest

# Code formatting
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Generate API documentation
php artisan l5-swagger:generate

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Queue management
php artisan queue:work
php artisan horizon
```

### Mobile Development

```bash
# Install dependencies
cd mobile
npm install

# Start Metro bundler
npm start

# Run on Android
npm run android

# Run on iOS
npm run ios

# Run tests
npm test

# Type checking
npm run type-check

# Linting
npm run lint

# Format code
npm run format
```

### Database Management

```bash
# Create migration
php artisan make:migration create_outpasses_table

# Create model with migration
php artisan make:model OutPass -m

# Create seeder
php artisan make:seeder OutPassSeeder

# Create factory
php artisan make:factory OutPassFactory

# Reset database
php artisan migrate:fresh --seed

# Database backup
php artisan db:backup
```

## Testing Strategy

### API Testing (Pest)

```php
// Feature tests
test('student can create out-pass request', function () {
    $student = Student::factory()->create();
    
    $response = $this->actingAs($student->user)
        ->postJson('/api/v1/outpasses', [
            'reason' => 'Medical appointment',
            'requested_at' => now()->addHour(),
            'valid_until' => now()->addHours(4),
        ]);
    
    $response->assertCreated();
    $this->assertDatabaseHas('out_passes', [
        'student_id' => $student->id,
        'reason' => 'Medical appointment',
    ]);
});

// Policy tests
test('student cannot view other tenants outpasses', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $student1 = Student::factory()->for($tenant1)->create();
    $student2 = Student::factory()->for($tenant2)->create();
    $outpass = OutPass::factory()->for($student2)->create();
    
    $this->actingAs($student1->user)
        ->getJson("/api/v1/outpasses/{$outpass->id}")
        ->assertForbidden();
});
```

### Mobile Testing (Jest)

```typescript
// Unit tests
describe('OutPassStore', () => {
  it('should create outpass successfully', async () => {
    const store = useOutPassStore.getState();
    const mockData = { reason: 'Medical', requested_at: '2024-01-01' };
    
    await store.createOutPass(mockData);
    
    expect(store.outpasses).toHaveLength(1);
    expect(store.outpasses[0].reason).toBe('Medical');
  });
});

// Component tests
describe('OutPassCard', () => {
  it('should render outpass information', () => {
    const outpass = { id: '1', reason: 'Medical', status: 'pending' };
    
    render(<OutPassCard outPass={outpass} />);
    
    expect(screen.getByText('Medical')).toBeOnTheScreen();
    expect(screen.getByText('Pending')).toBeOnTheScreen();
  });
});
```

## Feature Flags

### Configuration

```php
// config/features.php
return [
    'laundry_module' => env('FEATURE_LAUNDRY', false),
    'sports_module' => env('FEATURE_SPORTS', false),
    'checklists_module' => env('FEATURE_CHECKLISTS', true),
    'tickets_module' => env('FEATURE_TICKETS', true),
];
```

### Usage

```php
// In controllers
public function index(Request $request): JsonResponse
{
    abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);
    
    // Module logic
}

// In policies
public function viewAny(User $user): bool
{
    return Feature::isEnabled('laundry_module') && 
           $user->can('view_laundry_requests');
}
```

## Multi-Tenancy

### Global Scopes

```php
// Model with tenant scope
class OutPass extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }
}

// Manual tenant filtering
OutPass::query()
    ->where('tenant_id', $user->tenant_id)
    ->get();
```

### Policy Enforcement

```php
// Tenant-aware policy
class OutPassPolicy
{
    public function view(User $user, OutPass $outPass): bool
    {
        return $user->tenant_id === $outPass->tenant_id &&
               $user->can('view_outpasses');
    }
}
```

## Background Jobs

### Job Creation

```php
// Create job
php artisan make:job ProcessOutPassApproval

// Job implementation
class ProcessOutPassApproval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        private OutPass $outPass,
        private User $approver
    ) {}
    
    public function handle(): void
    {
        // Process approval logic
        // Send notifications
        // Update status
    }
}
```

### Job Dispatch

```php
// Dispatch job
ProcessOutPassApproval::dispatch($outPass, $user);

// Dispatch with delay
ProcessOutPassApproval::dispatch($outPass, $user)
    ->delay(now()->addMinutes(5));
```

## API Documentation

### OpenAPI Annotations

```php
/**
 * @OA\Post(
 *     path="/api/v1/outpasses",
 *     summary="Create out-pass request",
 *     tags={"OutPasses"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="reason", type="string"),
 *             @OA\Property(property="requested_at", type="string", format="date-time"),
 *             @OA\Property(property="valid_until", type="string", format="date-time")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Out-pass created successfully"
 *     )
 * )
 */
public function store(StoreOutPassRequest $request): JsonResponse
{
    // Implementation
}
```

## Performance Optimization

### Database Queries

```php
// Eager loading to prevent N+1
$outpasses = OutPass::with(['student', 'approver'])
    ->where('tenant_id', $tenantId)
    ->get();

// Query optimization with indexes
OutPass::query()
    ->where('tenant_id', $tenantId)  // Indexed
    ->where('status', 'pending')     // Indexed
    ->orderBy('created_at', 'desc')  // Indexed
    ->get();
```

### Caching

```php
// Cache expensive operations
$dashboardData = Cache::remember(
    "dashboard_data_{$tenantId}",
    now()->addMinutes(15),
    fn() => $this->calculateDashboardData($tenantId)
);
```

## Debugging

### Laravel Debugging

```php
// Debug queries
DB::enableQueryLog();
$results = OutPass::with('student')->get();
dd(DB::getQueryLog());

// Debug with Ray (if installed)
ray($outpass, $user);

// Log debugging info
Log::info('OutPass created', [
    'outpass_id' => $outpass->id,
    'student_id' => $student->id,
    'tenant_id' => $tenant->id
]);
```

### Mobile Debugging

```typescript
// React Native debugging
console.log('OutPass data:', outpass);

// Flipper debugging
import { flipperNetworkPlugin } from 'react-native-flipper';
```

## Git Workflow

### Branch Naming

```bash
# Feature branches
feature/outpass-approval-workflow
feature/mobile-offline-sync

# Bug fixes
bugfix/ticket-attachment-upload
bugfix/gate-device-timeout

# Documentation
docs/api-documentation-update
docs/mobile-setup-guide

# Chores
chore/update-dependencies
chore/refactor-auth-service
```

### Commit Messages

```bash
# Conventional commits
feat(api): add outpass approval workflow
fix(mobile): resolve offline sync issue
docs(readme): update installation guide
chore(deps): update laravel to 11.0
test(outpass): add approval policy tests
```

---

*Development guide version: v1.0*
*Owner: MAP Co-Pilot*
