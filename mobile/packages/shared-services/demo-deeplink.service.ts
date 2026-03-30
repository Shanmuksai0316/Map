import { Linking } from 'react-native';
import { useAuthStore } from '../store/auth.store';
import { tenantService } from '../services/tenant.service';
import { apiService } from '../services/api.service';
import { StorageService } from '../services/storage.service';
import { APP_CONFIG } from '../config/app.config';
import { User, Tenant } from '../types';

/**
 * Demo Deep Link Handler
 * 
 * Handles deep links in the format:
 * maphms://auth/demo?tenant=STXAV&token=TOKEN&role=warden&api=https%3A%2F%2Fstxaviers.mapservices.in%2Fv1
 * 
 * This bypasses OTP flow and directly authenticates with a demo token.
 */
export class DemoDeepLinkService {
  private static instance: DemoDeepLinkService;
  private listeners: Array<() => void> = [];

  private constructor() {}

  static getInstance(): DemoDeepLinkService {
    if (!DemoDeepLinkService.instance) {
      DemoDeepLinkService.instance = new DemoDeepLinkService();
    }
    return DemoDeepLinkService.instance;
  }

  /**
   * Parse and handle a deep link URL
   */
  async handleUrl(url: string): Promise<boolean> {
    try {
      // Parse URL
      const urlObj = new URL(url);
      
      // Only handle maphms:// protocol
      if (urlObj.protocol !== 'maphms:') {
        return false;
      }

      // Handle demo auth deep link
      if (urlObj.host === 'auth' && urlObj.pathname === '/demo') {
        const decodeParam = (value?: string | null): string | null => {
          if (value == null || value === '') {
            return value ?? null;
          }

          let decoded = value;

          try {
            decoded = decodeURIComponent(decoded);
          } catch (error) {
            console.warn('[DemoDeepLink] Failed primary decode', { value, error });
          }

          try {
            if (decoded.includes('%')) {
              decoded = decodeURIComponent(decoded);
            }
          } catch (error) {
            // Ignore if secondary decode fails (not needed)
          }

          return decoded;
        };

        const token = decodeParam(urlObj.searchParams.get('token')) ?? undefined;
        const tenantCode = decodeParam(urlObj.searchParams.get('tenant')) ?? undefined;
        const role = decodeParam(urlObj.searchParams.get('role')) ?? undefined;
        const apiUrl = decodeParam(urlObj.searchParams.get('api')) ?? undefined;
        const tenantId = decodeParam(urlObj.searchParams.get('tenantId')) ?? undefined;
        const tenantName = decodeParam(urlObj.searchParams.get('tenantName')) ?? undefined;
        const tenantDomain = decodeParam(urlObj.searchParams.get('tenantDomain')) ?? undefined;
        const userId = decodeParam(urlObj.searchParams.get('userId')) ?? undefined;
        const userName = decodeParam(urlObj.searchParams.get('userName')) ?? undefined;
        const userPhone = decodeParam(urlObj.searchParams.get('userPhone')) ?? undefined;
        const userEmail = decodeParam(urlObj.searchParams.get('userEmail')) ?? undefined;

        if (!token || !tenantCode || !apiUrl || !role) {
          console.warn('[DemoDeepLink] Missing required parameters:', { token: !!token, tenantCode, apiUrl: !!apiUrl, role });
          return false;
        }

        // Ensure API URL is correctly decoded and normalized
        const sanitizedApiUrl = (() => {
          let value = apiUrl;

          if (!value.startsWith('http')) {
            value = `https://${value.replace(/^https?:\/\//, '')}`;
          }

          // Append /v1 if missing
          if (!/\/(api\/)?v1$/.test(value)) {
            value = value.endsWith('/') ? `${value}v1` : `${value}/v1`;
          }

          return value.replace('/api/v1/v1', '/api/v1');
        })();

        // Construct tenant object
        const tenant: Tenant = {
          id: tenantId || tenantCode,
          code: tenantCode,
          name: tenantName || tenantCode,
          domain: tenantDomain || tenantCode.toLowerCase().replace('_', '-'),
          apiUrl: sanitizedApiUrl,
        };

        // Construct user object
        const user: Partial<User> = {
          id: userId ? parseInt(userId, 10) : 0,
          name: userName || 'Demo User',
          phone: userPhone || '',
          email: userEmail || '',
          role: role as any,
          tenant_id: tenant.id,
        };

        // Store authentication data
        StorageService.set(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN, token);
        StorageService.setObject(APP_CONFIG.STORAGE_KEYS.USER_DATA, user);
        StorageService.set(APP_CONFIG.STORAGE_KEYS.USER_ROLE, role);
        if (userPhone) {
          StorageService.set(APP_CONFIG.STORAGE_KEYS.PHONE_NUMBER, userPhone);
        }

        // Set tenant and update API base URL
        tenantService.setSelectedTenant(tenant);
        apiService.updateBaseUrl();

        // Update auth store
        const authStore = useAuthStore.getState();
        authStore.setSelectedTenant(tenant);
        authStore.setState({
          isAuthenticated: true,
          user: user as User,
          token,
        });

        console.log('[DemoDeepLink] Successfully authenticated via deep link', {
          tenant: tenant.code,
          role,
        });

        return true;
      }

      return false;
    } catch (error) {
      console.error('[DemoDeepLink] Error handling URL:', error);
      return false;
    }
  }

  /**
   * Initialize deep link listener
   */
  initialize(): () => void {
    // Handle initial URL (if app was opened via deep link)
    Linking.getInitialURL()
      .then((url) => {
        if (url) {
          this.handleUrl(url);
        }
      })
      .catch((err) => {
        console.error('[DemoDeepLink] Error getting initial URL:', err);
      });

    // Listen for deep links while app is running
    const subscription = Linking.addEventListener('url', ({ url }) => {
      this.handleUrl(url);
    });

    // Return cleanup function
    return () => {
      subscription.remove();
    };
  }
}

// Export singleton instance
export const demoDeepLinkService = DemoDeepLinkService.getInstance();

