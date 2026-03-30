# MAP-HMS Mobile Guide

Comprehensive guide for React Native mobile app development in MAP-HMS.

## App Structure

```
mobile/
├── src/
│   ├── screens/           # App screens
│   │   ├── Auth/         # Authentication screens
│   │   ├── Student/      # Student-specific screens
│   │   └── Guard/        # Guard-specific screens
│   ├── navigation/       # Navigation setup
│   ├── store/           # Zustand state management
│   ├── api/             # API client
│   ├── components/      # Reusable components
│   ├── types/           # TypeScript type definitions
│   ├── utils/           # Utility functions
│   └── validation/      # Form validation schemas
├── android/             # Android-specific code
├── ios/                 # iOS-specific code
└── app.config.json      # App configuration
```

## Navigation Architecture

### Student App Navigation
```typescript
// StudentStack.tsx
export type StudentStackParamList = {
  Login: { email?: string } | undefined;
  OutPassList: undefined;
  CreateOutPass: undefined;
  OutPassDetail: { id: string };
};
```

### Guard App Navigation
```typescript
// GuardNavigator.tsx
export type GuardStackParamList = {
  GateHome: undefined;
  GateOut: undefined;
  GateIn: undefined;
  OutPassesToday: undefined;
  VisitorsToday: undefined;
};
```

## State Management (Zustand)

### Store Structure
```typescript
// Base store pattern
interface BaseStoreState {
  loading: boolean;
  error?: string;
  setLoading: (loading: boolean) => void;
  setError: (error?: string) => void;
}
```

### Auth Store
```typescript
/**
 * Store: AuthStore
 * State shape: { user: User | null, loading: boolean, error?: string }
 * Actions: login, logout
 * Persistence: Token stored in AsyncStorage
 * Offline behavior: Works offline, syncs on reconnect
 */

interface AuthState {
  user: User | null;
  loading: boolean;
  error?: string;
  
  // Actions
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}
```

### OutPass Store
```typescript
/**
 * Store: OutPassStore
 * State shape: { outpasses: OutPass[], pendingActions: PendingAction[] }
 * Actions: fetchOutPasses, createOutPass, cancelOutPass
 * Persistence: Cached locally, synced on reconnect
 * Offline behavior: Queues actions when offline
 */

interface OutPassState {
  outpasses: OutPass[];
  pendingActions: PendingAction[];
  loading: boolean;
  error?: string;
  
  // Actions
  fetchOutPasses: () => Promise<void>;
  createOutPass: (data: CreateOutPassData) => Promise<void>;
  cancelOutPass: (id: string) => Promise<void>;
  
  // Offline management
  syncPendingActions: () => Promise<void>;
  clearPendingActions: () => void;
}
```

### Gate Store
```typescript
/**
 * Store: GateStore
 * State shape: { outpasses: OutPass[], visitors: Visitor[], offlineActions: OfflineAction[] }
 * Actions: fetchTodayData, recordEntry, allowVisitor
 * Persistence: Cached locally, synced on reconnect
 * Offline behavior: Queues gate operations when offline
 */

interface GateState {
  outpasses: OutPass[];
  visitors: Visitor[];
  offlineActions: OfflineAction[];
  onlineStatus: boolean;
  
  // Actions
  fetchTodayData: () => Promise<void>;
  recordEntry: (type: 'in' | 'out', studentId: string) => Promise<void>;
  allowVisitor: (visitorId: string) => Promise<void>;
  
  // Offline management
  setOnlineStatus: (online: boolean) => void;
  syncOfflineActions: () => Promise<void>;
}
```

## API Client

### Base Configuration
```typescript
/**
 * API Client: Base configuration for all API calls
 * Features: JWT authentication, offline queue, error handling
 * Base URL: Configurable via app.config.json
 */

const apiClient = axios.create({
  baseURL: Config.API_BASE,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
});
```

### Authentication
```typescript
// Request interceptor for JWT token
apiClient.interceptors.request.use((config) => {
  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized - redirect to login
      useAuthStore.getState().logout();
    }
    return Promise.reject(error);
  }
);
```

### API Modules
```typescript
// auth.ts - Authentication API
export const login = (credentials: LoginCredentials) => 
  apiClient.post('/auth/login', credentials);

export const logout = () => 
  apiClient.post('/auth/logout');

// outpass.ts - OutPass API
export const getOutPasses = () => 
  apiClient.get('/outpasses');

export const createOutPass = (data: CreateOutPassData) => 
  apiClient.post('/outpasses', data);

// gate.ts - Gate API
export const getTodayOutPasses = () => 
  apiClient.get('/gate/outpasses/today');

export const recordGateEntry = (data: GateEntryData) => 
  apiClient.post('/gate/out', data);
```

## Offline Support

### Offline Queue System
```typescript
/**
 * Offline Queue: Manages actions when device is offline
 * Storage: AsyncStorage for persistence
 * Sync: Automatic sync when connection restored
 * Features: Retry logic, conflict resolution
 */

interface OfflineAction {
  id: string;
  type: string;
  endpoint: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
  data?: any;
  timestamp: number;
  retryCount: number;
}

class OfflineQueue {
  private actions: OfflineAction[] = [];
  
  async addAction(action: Omit<OfflineAction, 'id' | 'timestamp' | 'retryCount'>) {
    // Add action to queue
  }
  
  async syncActions() {
    // Process all queued actions
  }
  
  async clearActions() {
    // Clear successfully synced actions
  }
}
```

### Offline Indicators
```typescript
// Network status monitoring
import NetInfo from '@react-native-netinfo/netinfo';

const useNetworkStatus = () => {
  const [isConnected, setIsConnected] = useState(true);
  
  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener(state => {
      setIsConnected(state.isConnected ?? false);
    });
    
    return unsubscribe;
  }, []);
  
  return isConnected;
};

// Offline banner component
const OfflineBanner = () => {
  const isConnected = useNetworkStatus();
  const pendingCount = useOutPassStore(state => state.pendingActions.length);
  
  if (isConnected || pendingCount === 0) return null;
  
  return (
    <View style={styles.offlineBanner}>
      <Text>Offline - {pendingCount} actions pending sync</Text>
    </View>
  );
};
```

## Screen Components

### Authentication Screen
```typescript
/**
 * Screen: LoginScreen
 * Purpose: User authentication
 * Navigation params: { email?: string }
 * API deps: login API endpoint
 * Offline behavior: Works offline, queues login on reconnect
 */

export function LoginScreen(): JSX.Element {
  const { login, loading, error } = useAuthStore();
  const { control, handleSubmit } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });
  
  const onSubmit = async (data: LoginFormData) => {
    try {
      await login(data.email, data.password);
    } catch (error) {
      // Error handled by store
    }
  };
  
  return (
    <View style={styles.container}>
      <TextInput
        control={control}
        name="email"
        placeholder="Email"
        keyboardType="email-address"
        autoCapitalize="none"
      />
      <TextInput
        control={control}
        name="password"
        placeholder="Password"
        secureTextEntry
      />
      <Button
        title="Login"
        onPress={handleSubmit(onSubmit)}
        loading={loading}
      />
      {error && <Text style={styles.error}>{error}</Text>}
    </View>
  );
}
```

### OutPass List Screen
```typescript
/**
 * Screen: OutPassListScreen
 * Purpose: Display student's out-pass requests
 * Navigation params: None
 * API deps: getOutPasses endpoint
 * Offline behavior: Shows cached data, syncs on refresh
 */

export function OutPassListScreen(): JSX.Element {
  const { outpasses, loading, fetchOutPasses } = useOutPassStore();
  
  useEffect(() => {
    fetchOutPasses();
  }, []);
  
  const renderOutPass = ({ item }: { item: OutPass }) => (
    <OutPassCard
      outPass={item}
      onPress={() => navigation.navigate('OutPassDetail', { id: item.id })}
    />
  );
  
  return (
    <View style={styles.container}>
      <FlatList
        data={outpasses}
        renderItem={renderOutPass}
        keyExtractor={(item) => item.id}
        refreshing={loading}
        onRefresh={fetchOutPasses}
      />
    </View>
  );
}
```

### Gate Home Screen
```typescript
/**
 * Screen: GateHome
 * Purpose: Guard dashboard for gate operations
 * Navigation params: None
 * API deps: getTodayOutPasses, getTodayVisitors
 * Offline behavior: Works offline, syncs data on reconnect
 */

export function GateHome(): JSX.Element {
  const { outpasses, visitors, fetchTodayData } = useGateStore();
  const isOnline = useNetworkStatus();
  
  useEffect(() => {
    fetchTodayData();
  }, []);
  
  return (
    <View style={styles.container}>
      <OfflineBanner />
      
      <View style={styles.statsContainer}>
        <StatCard
          title="Approved Out-Passes"
          value={outpasses.length}
          icon="exit-to-app"
        />
        <StatCard
          title="Pending Visitors"
          value={visitors.filter(v => v.status === 'pending').length}
          icon="people"
        />
      </View>
      
      <View style={styles.actionButtons}>
        <Button
          title="Record OUT"
          onPress={() => navigation.navigate('GateOut')}
          style={styles.outButton}
        />
        <Button
          title="Record IN"
          onPress={() => navigation.navigate('GateIn')}
          style={styles.inButton}
        />
      </View>
    </View>
  );
}
```

## Form Validation

### Validation Schemas (Zod)
```typescript
// login.ts
export const loginSchema = z.object({
  email: z.string().email('Invalid email address'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
});

// outpass.ts
export const createOutPassSchema = z.object({
  reason: z.string().min(10, 'Reason must be at least 10 characters'),
  requested_at: z.string().datetime('Invalid date format'),
  valid_until: z.string().datetime('Invalid date format'),
}).refine(
  (data) => new Date(data.valid_until) > new Date(data.requested_at),
  {
    message: 'Valid until must be after requested time',
    path: ['valid_until'],
  }
);
```

### Form Components
```typescript
// Custom form components with validation
const TextInput = ({ control, name, ...props }) => {
  const { field, fieldState } = useController({
    control,
    name,
  });
  
  return (
    <View>
      <RNTextInput
        {...field}
        {...props}
        onChangeText={field.onChange}
        onBlur={field.onBlur}
        value={field.value}
      />
      {fieldState.error && (
        <Text style={styles.error}>{fieldState.error.message}</Text>
      )}
    </View>
  );
};
```

## Push Notifications

### FCM Integration
```typescript
// Push notification setup
import messaging from '@react-native-firebase/messaging';

const usePushNotifications = () => {
  useEffect(() => {
    // Request permission
    const requestPermission = async () => {
      const authStatus = await messaging().requestPermission();
      const enabled = authStatus === messaging.AuthorizationStatus.AUTHORIZED ||
                     authStatus === messaging.AuthorizationStatus.PROVISIONAL;
      
      if (enabled) {
        const token = await messaging().getToken();
        // Send token to server
        await apiClient.post('/notifications/register-token', { token });
      }
    };
    
    requestPermission();
  }, []);
  
  useEffect(() => {
    // Handle foreground messages
    const unsubscribe = messaging().onMessage(async remoteMessage => {
      // Handle foreground notification
    });
    
    return unsubscribe;
  }, []);
};
```

## Environment Configuration

### App Configuration
```json
// app.config.json
{
  "API_BASE": "http://10.0.2.2:8000/api/v1",
  "DEVICE_UUID": "GUARD-DEMO-UUID-0001",
  "HOSTEL_ID": 1,
  "ENVIRONMENT": "development",
  "SENTRY_DSN": "",
  "ANALYTICS_ENABLED": true
}
```

### Environment-Specific Configs
```typescript
// config.ts
const config = {
  development: {
    API_BASE: 'http://10.0.2.2:8000/api/v1',
    LOG_LEVEL: 'debug',
  },
  staging: {
    API_BASE: 'https://staging-api.map-hms.com/api/v1',
    LOG_LEVEL: 'info',
  },
  production: {
    API_BASE: 'https://api.map-hms.com/api/v1',
    LOG_LEVEL: 'error',
  },
};

export const Config = config[__DEV__ ? 'development' : 'production'];
```

## Testing

### Unit Tests (Jest)
```typescript
// store/auth.test.ts
describe('AuthStore', () => {
  it('should login successfully', async () => {
    const store = useAuthStore.getState();
    const mockResponse = { user: mockUser, token: 'jwt-token' };
    
    jest.spyOn(apiClient, 'post').mockResolvedValue({ data: mockResponse });
    
    await store.login('test@example.com', 'password');
    
    expect(store.user).toEqual(mockUser);
    expect(store.loading).toBe(false);
    expect(store.error).toBeUndefined();
  });
});
```

### Component Tests
```typescript
// components/OutPassCard.test.tsx
describe('OutPassCard', () => {
  it('should render outpass information correctly', () => {
    const mockOutPass = {
      id: '1',
      reason: 'Medical appointment',
      status: 'approved',
      requested_at: '2024-01-01T10:00:00Z',
    };
    
    render(<OutPassCard outPass={mockOutPass} />);
    
    expect(screen.getByText('Medical appointment')).toBeOnTheScreen();
    expect(screen.getByText('Approved')).toBeOnTheScreen();
  });
});
```

## Performance Optimization

### Image Optimization
```typescript
// Optimized image component
const OptimizedImage = ({ source, ...props }) => {
  return (
    <Image
      source={source}
      resizeMode="cover"
      style={props.style}
      // Enable caching
      cache="force-cache"
    />
  );
};
```

### List Optimization
```typescript
// Optimized FlatList
const OptimizedList = ({ data, renderItem }) => {
  return (
    <FlatList
      data={data}
      renderItem={renderItem}
      keyExtractor={(item) => item.id}
      // Performance optimizations
      removeClippedSubviews={true}
      maxToRenderPerBatch={10}
      windowSize={10}
      getItemLayout={(data, index) => ({
        length: ITEM_HEIGHT,
        offset: ITEM_HEIGHT * index,
        index,
      })}
    />
  );
};
```

## Security Considerations

### Data Protection
- No sensitive data stored in AsyncStorage
- JWT tokens stored securely
- PII data encrypted in transit
- Automatic logout on token expiry

### Screenshot Protection
```typescript
// Prevent screenshots on sensitive screens
import { preventScreenshot, allowScreenshot } from 'react-native-screenshot-prevent';

const SensitiveScreen = () => {
  useEffect(() => {
    preventScreenshot();
    return () => allowScreenshot();
  }, []);
  
  // Screen content
};
```

## Deployment

### Android Build
```bash
# Generate signed APK
cd android
./gradlew assembleRelease

# Generate AAB for Play Store
./gradlew bundleRelease
```

### iOS Build
```bash
# Build for device
npx react-native run-ios --device

# Archive for App Store
cd ios
xcodebuild -workspace mobile.xcworkspace -scheme mobile archive
```

---

*Mobile guide version: v1.0*
*Owner: MAP Co-Pilot*
