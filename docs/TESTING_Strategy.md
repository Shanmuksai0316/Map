# MAP-HMS Testing Strategy

Comprehensive testing approach for MAP-HMS development.

## Testing Philosophy

MAP-HMS follows a **test-driven development** approach with comprehensive coverage across all layers:

- **Unit Tests**: Individual components and services
- **Feature Tests**: API endpoints and business logic
- **Integration Tests**: External service integrations
- **Mobile Tests**: React Native components and stores
- **End-to-End Tests**: Complete user workflows

## API Testing (Pest)

### Test Structure
```php
// tests/Feature/OutPassTest.php
<?php

use App\Models\OutPass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;

describe('OutPass API', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->student = Student::factory()->for($this->tenant)->create();
        $this->user = $this->student->user;
    });

    describe('POST /api/v1/outpasses', function () {
        it('allows student to create outpass request', function () {
            $data = [
                'reason' => 'Medical appointment',
                'requested_at' => now()->addHour()->toISOString(),
                'valid_until' => now()->addHours(4)->toISOString(),
                'type' => 'medical',
            ];

            $response = $this->actingAs($this->user)
                ->postJson('/api/v1/outpasses', $data);

            $response->assertCreated()
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'reason',
                        'status',
                        'requested_at',
                        'valid_until',
                    ]
                ]);

            $this->assertDatabaseHas('out_passes', [
                'student_id' => $this->student->id,
                'reason' => 'Medical appointment',
                'status' => 'pending',
            ]);
        });

        it('validates required fields', function () {
            $response = $this->actingAs($this->user)
                ->postJson('/api/v1/outpasses', []);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['reason', 'requested_at', 'valid_until']);
        });

        it('enforces tenant isolation', function () {
            $otherTenant = Tenant::factory()->create();
            $otherStudent = Student::factory()->for($otherTenant)->create();
            
            $data = [
                'reason' => 'Test reason',
                'requested_at' => now()->addHour()->toISOString(),
                'valid_until' => now()->addHours(4)->toISOString(),
                'type' => 'emergency',
            ];

            $response = $this->actingAs($this->user)
                ->postJson('/api/v1/outpasses', $data);

            // Should create outpass for current tenant, not other tenant
            $this->assertDatabaseHas('out_passes', [
                'tenant_id' => $this->tenant->id,
                'student_id' => $this->student->id,
            ]);

            $this->assertDatabaseMissing('out_passes', [
                'tenant_id' => $otherTenant->id,
                'student_id' => $otherStudent->id,
            ]);
        });
    });

    describe('GET /api/v1/outpasses', function () {
        it('returns only tenant outpasses', function () {
            // Create outpasses for current tenant
            OutPass::factory()->count(3)->for($this->student)->create();
            
            // Create outpass for other tenant
            $otherTenant = Tenant::factory()->create();
            $otherStudent = Student::factory()->for($otherTenant)->create();
            OutPass::factory()->for($otherStudent)->create();

            $response = $this->actingAs($this->user)
                ->getJson('/api/v1/outpasses');

            $response->assertOk()
                ->assertJsonCount(3, 'data');
        });
    });
});
```

### Policy Testing
```php
// tests/Unit/OutPassPolicyTest.php
<?php

use App\Models\OutPass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\OutPassPolicy;

describe('OutPassPolicy', function () {
    beforeEach(function () {
        $this->policy = new OutPassPolicy();
        $this->tenant = Tenant::factory()->create();
        $this->student = Student::factory()->for($this->tenant)->create();
        $this->outpass = OutPass::factory()->for($this->student)->create();
    });

    it('allows student to view their own outpass', function () {
        expect($this->policy->view($this->student->user, $this->outpass))
            ->toBeTrue();
    });

    it('prevents student from viewing other tenant outpass', function () {
        $otherTenant = Tenant::factory()->create();
        $otherStudent = Student::factory()->for($otherTenant)->create();
        $otherOutpass = OutPass::factory()->for($otherStudent)->create();

        expect($this->policy->view($this->student->user, $otherOutpass))
            ->toBeFalse();
    });

    it('allows campus manager to approve outpass', function () {
        $campusManager = User::factory()->for($this->tenant)->create();
        $campusManager->assignRole('campus_manager');

        expect($this->policy->approve($campusManager, $this->outpass))
            ->toBeTrue();
    });
});
```

### Service Testing
```php
// tests/Unit/OutPassServiceTest.php
<?php

use App\Models\OutPass;
use App\Models\Student;
use App\Services\OutPassService;

describe('OutPassService', function () {
    beforeEach(function () {
        $this->service = new OutPassService();
        $this->student = Student::factory()->create();
    });

    it('creates outpass with correct status', function () {
        $data = [
            'reason' => 'Medical appointment',
            'requested_at' => now()->addHour(),
            'valid_until' => now()->addHours(4),
            'type' => 'medical',
        ];

        $outpass = $this->service->create($this->student, $data);

        expect($outpass)
            ->toBeInstanceOf(OutPass::class)
            ->reason->toBe('Medical appointment')
            ->status->toBe('pending');
    });

    it('sends notification after creation', function () {
        Notification::fake();

        $data = [
            'reason' => 'Medical appointment',
            'requested_at' => now()->addHour(),
            'valid_until' => now()->addHours(4),
            'type' => 'medical',
        ];

        $this->service->create($this->student, $data);

        Notification::assertSentTo(
            $this->student->user,
            OutPassCreatedNotification::class
        );
    });
});
```

## Mobile Testing (Jest)

### Store Testing
```typescript
// __tests__/store/outpass.test.ts
import { useOutPassStore } from '../../src/store/outpass';
import { apiClient } from '../../src/api/client';

// Mock API client
jest.mock('../../src/api/client');
const mockedApiClient = apiClient as jest.Mocked<typeof apiClient>;

describe('OutPassStore', () => {
  beforeEach(() => {
    // Reset store state
    useOutPassStore.getState().clearState();
    jest.clearAllMocks();
  });

  describe('fetchOutPasses', () => {
    it('should fetch and store outpasses successfully', async () => {
      const mockOutPasses = [
        {
          id: '1',
          reason: 'Medical appointment',
          status: 'pending',
          requested_at: '2024-01-01T10:00:00Z',
          valid_until: '2024-01-01T14:00:00Z',
        },
        {
          id: '2',
          reason: 'Emergency',
          status: 'approved',
          requested_at: '2024-01-01T11:00:00Z',
          valid_until: '2024-01-01T15:00:00Z',
        },
      ];

      mockedApiClient.get.mockResolvedValue({
        data: { data: mockOutPasses },
      });

      const store = useOutPassStore.getState();
      await store.fetchOutPasses();

      expect(store.outpasses).toEqual(mockOutPasses);
      expect(store.loading).toBe(false);
      expect(store.error).toBeUndefined();
    });

    it('should handle fetch errors', async () => {
      const errorMessage = 'Network error';
      mockedApiClient.get.mockRejectedValue(new Error(errorMessage));

      const store = useOutPassStore.getState();
      await store.fetchOutPasses();

      expect(store.error).toBe(errorMessage);
      expect(store.loading).toBe(false);
    });
  });

  describe('createOutPass', () => {
    it('should create outpass and add to list', async () => {
      const newOutPass = {
        reason: 'Medical appointment',
        requested_at: '2024-01-01T10:00:00Z',
        valid_until: '2024-01-01T14:00:00Z',
      };

      const createdOutPass = {
        id: '3',
        ...newOutPass,
        status: 'pending',
      };

      mockedApiClient.post.mockResolvedValue({
        data: { data: createdOutPass },
      });

      const store = useOutPassStore.getState();
      await store.createOutPass(newOutPass);

      expect(store.outpasses).toContain(createdOutPass);
      expect(store.loading).toBe(false);
    });

    it('should queue action when offline', async () => {
      // Mock offline state
      const store = useOutPassStore.getState();
      store.setOnlineStatus(false);

      const newOutPass = {
        reason: 'Medical appointment',
        requested_at: '2024-01-01T10:00:00Z',
        valid_until: '2024-01-01T14:00:00Z',
      };

      await store.createOutPass(newOutPass);

      expect(store.pendingActions).toHaveLength(1);
      expect(store.pendingActions[0].type).toBe('CREATE_OUTPASS');
    });
  });
});
```

### Component Testing
```typescript
// __tests__/components/OutPassCard.test.tsx
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { OutPassCard } from '../../src/components/outpass/OutPassCard';

const mockOutPass = {
  id: '1',
  reason: 'Medical appointment',
  status: 'pending',
  requested_at: '2024-01-01T10:00:00Z',
  valid_until: '2024-01-01T14:00:00Z',
};

describe('OutPassCard', () => {
  it('should render outpass information correctly', () => {
    render(<OutPassCard outPass={mockOutPass} />);

    expect(screen.getByText('Medical appointment')).toBeOnTheScreen();
    expect(screen.getByText('Pending')).toBeOnTheScreen();
    expect(screen.getByText('10:00 AM')).toBeOnTheScreen();
  });

  it('should call onPress when tapped', () => {
    const mockOnPress = jest.fn();
    render(<OutPassCard outPass={mockOutPass} onPress={mockOnPress} />);

    fireEvent.press(screen.getByText('Medical appointment'));
    expect(mockOnPress).toHaveBeenCalledTimes(1);
  });

  it('should display correct status color', () => {
    render(<OutPassCard outPass={mockOutPass} />);
    
    const statusText = screen.getByText('Pending');
    expect(statusText).toHaveStyle({ color: '#FFA500' }); // Orange for pending
  });
});
```

### API Testing
```typescript
// __tests__/api/outpass.test.ts
import { getOutPasses, createOutPass } from '../../src/api/outpass';
import { apiClient } from '../../src/api/client';

jest.mock('../../src/api/client');
const mockedApiClient = apiClient as jest.Mocked<typeof apiClient>;

describe('OutPass API', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('getOutPasses', () => {
    it('should fetch outpasses successfully', async () => {
      const mockResponse = {
        data: [
          { id: '1', reason: 'Medical', status: 'pending' },
          { id: '2', reason: 'Emergency', status: 'approved' },
        ],
      };

      mockedApiClient.get.mockResolvedValue(mockResponse);

      const result = await getOutPasses();

      expect(mockedApiClient.get).toHaveBeenCalledWith('/outpasses');
      expect(result).toEqual(mockResponse.data);
    });

    it('should handle API errors', async () => {
      const error = new Error('Network error');
      mockedApiClient.get.mockRejectedValue(error);

      await expect(getOutPasses()).rejects.toThrow('Network error');
    });
  });

  describe('createOutPass', () => {
    it('should create outpass successfully', async () => {
      const outpassData = {
        reason: 'Medical appointment',
        requested_at: '2024-01-01T10:00:00Z',
        valid_until: '2024-01-01T14:00:00Z',
      };

      const mockResponse = {
        data: { id: '3', ...outpassData, status: 'pending' },
      };

      mockedApiClient.post.mockResolvedValue(mockResponse);

      const result = await createOutPass(outpassData);

      expect(mockedApiClient.post).toHaveBeenCalledWith('/outpasses', outpassData);
      expect(result).toEqual(mockResponse.data);
    });
  });
});
```

## End-to-End Testing (Detox)

### Setup
```javascript
// .detoxrc.js
module.exports = {
  testRunner: 'jest',
  runnerConfig: 'e2e/config.json',
  configurations: {
    'ios.sim.debug': {
      binaryPath: 'ios/build/Build/Products/Debug-iphonesimulator/mobile.app',
      build: 'xcodebuild -workspace ios/mobile.xcworkspace -scheme mobile -configuration Debug -sdk iphonesimulator -derivedDataPath ios/build',
      type: 'ios.simulator',
      device: {
        type: 'iPhone 14',
      },
    },
  },
};
```

### E2E Test Example
```typescript
// e2e/firstTest.e2e.ts
describe('Student App E2E', () => {
  beforeAll(async () => {
    await device.launchApp();
  });

  beforeEach(async () => {
    await device.reloadReactNative();
  });

  it('should login and create outpass', async () => {
    // Login
    await element(by.id('email-input')).typeText('student1@demo.com');
    await element(by.id('password-input')).typeText('password');
    await element(by.id('login-button')).tap();

    // Wait for dashboard
    await expect(element(by.id('outpass-list'))).toBeVisible();

    // Create outpass
    await element(by.id('create-outpass-button')).tap();
    await element(by.id('reason-input')).typeText('Medical appointment');
    await element(by.id('requested-at-input')).typeText('2024-01-01 10:00');
    await element(by.id('valid-until-input')).typeText('2024-01-01 14:00');
    await element(by.id('submit-button')).tap();

    // Verify success
    await expect(element(by.id('success-message'))).toBeVisible();
  });

  it('should handle offline mode', async () => {
    // Simulate offline
    await device.setURLBlacklist(['.*']);

    // Try to create outpass
    await element(by.id('create-outpass-button')).tap();
    await element(by.id('reason-input')).typeText('Emergency');
    await element(by.id('submit-button')).tap();

    // Should show offline message
    await expect(element(by.id('offline-message'))).toBeVisible();

    // Restore online
    await device.clearURLBlacklist();
    await device.reloadReactNative();

    // Should sync pending actions
    await expect(element(by.id('sync-complete'))).toBeVisible();
  });
});
```

## Background Reminder Jobs & Dashboard Widgets

- **Room change SLA escalations**
  - Feature: `tests/Feature/RoomChanges/RoomChangeEscalationTest.php` fakes the queue to assert `SendRoomChangeEscalationNotification` dispatch and timestamp updates (`last_escalated_at`, `last_reminded_at`).
  - Manual dry run: `php artisan room-changes:escalate [--tenant=UUID]`. Expect SMS/push logs plus updated reminder columns.

- **Checklist reminder windows**
  - Feature: `tests/Feature/Checklists/ChecklistReminderCommandTest.php` validates `checklists:remind --window=morning` (mirrors 15:00 + overdue windows) and notification job payload.
  - Manual dry run: `php artisan checklists:remind --window={morning|afternoon|overdue}`.

- **Reminder Center widget**
  - Filament widget test: `tests/Feature/Filament/ReminderWidgetTest.php` mocks reminder services to verify dashboard data binding.
  - UI regression: Campus Manager dashboard should show Reminder Center card (room-change SLA counts + checklist windows) with values aligning to command outputs.

Add these commands to nightly CI once Postgres service spins up, ensuring reminder flows stay covered.

## Test Data Management

### Factories
```php
// database/factories/OutPassFactory.php
<?php

namespace Database\Factories;

use App\Models\OutPass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutPassFactory extends Factory
{
    protected $model = OutPass::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn() => \App\Models\Tenant::factory(),
            'student_id' => fn() => Student::factory(),
            'reason' => $this->faker->sentence(),
            'requested_at' => $this->faker->dateTimeBetween('now', '+1 hour'),
            'valid_until' => $this->faker->dateTimeBetween('+1 hour', '+4 hours'),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'type' => $this->faker->randomElement(['emergency', 'planned', 'medical']),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => \App\Models\User::factory(),
        ]);
    }
}
```

### Test Database Setup
```php
// tests/TestCase.php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use in-memory database for tests
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        
        // Run migrations
        $this->artisan('migrate', ['--database' => 'sqlite']);
    }
}
```

## Coverage and Quality

### Coverage Targets
- **API Tests**: 90%+ code coverage
- **Unit Tests**: 85%+ code coverage
- **Mobile Tests**: 80%+ code coverage
- **Critical Paths**: 100% coverage

### Quality Gates
```bash
# Run tests with coverage
vendor/bin/pest --coverage

# Generate coverage report
vendor/bin/pest --coverage-html coverage/

# Check coverage threshold
vendor/bin/pest --coverage --min=90
```

### Continuous Integration
```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  api-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: actions/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: vendor/bin/pest --coverage
      
  mobile-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: actions/setup-node@v2
        with:
          node-version: '18'
      - run: cd mobile && npm install
      - run: cd mobile && npm test
```

## Testing Best Practices

### Test Organization
- Group related tests using `describe()` blocks
- Use descriptive test names
- Follow AAA pattern (Arrange, Act, Assert)
- Keep tests independent and isolated

### Test Data
- Use factories for consistent test data
- Create minimal test data needed
- Use database transactions for cleanup
- Mock external services

### Performance
- Use in-memory database for tests
- Mock expensive operations
- Run tests in parallel when possible
- Keep test execution time under 5 minutes

### Maintenance
- Update tests when changing functionality
- Remove obsolete tests
- Refactor tests for better readability
- Document complex test scenarios

---

*Testing strategy version: v1.0*
*Owner: MAP Co-Pilot*
