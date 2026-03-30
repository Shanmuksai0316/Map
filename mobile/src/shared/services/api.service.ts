import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import { APP_CONFIG } from '../config/app.config';
import { StorageService } from './storage.service';
import { tenantService } from './tenant.service';

class ApiService {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: tenantService.getCurrentApiUrl(),
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    // Request interceptor to add auth token and tenant code
    this.client.interceptors.request.use(
      async (config) => {
        // Use sync get for axios interceptor (from cache)
        // Cache is updated whenever token is stored
        let rawToken = StorageService.getSync(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
        
        // If not in cache, try async get (this should rarely happen)
        if (!rawToken) {
          rawToken = await StorageService.get(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
        }
        
        // Declare finalToken outside the if block for use in console.log
        let finalToken: string = '';
        
        if (rawToken && config.headers) {
          // Ensure token is a string (handle case where it might be stored as object)
          
          if (typeof rawToken === 'string') {
            // If it's already a string, check if it's JSON-encoded
            if (rawToken.startsWith('{') || rawToken.startsWith('[')) {
              try {
                const parsed = JSON.parse(rawToken);
                // Check if it's a Zustand state object (has _h, _i, _j, _k properties)
                if (parsed._h !== undefined || parsed._i !== undefined) {
                  // This is a Zustand state object, not a token - skip it
                  console.warn('[API] Token appears to be a Zustand state object, skipping');
                  finalToken = '';
                } else {
                  // Try common token field names
                  finalToken = parsed.token || parsed.data?.token || parsed.access_token || parsed.plainTextToken || '';
                }
              } catch {
                // If JSON parsing fails, use the raw string
                finalToken = rawToken;
              }
            } else {
              finalToken = rawToken;
            }
          } else if (typeof rawToken === 'object' && rawToken !== null) {
            // If it's an object, try to extract the token
            finalToken = (rawToken as any).token || (rawToken as any).data?.token || (rawToken as any).access_token || (rawToken as any).plainTextToken || '';
          } else {
            finalToken = String(rawToken);
          }
          
          // Only set Authorization header if we have a valid token (not empty)
          if (finalToken && finalToken.trim().length > 0) {
            config.headers.Authorization = `Bearer ${finalToken}`;
          }
          
          // Authorization header is set above only if finalToken is valid
        }
        
        // Add tenant code header
        const selectedTenant = tenantService.getSelectedTenant();
        if (selectedTenant && config.headers) {
          config.headers['X-Tenant-Code'] = selectedTenant.code;
        }
        
        // Allow server to include debug info for dev builds
        if (__DEV__ && config.headers) {
          config.headers['X-Debug'] = '1';
        }

        // Always resolve latest base URL from 'tenant service'
        config.baseURL = tenantService.getCurrentApiUrl();

        if (__DEV__) {
          console.log('[API] Request', config.method?.toUpperCase(), config.baseURL, config.url, {
            tenant: selectedTenant?.code,
            hasToken: Boolean(finalToken && finalToken.trim().length > 0),
          });
        }

        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => {
        // Log response for debugging (only in dev and for specific endpoints)
        if (__DEV__ && response.config.url?.includes('/warden/rooms') && response.config.url?.includes('/students')) {
          console.log('[API] Response', response.config.method?.toUpperCase(), response.config.url, {
            status: response.status,
            dataType: typeof response.data,
            hasData: !!response.data,
            dataKeys: response.data ? Object.keys(response.data) : [],
            dataLength: Array.isArray(response.data?.data) ? response.data.data.length : 'N/A',
          });
        }
        return response;
      },
      (error) => {
        if (error.response?.status === 401) {
          // Token expired or invalid - clear auth only (keep tenant selection)
          StorageService.delete(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
          StorageService.delete(APP_CONFIG.STORAGE_KEYS.USER_DATA);
        }
        return Promise.reject(error);
      }
    );
  }

  async get<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.get(url, config);
    return response.data;
  }

  async post<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.post(url, data, config);
    return response.data;
  }

  async put<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.put(url, data, config);
    return response.data;
  }

  async patch<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.patch(url, data, config);
    return response.data;
  }

  async delete<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.delete(url, config);
    return response.data;
  }

  /**
   * Update the base URL when tenant changes
   */
  updateBaseUrl(): void {
    this.client.defaults.baseURL = tenantService.getCurrentApiUrl();
  }
}

export const apiService = new ApiService();
